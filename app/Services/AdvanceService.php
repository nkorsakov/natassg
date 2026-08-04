<?php

namespace App\Services;

use App\Models\Advance;
use App\Models\Task;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\DictionaryResolver;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AdvanceService
{
    /** @var array<string, list<string>> */
    protected const TRANSITIONS = [
        'pending' => ['received', 'closed'],
        'received' => ['reporting', 'closed', 'pending'],
        'reporting' => ['received', 'closed', 'pending'],
        'closed' => [],
        // legacy aliases mapped before transition check
        'issued' => ['reporting', 'closed', 'pending'],
        'approved' => ['received', 'closed', 'pending'],
    ];

    public function __construct(protected FinanceLedger $ledger) {}

    public function create(User $user, array $data): Advance
    {
        $statusSlug = $this->normalizeStatusSlug($data['status_id'] ?? 'pending');

        $amountMinor = array_key_exists('amount_minor', $data)
            ? (int) $data['amount_minor']
            : DictionaryResolver::rublesToMinor($data['amount'] ?? 0);

        $methodId = null;
        if (! empty($data['disbursement_method_id'])) {
            $methodId = DictionaryResolver::disbursementMethodId($data['disbursement_method_id']);
        }

        if ($statusSlug === 'received') {
            if ($amountMinor <= 0) {
                throw new InvalidArgumentException('Перед получением укажите сумму больше нуля');
            }
            if (! $methodId) {
                throw new InvalidArgumentException('Укажите способ выдачи');
            }
        }

        $advance = Advance::create([
            'user_id' => $user->id,
            'status_id' => DictionaryResolver::advanceStatusId($statusSlug),
            'disbursement_method_id' => $methodId,
            'title' => array_key_exists('title', $data) ? (string) ($data['title'] ?? '') : '',
            'amount_minor' => $amountMinor,
            'note' => $data['note'] ?? null,
        ]);

        $taskIds = $this->normalizeTaskIds($data, $user);
        if ($taskIds !== null) {
            $advance->tasks()->sync($taskIds);
        }

        if ($statusSlug === 'received') {
            $this->markReceived($advance->fresh(['status']));
        }

        return $advance->fresh(['status', 'disbursementMethod', 'tasks', 'expenses.receipts', 'expenses.article', 'expenses.supplier']);
    }

    public function update(Advance $advance, array $data): Advance
    {
        return DB::transaction(function () use ($advance, $data) {
            $advance = Advance::whereKey($advance->id)->lockForUpdate()->firstOrFail();
            $advance->load('status');

            $previousSlug = $this->normalizeStatusSlug($advance->status?->slug);
            $wasReceived = in_array($previousSlug, ['received', 'reporting', 'closed'], true)
                || $this->ledger->hasIncomeForAdvance($advance->id);

            if (isset($data['status_id'])) {
                $newSlug = $this->normalizeStatusSlug(
                    is_numeric($data['status_id'])
                        ? (\App\Models\AdvanceStatus::query()->whereKey((int) $data['status_id'])->value('slug') ?? null)
                        : (string) $data['status_id']
                );

                if (! $newSlug) {
                    throw new InvalidArgumentException('Неизвестный статус аванса');
                }

                $this->assertTransition($previousSlug, $newSlug);

                if ($newSlug === 'received') {
                    $amountForReceive = array_key_exists('amount', $data) || array_key_exists('amount_minor', $data)
                        ? (array_key_exists('amount_minor', $data)
                            ? (int) $data['amount_minor']
                            : DictionaryResolver::rublesToMinor($data['amount']))
                        : (int) $advance->amount_minor;

                    $methodId = array_key_exists('disbursement_method_id', $data)
                        ? DictionaryResolver::disbursementMethodId($data['disbursement_method_id'])
                        : $advance->disbursement_method_id;

                    if ($amountForReceive <= 0) {
                        throw new InvalidArgumentException('Перед получением укажите сумму больше нуля');
                    }
                    if (! $methodId) {
                        throw new InvalidArgumentException('Укажите способ выдачи');
                    }

                    $advance->amount_minor = $amountForReceive;
                    $advance->disbursement_method_id = $methodId;
                }

                if ($newSlug === 'closed') {
                    throw new InvalidArgumentException('Закрывайте аванс через «в кошелёк» или «списание без отчёта»');
                }

                $advance->status_id = DictionaryResolver::advanceStatusId($newSlug);

                if ($previousSlug === 'closed' && $newSlug !== 'closed') {
                    $advance->closed_at = null;
                }
            }

            if (array_key_exists('disbursement_method_id', $data) && ! isset($data['status_id'])) {
                $advance->disbursement_method_id = $data['disbursement_method_id']
                    ? DictionaryResolver::disbursementMethodId($data['disbursement_method_id'])
                    : null;
            }

            if (isset($data['title'])) {
                $advance->title = $data['title'];
            }

            if (array_key_exists('note', $data)) {
                $advance->note = $data['note'];
            }

            $newAmount = null;
            if (array_key_exists('amount_minor', $data)) {
                $newAmount = (int) $data['amount_minor'];
            } elseif (array_key_exists('amount', $data)) {
                $newAmount = DictionaryResolver::rublesToMinor($data['amount']);
            }

            if ($newAmount !== null && $newAmount !== (int) $advance->amount_minor) {
                if ($wasReceived && $this->ledger->hasIncomeForAdvance($advance->id)) {
                    $delta = $newAmount - (int) $advance->amount_minor;
                    $advance->amount_minor = $newAmount;
                    $advance->save();
                    if ($delta !== 0) {
                        $this->ledger->apply($advance->user, WalletTransaction::TYPE_INCOME, WalletTransaction::ACCOUNT_ADVANCE, $delta, [
                            'advance_id' => $advance->id,
                            'meta' => ['reason' => 'amount_change'],
                        ]);
                    }
                } else {
                    $advance->amount_minor = $newAmount;
                }
            }

            $advance->save();
            $advance->load('status');

            if ($previousSlug !== 'received' && $advance->status?->slug === 'received') {
                $this->markReceived($advance);
            }

            $taskIds = $this->normalizeTaskIds($data, $advance->user);
            if ($taskIds !== null) {
                $advance->tasks()->sync($taskIds);
            }

            return $advance->fresh(['status', 'disbursementMethod', 'tasks', 'expenses.receipts', 'expenses.article', 'expenses.supplier']);
        });
    }

    public function markReceived(Advance $advance): void
    {
        DB::transaction(function () use ($advance) {
            $advance = Advance::whereKey($advance->id)->lockForUpdate()->firstOrFail();

            if ($this->ledger->hasIncomeForAdvance($advance->id)) {
                return;
            }

            if ((int) $advance->amount_minor <= 0) {
                throw new InvalidArgumentException('Сумма должна быть больше нуля');
            }
            if (! $advance->disbursement_method_id) {
                throw new InvalidArgumentException('Укажите способ выдачи');
            }

            $methodSlug = $advance->disbursementMethod?->slug
                ?? \App\Models\DisbursementMethod::query()->whereKey($advance->disbursement_method_id)->value('slug');

            $this->ledger->apply(
                $advance->user,
                WalletTransaction::TYPE_INCOME,
                WalletTransaction::ACCOUNT_ADVANCE,
                (int) $advance->amount_minor,
                [
                    'advance_id' => $advance->id,
                    'meta' => ['disbursement_method_id' => $methodSlug],
                ]
            );

            $advance->status_id = DictionaryResolver::advanceStatusId('received');
            $advance->issued_at = $advance->issued_at ?? now();
            $advance->closed_at = null;
            $advance->save();
        });
    }

    public function spentMinor(Advance $advance): int
    {
        return (int) $advance->expenses()
            ->where('debit_account', WalletTransaction::ACCOUNT_ADVANCE)
            ->sum('amount_minor');
    }

    /** Остаток на счёте аванса по леджеру. */
    public function remainingMinor(Advance $advance): int
    {
        return $this->ledger->advanceBalanceMinor($advance->id);
    }

    public function closeToWallet(Advance $advance): void
    {
        DB::transaction(function () use ($advance) {
            $advance = Advance::whereKey($advance->id)->lockForUpdate()->with('status')->firstOrFail();
            $this->assertOpenForClose($advance);

            $rem = $this->remainingMinor($advance);
            if ($rem <= 0) {
                $this->markClosed($advance);

                return;
            }

            $this->ledger->apply($advance->user, WalletTransaction::TYPE_TRANSFER, WalletTransaction::ACCOUNT_ADVANCE, -$rem, [
                'advance_id' => $advance->id,
                'meta' => ['kind' => 'close_to_wallet', 'direction' => 'from'],
            ]);
            $this->ledger->apply($advance->user, WalletTransaction::TYPE_TRANSFER, WalletTransaction::ACCOUNT_WALLET, $rem, [
                'advance_id' => $advance->id,
                'meta' => ['kind' => 'close_to_wallet', 'direction' => 'to'],
            ]);

            $this->markClosed($advance);
        });
    }

    public function closeWriteOff(Advance $advance): void
    {
        DB::transaction(function () use ($advance) {
            $advance = Advance::whereKey($advance->id)->lockForUpdate()->with('status')->firstOrFail();
            $this->assertOpenForClose($advance);

            $rem = $this->remainingMinor($advance);
            if ($rem <= 0) {
                $this->markClosed($advance);

                return;
            }

            $this->ledger->apply($advance->user, WalletTransaction::TYPE_EXPENSE, WalletTransaction::ACCOUNT_ADVANCE, -$rem, [
                'advance_id' => $advance->id,
                'meta' => ['kind' => 'close_writeoff'],
            ]);

            $this->markClosed($advance);
        });
    }

    public function maybeAutoClose(Advance $advance): void
    {
        $advance->refresh();
        $advance->load('status');

        if (! in_array($advance->status?->slug, ['received', 'reporting'], true)) {
            return;
        }

        if ($this->remainingMinor($advance) === 0) {
            $this->markClosed($advance);
        }
    }

    public function markReportingIfNeeded(Advance $advance): void
    {
        $advance->loadMissing('status');
        if ($advance->status?->slug === 'received') {
            $advance->status_id = DictionaryResolver::advanceStatusId('reporting');
            $advance->save();
        }
    }

    public function attachExpense(Advance $advance, \App\Models\Expense $expense, ?string $debitAccount = null): \App\Models\Expense
    {
        return app(ExpenseService::class)->attachToAdvance($advance, $expense, $debitAccount);
    }

    public function detachExpense(Advance $advance, \App\Models\Expense $expense): \App\Models\Expense
    {
        return app(ExpenseService::class)->detachFromAdvance($advance, $expense);
    }

    public function destroy(Advance $advance): void
    {
        DB::transaction(function () use ($advance) {
            $advance = Advance::whereKey($advance->id)->lockForUpdate()->with(['expenses.receipts', 'user'])->firstOrFail();
            $owner = $advance->user;
            $wallet = \App\Models\Wallet::query()->firstOrCreate(
                ['user_id' => $owner->id],
                ['balance_minor' => 0, 'currency' => 'RUB'],
            );
            $wallet = \App\Models\Wallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();

            $expenseIds = $advance->expenses->pluck('id')->all();

            $txs = WalletTransaction::query()
                ->where(function ($q) use ($advance, $expenseIds) {
                    $q->where('advance_id', $advance->id);
                    if ($expenseIds !== []) {
                        $q->orWhereIn('expense_id', $expenseIds);
                    }
                })
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

            foreach ($advance->expenses as $expense) {
                foreach ($expense->receipts as $receipt) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($receipt->path);
                    $receipt->delete();
                }
            }

            WalletTransaction::query()
                ->where(function ($q) use ($advance, $expenseIds) {
                    $q->where('advance_id', $advance->id);
                    if ($expenseIds !== []) {
                        $q->orWhereIn('expense_id', $expenseIds);
                    }
                })
                ->delete();

            \App\Models\Expense::query()->where('advance_id', $advance->id)->delete();
            $advance->tasks()->detach();
            $advance->delete();
        });
    }

    protected function markClosed(Advance $advance): void
    {
        $advance->status_id = DictionaryResolver::advanceStatusId('closed');
        $advance->closed_at = now();
        $advance->save();
    }

    protected function assertOpenForClose(Advance $advance): void
    {
        $slug = $advance->status?->slug;
        if (! in_array($slug, ['received', 'reporting'], true)) {
            throw new InvalidArgumentException('Закрытие доступно только для полученных авансов');
        }
        if (! $this->ledger->hasIncomeForAdvance($advance->id)) {
            throw new InvalidArgumentException('Аванс ещё не получен');
        }
    }

    protected function assertTransition(?string $from, string $to): void
    {
        if ($from === null || $from === $to) {
            return;
        }

        $allowed = self::TRANSITIONS[$from] ?? [];
        if (! in_array($to, $allowed, true)) {
            throw new InvalidArgumentException("Нельзя перейти из «{$from}» в «{$to}»");
        }
    }

    protected function normalizeStatusSlug(?string $slug): ?string
    {
        if ($slug === null) {
            return null;
        }

        return match ($slug) {
            'issued' => 'received',
            'approved' => 'pending',
            default => $slug,
        };
    }

    /**
     * @return list<int>|null
     */
    protected function normalizeTaskIds(array $data, User $user): ?array
    {
        if (array_key_exists('task_ids', $data)) {
            $ids = collect($data['task_ids'] ?? [])->filter()->map(fn ($id) => (int) $id)->unique()->values();
        } elseif (array_key_exists('task_id', $data)) {
            $ids = collect($data['task_id'] !== null && $data['task_id'] !== '' ? [(int) $data['task_id']] : []);
        } else {
            return null;
        }

        if ($ids->isEmpty()) {
            return [];
        }

        $owned = ($user->is_admin
            ? Task::query()
            : $user->tasks()
        )->whereIn('id', $ids)->pluck('id')->all();
        if (count($owned) !== $ids->count()) {
            throw new InvalidArgumentException('Поручение не найдено');
        }

        return $ids->all();
    }
}
