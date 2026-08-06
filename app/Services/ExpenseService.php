<?php

namespace App\Services;

use App\Models\Advance;
use App\Models\Expense;
use App\Models\Receipt;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\DictionaryResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ExpenseService
{
    public const ACCOUNTS = [
        WalletTransaction::ACCOUNT_WALLET,
        WalletTransaction::ACCOUNT_ADVANCE,
        WalletTransaction::ACCOUNT_UNASSIGNED,
    ];

    public function __construct(
        protected FinanceLedger $ledger,
        protected AdvanceService $advances,
    ) {}

    public function addExpense(User $user, array $data, ?Advance $advance = null): Expense
    {
        return DB::transaction(function () use ($user, $data, $advance) {
            if ($advance) {
                $advance = Advance::whereKey($advance->id)->lockForUpdate()->with('status')->firstOrFail();
                if (! $user->canAccessOwned($advance->user_id)) {
                    throw new InvalidArgumentException('Чужой аванс');
                }
                if (! in_array($advance->status?->slug, ['received', 'reporting'], true)) {
                    throw new InvalidArgumentException('Траты к авансу — только после получения денег');
                }
            }

            $amountMinor = array_key_exists('amount_minor', $data)
                ? (int) $data['amount_minor']
                : DictionaryResolver::rublesToMinor($data['amount'] ?? 0);

            if ($amountMinor <= 0) {
                throw new InvalidArgumentException('Сумма траты должна быть больше нуля');
            }

            $articleId = DictionaryResolver::expenseArticleId($data['article_id'] ?? null);
            if (! $articleId) {
                throw new InvalidArgumentException('Укажите статью расхода');
            }

            $owner = $this->resolveOwner($user, $advance?->user_id);
            $supplierId = $this->resolveSupplier($owner, $data['supplier_id'] ?? null);

            $taskId = null;
            if (! empty($data['task_id'])) {
                $taskId = (int) $data['task_id'];
                $task = Task::query()->find($taskId);
                if (! $task || ! $user->canAccessOwned($task->user_id)) {
                    throw new InvalidArgumentException('Поручение не найдено');
                }
            }

            $debitAccount = $this->normalizeAccount(
                $data['debit_account'] ?? ($advance ? WalletTransaction::ACCOUNT_ADVANCE : WalletTransaction::ACCOUNT_UNASSIGNED)
            );

            if ($advance && $debitAccount === WalletTransaction::ACCOUNT_ADVANCE) {
                // ok
            } elseif ($advance && $debitAccount === WalletTransaction::ACCOUNT_WALLET) {
                // linked but paid from wallet
            } elseif ($advance && $debitAccount === WalletTransaction::ACCOUNT_UNASSIGNED) {
                throw new InvalidArgumentException('К авансу нельзя привязать неразнесённый счёт');
            } elseif (! $advance && $debitAccount === WalletTransaction::ACCOUNT_ADVANCE) {
                throw new InvalidArgumentException('Для списания с аванса укажите аванс');
            }

            $expense = Expense::create([
                'user_id' => $owner->id,
                'advance_id' => $advance?->id,
                'debit_account' => $debitAccount,
                'article_id' => $articleId,
                'supplier_id' => $supplierId,
                'task_id' => $taskId,
                'amount_minor' => $amountMinor,
                'description' => $data['description'] ?? null,
            ]);

            $this->postExpenseDebits($owner, $expense, $advance, $amountMinor, $debitAccount, $data['occurred_at'] ?? null);

            $receiptFiles = $data['receipt_files'] ?? [];
            foreach ($receiptFiles as $file) {
                if ($file instanceof UploadedFile) {
                    $this->addReceipt($expense, $file);
                }
            }

            if ($advance) {
                $this->advances->markReportingIfNeeded($advance);
                $this->advances->maybeAutoClose($advance);
            }

            return $expense->fresh(['receipts', 'article', 'supplier']);
        });
    }

    public function updateExpense(User $user, Expense $expense, array $data): Expense
    {
        return DB::transaction(function () use ($user, $expense, $data) {
            $expense = Expense::whereKey($expense->id)->lockForUpdate()->firstOrFail();
            if (! $user->canAccessOwned($expense->user_id)) {
                throw new InvalidArgumentException('Чужая трата');
            }

            $advance = $expense->advance_id
                ? Advance::whereKey($expense->advance_id)->lockForUpdate()->with('status')->first()
                : null;

            if ($advance?->status?->slug === 'closed') {
                throw new InvalidArgumentException('Нельзя менять трату закрытого аванса');
            }

            $oldAmount = (int) $expense->amount_minor;
            $oldAccount = $expense->debit_account;

            if (array_key_exists('article_id', $data)) {
                $articleId = DictionaryResolver::expenseArticleId($data['article_id']);
                if (! $articleId) {
                    throw new InvalidArgumentException('Укажите статью расхода');
                }
                $expense->article_id = $articleId;
            }

            if (array_key_exists('supplier_id', $data)) {
                $expense->supplier_id = $this->resolveSupplier($user, $data['supplier_id']);
            }

            if (array_key_exists('description', $data)) {
                $expense->description = $data['description'];
            }

            if (array_key_exists('task_id', $data)) {
                if ($data['task_id'] === null || $data['task_id'] === '') {
                    $expense->task_id = null;
                } else {
                    $taskId = (int) $data['task_id'];
                    $task = Task::query()->find($taskId);
                    if (! $task || ! $user->canAccessOwned($task->user_id)) {
                        throw new InvalidArgumentException('Поручение не найдено');
                    }
                    $expense->task_id = $taskId;
                }
            }

            $newAccount = array_key_exists('debit_account', $data)
                ? $this->normalizeAccount($data['debit_account'])
                : $oldAccount;

            $newAmount = null;
            if (array_key_exists('amount_minor', $data)) {
                $newAmount = (int) $data['amount_minor'];
            } elseif (array_key_exists('amount', $data)) {
                $newAmount = DictionaryResolver::rublesToMinor($data['amount']);
            }
            $newAmount ??= $oldAmount;

            if ($newAmount <= 0) {
                throw new InvalidArgumentException('Сумма траты должна быть больше нуля');
            }

            if ($newAccount === WalletTransaction::ACCOUNT_ADVANCE && ! $advance) {
                throw new InvalidArgumentException('Для списания с аванса укажите аванс');
            }

            $owner = $this->resolveOwner($user, $expense->user_id);
            $occurredAt = array_key_exists('occurred_at', $data)
                ? $data['occurred_at']
                : $this->expenseOccurredAt($expense);

            if ($newAmount !== $oldAmount || $newAccount !== $oldAccount) {
                $this->reverseExpenseDebits($owner, $expense, $occurredAt);
                $expense->amount_minor = $newAmount;
                $expense->debit_account = $newAccount;
                $expense->save();
                $this->postExpenseDebits($owner, $expense, $advance, $newAmount, $newAccount, $occurredAt);
            } else {
                $expense->save();
                if (array_key_exists('occurred_at', $data)) {
                    $this->touchExpenseOccurredAt($expense, $occurredAt);
                }
            }

            if ($advance) {
                $this->advances->markReportingIfNeeded($advance);
                $this->advances->maybeAutoClose($advance->fresh('status'));
            }

            return $expense->fresh(['receipts', 'article', 'supplier']);
        });
    }

    public function reclassify(User $user, Expense $expense, string $debitAccount, ?Advance $advance = null): Expense
    {
        $data = ['debit_account' => $debitAccount];
        if ($advance) {
            return DB::transaction(function () use ($user, $expense, $debitAccount, $advance) {
                $expense = Expense::whereKey($expense->id)->lockForUpdate()->firstOrFail();
                if ($expense->advance_id && (int) $expense->advance_id !== (int) $advance->id) {
                    throw new InvalidArgumentException('Трата привязана к другому авансу');
                }
                $expense->advance_id = $advance->id;
                $expense->save();

                return $this->updateExpense($user, $expense, ['debit_account' => $debitAccount]);
            });
        }

        return $this->updateExpense($user, $expense, $data);
    }

    public function attachToAdvance(Advance $advance, Expense $expense, ?string $debitAccount = null): Expense
    {
        return DB::transaction(function () use ($advance, $expense, $debitAccount) {
            $advance = Advance::whereKey($advance->id)->lockForUpdate()->with(['status', 'user'])->firstOrFail();
            $expense = Expense::whereKey($expense->id)->lockForUpdate()->firstOrFail();

            if ($advance->status?->slug === 'closed') {
                throw new InvalidArgumentException('К закрытому авансу нельзя прикреплять');
            }
            if (! in_array($advance->status?->slug, ['received', 'reporting'], true)) {
                throw new InvalidArgumentException('Сначала отметьте получение денег');
            }
            if ($expense->advance_id && (int) $expense->advance_id !== (int) $advance->id) {
                throw new InvalidArgumentException('Трата уже привязана к другому авансу');
            }

            $targetAccount = $debitAccount
                ? $this->normalizeAccount($debitAccount)
                : ($expense->debit_account === WalletTransaction::ACCOUNT_UNASSIGNED
                    ? WalletTransaction::ACCOUNT_ADVANCE
                    : $expense->debit_account);

            if ($targetAccount === WalletTransaction::ACCOUNT_UNASSIGNED) {
                throw new InvalidArgumentException('Укажите счёт списания: кошелёк или аванс');
            }

            $expense->advance_id = $advance->id;
            $expense->save();

            $owner = $advance->user;
            $occurredAt = $this->expenseOccurredAt($expense);
            $this->reverseExpenseDebits($owner, $expense, $occurredAt);
            $expense->debit_account = $targetAccount;
            $expense->save();
            $this->postExpenseDebits($owner, $expense, $advance, (int) $expense->amount_minor, $targetAccount, $occurredAt);

            $this->advances->markReportingIfNeeded($advance);
            $this->advances->maybeAutoClose($advance);

            return $expense->fresh(['receipts', 'article', 'supplier']);
        });
    }

    public function detachFromAdvance(Advance $advance, Expense $expense): Expense
    {
        return DB::transaction(function () use ($advance, $expense) {
            $advance = Advance::whereKey($advance->id)->lockForUpdate()->with(['status', 'user'])->firstOrFail();
            $expense = Expense::whereKey($expense->id)->lockForUpdate()->firstOrFail();

            if ($advance->status?->slug === 'closed') {
                throw new InvalidArgumentException('От закрытого аванса открепить нельзя');
            }
            if ((int) $expense->advance_id !== (int) $advance->id) {
                throw new InvalidArgumentException('Трата не привязана к этому авансу');
            }

            $owner = $advance->user;
            $occurredAt = $this->expenseOccurredAt($expense);
            $this->reverseExpenseDebits($owner, $expense, $occurredAt);

            $expense->advance_id = null;
            $expense->debit_account = WalletTransaction::ACCOUNT_UNASSIGNED;
            $expense->save();

            $this->postExpenseDebits($owner, $expense, null, (int) $expense->amount_minor, WalletTransaction::ACCOUNT_UNASSIGNED, $occurredAt);

            return $expense->fresh(['receipts', 'article', 'supplier']);
        });
    }

    public function destroyExpense(User $user, Expense $expense): void
    {
        DB::transaction(function () use ($user, $expense) {
            $expense = Expense::whereKey($expense->id)->lockForUpdate()->with('receipts')->firstOrFail();
            if (! $user->canAccessOwned($expense->user_id)) {
                throw new InvalidArgumentException('Чужая трата');
            }

            $advance = $expense->advance_id
                ? Advance::whereKey($expense->advance_id)->lockForUpdate()->with('status')->first()
                : null;

            if ($advance?->status?->slug === 'closed') {
                throw new InvalidArgumentException('Нельзя удалить трату закрытого аванса');
            }

            $owner = $this->resolveOwner($user, $expense->user_id);
            $wallet = \App\Models\Wallet::query()->firstOrCreate(
                ['user_id' => $owner->id],
                ['balance_minor' => 0, 'currency' => 'RUB'],
            );
            $wallet = \App\Models\Wallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();

            // Hard-delete ledger rows and restore stored wallet balance for wallet-account debits.
            $txs = WalletTransaction::query()
                ->where('expense_id', $expense->id)
                ->lockForUpdate()
                ->get();

            $walletDelta = 0;
            foreach ($txs as $tx) {
                if ($tx->account === WalletTransaction::ACCOUNT_WALLET) {
                    $walletDelta -= (int) $tx->amount_minor;
                }
            }
            if ($walletDelta !== 0) {
                $wallet->increment('balance_minor', $walletDelta);
            }

            WalletTransaction::query()->where('expense_id', $expense->id)->delete();

            foreach ($expense->receipts as $receipt) {
                Storage::disk('public')->delete($receipt->path);
                $receipt->delete();
            }

            $expense->delete();
        });
    }

    public function addReceipt(Expense $expense, UploadedFile $file): Receipt
    {
        $dir = 'receipts/'.($expense->advance_id ?: 'free')."/{$expense->id}";
        $name = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs($dir, $name, 'public');

        return Receipt::create([
            'expense_id' => $expense->id,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize() ?: 0,
        ]);
    }

    public function destroyReceipt(Receipt $receipt): void
    {
        Storage::disk('public')->delete($receipt->path);
        $receipt->delete();
    }

    protected function postExpenseDebits(
        User $owner,
        Expense $expense,
        ?Advance $advance,
        int $amountMinor,
        string $debitAccount,
        mixed $occurredAt = null,
    ): void {
        $linksBase = ['occurred_at' => $occurredAt];

        if ($debitAccount === WalletTransaction::ACCOUNT_UNASSIGNED) {
            $this->ledger->apply($owner, WalletTransaction::TYPE_EXPENSE, WalletTransaction::ACCOUNT_UNASSIGNED, -$amountMinor, [
                ...$linksBase,
                'advance_id' => null,
                'expense_id' => $expense->id,
            ]);

            return;
        }

        if ($debitAccount === WalletTransaction::ACCOUNT_WALLET) {
            $this->ledger->apply($owner, WalletTransaction::TYPE_EXPENSE, WalletTransaction::ACCOUNT_WALLET, -$amountMinor, [
                ...$linksBase,
                'advance_id' => $advance?->id,
                'expense_id' => $expense->id,
            ]);

            return;
        }

        // advance account — with overspend tail from wallet
        $rem = $advance ? max(0, $this->ledger->advanceBalanceMinor($advance->id)) : 0;
        $fromAdvance = min($amountMinor, $rem);
        $fromWallet = $amountMinor - $fromAdvance;

        if ($fromAdvance > 0) {
            $this->ledger->apply($owner, WalletTransaction::TYPE_EXPENSE, WalletTransaction::ACCOUNT_ADVANCE, -$fromAdvance, [
                ...$linksBase,
                'advance_id' => $advance?->id,
                'expense_id' => $expense->id,
            ]);
        }
        if ($fromWallet > 0) {
            $this->ledger->apply($owner, WalletTransaction::TYPE_EXPENSE, WalletTransaction::ACCOUNT_WALLET, -$fromWallet, [
                ...$linksBase,
                'advance_id' => $advance?->id,
                'expense_id' => $expense->id,
                'meta' => ['kind' => 'advance_overspend'],
            ]);
        }
    }

    protected function reverseExpenseDebits(User $owner, Expense $expense, mixed $occurredAt = null): void
    {
        $nets = WalletTransaction::query()
            ->where('expense_id', $expense->id)
            ->where('type', WalletTransaction::TYPE_EXPENSE)
            ->selectRaw('account, advance_id, sum(amount_minor) as total')
            ->groupBy('account', 'advance_id')
            ->get();

        foreach ($nets as $row) {
            $total = (int) $row->total;
            if ($total === 0) {
                continue;
            }

            $this->ledger->apply($owner, WalletTransaction::TYPE_EXPENSE, $row->account, -$total, [
                'occurred_at' => $occurredAt,
                'advance_id' => $row->advance_id,
                'expense_id' => $expense->id,
                'meta' => ['reason' => 'expense_reverse'],
            ]);
        }
    }

    protected function expenseOccurredAt(Expense $expense): mixed
    {
        $tx = WalletTransaction::query()
            ->where('expense_id', $expense->id)
            ->where('type', WalletTransaction::TYPE_EXPENSE)
            ->orderByDesc('id')
            ->get()
            ->first(function (WalletTransaction $tx) {
                $meta = is_array($tx->meta) ? $tx->meta : [];

                return ($meta['reason'] ?? null) !== 'expense_reverse';
            });

        return $tx?->occurred_at;
    }

    protected function touchExpenseOccurredAt(Expense $expense, mixed $occurredAt): void
    {
        $resolved = $this->ledger->resolveOccurredAt($occurredAt);
        WalletTransaction::query()
            ->where('expense_id', $expense->id)
            ->where('type', WalletTransaction::TYPE_EXPENSE)
            ->get()
            ->each(function (WalletTransaction $tx) use ($resolved) {
                $meta = is_array($tx->meta) ? $tx->meta : [];
                if (($meta['reason'] ?? null) === 'expense_reverse') {
                    return;
                }
                $tx->occurred_at = $resolved;
                $tx->save();
            });
    }

    protected function normalizeAccount(?string $account): string
    {
        $account = $account ?: WalletTransaction::ACCOUNT_UNASSIGNED;
        if (! in_array($account, self::ACCOUNTS, true)) {
            throw new InvalidArgumentException('Неизвестный счёт списания');
        }

        return $account;
    }

    protected function resolveOwner(User $actor, ?int $ownerId): User
    {
        if ($ownerId === null || (int) $ownerId === (int) $actor->id) {
            return $actor;
        }

        return User::query()->findOrFail($ownerId);
    }

    protected function resolveSupplier(User $user, mixed $supplierId): ?int
    {
        if ($supplierId === null || $supplierId === '') {
            return null;
        }

        $query = Supplier::query()->whereKey((int) $supplierId);
        if (! $user->is_admin) {
            $query->where('user_id', $user->id);
        }

        $supplier = $query->first();

        if (! $supplier) {
            throw new InvalidArgumentException('Поставщик не найден');
        }

        return (int) $supplier->id;
    }
}
