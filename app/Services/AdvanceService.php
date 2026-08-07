<?php

namespace App\Services;

use App\Enums\AdvanceStatus;
use App\Models\Advance;
use App\Models\Task;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Support\DictionaryResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class AdvanceService
{
    public function __construct(protected FinanceLedger $ledger) {}

    public function create(User $user, array $data): Advance
    {
        $amountMinor = array_key_exists('amount_minor', $data)
            ? (int) $data['amount_minor']
            : DictionaryResolver::rublesToMinor($data['amount'] ?? 0);

        $methodId = null;
        if (! empty($data['disbursement_method_id'])) {
            $methodId = DictionaryResolver::disbursementMethodId($data['disbursement_method_id']);
        }

        $advance = Advance::create([
            'user_id' => $user->id,
            'status' => AdvanceStatus::Pending,
            'disbursement_method_id' => $methodId,
            'title' => array_key_exists('title', $data) ? (string) ($data['title'] ?? '') : '',
            'amount_minor' => $amountMinor,
            'note' => $data['note'] ?? null,
            'needed_at' => $this->normalizeDate($data['needed_at'] ?? null, 'нужна к'),
        ]);

        $taskIds = $this->normalizeTaskIds($data, $user);
        if ($taskIds !== null) {
            $advance->tasks()->sync($taskIds);
        }

        return $this->freshAdvance($advance);
    }

    public function update(Advance $advance, array $data): Advance
    {
        return DB::transaction(function () use ($advance, $data) {
            $advance = Advance::whereKey($advance->id)->lockForUpdate()->firstOrFail();
            $status = $advance->statusEnum();
            $wasFunded = $status->isFunded() || $this->ledger->hasIncomeForAdvance($advance->id);

            if (array_key_exists('status_id', $data) || array_key_exists('status', $data)) {
                throw new InvalidArgumentException('Статус меняется только кнопками «Утвердили» / «Получены» / закрытием');
            }

            if (array_key_exists('disbursement_method_id', $data)) {
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

            if (array_key_exists('needed_at', $data)) {
                $advance->needed_at = $this->normalizeDate($data['needed_at'], 'нужна к');
            }

            $newAmount = null;
            if (array_key_exists('amount_minor', $data)) {
                $newAmount = (int) $data['amount_minor'];
            } elseif (array_key_exists('amount', $data)) {
                $newAmount = DictionaryResolver::rublesToMinor($data['amount']);
            }

            if ($newAmount !== null && $newAmount !== (int) $advance->amount_minor) {
                if ($wasFunded && $this->ledger->hasIncomeForAdvance($advance->id)) {
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

            $taskIds = $this->normalizeTaskIds($data, $advance->user);
            if ($taskIds !== null) {
                $advance->tasks()->sync($taskIds);
            }

            return $this->freshAdvance($advance);
        });
    }

    public function approve(Advance $advance): Advance
    {
        return DB::transaction(function () use ($advance) {
            $advance = Advance::whereKey($advance->id)->lockForUpdate()->firstOrFail();
            if ($advance->statusEnum() !== AdvanceStatus::Pending) {
                throw new InvalidArgumentException('Утвердить можно только заявку');
            }
            $advance->status = AdvanceStatus::Approved;
            $advance->save();

            return $this->freshAdvance($advance);
        });
    }

    /**
     * Receive money: only from approved. Posts income and moves to reporting.
     *
     * @param  array{issued_at?: mixed, disbursement_method_id?: mixed, amount?: mixed, amount_minor?: int|null}  $data
     */
    public function receive(Advance $advance, array $data = []): Advance
    {
        return DB::transaction(function () use ($advance, $data) {
            $advance = Advance::whereKey($advance->id)->lockForUpdate()->with(['disbursementMethod', 'user'])->firstOrFail();

            if ($this->ledger->hasIncomeForAdvance($advance->id)) {
                if ($advance->statusEnum() !== AdvanceStatus::Reporting && $advance->statusEnum() !== AdvanceStatus::Closed) {
                    $advance->status = AdvanceStatus::Reporting;
                    $advance->save();
                }

                return $this->freshAdvance($advance);
            }

            if ($advance->statusEnum() !== AdvanceStatus::Approved) {
                throw new InvalidArgumentException('Сначала утвердите заявку');
            }

            if (array_key_exists('amount_minor', $data) && $data['amount_minor'] !== null) {
                $advance->amount_minor = (int) $data['amount_minor'];
            } elseif (array_key_exists('amount', $data) && $data['amount'] !== null && $data['amount'] !== '') {
                $advance->amount_minor = DictionaryResolver::rublesToMinor($data['amount']);
            }

            if (array_key_exists('disbursement_method_id', $data) && $data['disbursement_method_id']) {
                $advance->disbursement_method_id = DictionaryResolver::disbursementMethodId($data['disbursement_method_id']);
                $advance->load('disbursementMethod');
            }

            if ((int) $advance->amount_minor <= 0) {
                throw new InvalidArgumentException('Перед получением укажите сумму больше нуля');
            }
            if (! $advance->disbursement_method_id) {
                throw new InvalidArgumentException('Укажите способ выдачи');
            }

            $issuedAt = $this->normalizeDate(
                $data['issued_at'] ?? $advance->issued_at ?? $advance->needed_at ?? now(),
                'получения'
            );

            $methodSlug = $advance->disbursementMethod?->slug
                ?? \App\Models\DisbursementMethod::query()->whereKey($advance->disbursement_method_id)->value('slug');

            $this->ledger->apply(
                $advance->user,
                WalletTransaction::TYPE_INCOME,
                WalletTransaction::ACCOUNT_ADVANCE,
                (int) $advance->amount_minor,
                [
                    'advance_id' => $advance->id,
                    'occurred_at' => $issuedAt,
                    'meta' => ['disbursement_method_id' => $methodSlug],
                ]
            );

            $advance->status = AdvanceStatus::Reporting;
            $advance->issued_at = $issuedAt;
            $advance->closed_at = null;
            $advance->save();

            return $this->freshAdvance($advance);
        });
    }

    public function spentMinor(Advance $advance): int
    {
        return (int) $advance->expenses()
            ->where('debit_account', WalletTransaction::ACCOUNT_ADVANCE)
            ->sum('amount_minor');
    }

    public function remainingMinor(Advance $advance): int
    {
        return $this->ledger->advanceBalanceMinor($advance->id);
    }

    public function closeToWallet(Advance $advance): void
    {
        DB::transaction(function () use ($advance) {
            $advance = Advance::whereKey($advance->id)->lockForUpdate()->firstOrFail();
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

    public function maybeAutoClose(Advance $advance): void
    {
        $advance->refresh();

        if ($advance->statusEnum() !== AdvanceStatus::Reporting) {
            return;
        }

        if ($this->remainingMinor($advance) === 0) {
            $this->markClosed($advance);
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
            $wallet = Wallet::query()->firstOrCreate(
                ['user_id' => $owner->id],
                ['balance_minor' => 0, 'currency' => 'RUB'],
            );
            $wallet = Wallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();

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
                    Storage::disk('public')->delete($receipt->path);
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
        $advance->status = AdvanceStatus::Closed;
        $advance->closed_at = now();
        $advance->save();
    }

    protected function assertOpenForClose(Advance $advance): void
    {
        if (! $advance->statusEnum()->allowsClose()) {
            throw new InvalidArgumentException('Закрытие доступно только для авансов на отчёте');
        }
        if (! $this->ledger->hasIncomeForAdvance($advance->id)) {
            throw new InvalidArgumentException('Аванс ещё не получен');
        }
    }

    protected function normalizeDate(mixed $value, string $label = 'даты'): ?\Carbon\CarbonInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            throw new InvalidArgumentException('Некорректная дата '.$label);
        }
    }

    protected function freshAdvance(Advance $advance): Advance
    {
        return $advance->fresh(['disbursementMethod', 'tasks', 'expenses.receipts', 'expenses.article', 'expenses.supplier']);
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
            : Task::query()->where('user_id', $user->id)
        )->whereIn('id', $ids->all())->pluck('id');

        if ($owned->count() !== $ids->count()) {
            throw new InvalidArgumentException('Поручение не найдено');
        }

        return $ids->all();
    }
}
