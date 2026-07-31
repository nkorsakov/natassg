<?php

namespace App\Services;

use App\Models\Advance;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Support\DictionaryResolver;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AdvanceService
{
    public function create(User $user, array $data): Advance
    {
        $statusId = DictionaryResolver::advanceStatusId($data['status_id'] ?? 'pending');
        $amountMinor = array_key_exists('amount_minor', $data)
            ? (int) $data['amount_minor']
            : DictionaryResolver::rublesToMinor($data['amount'] ?? 0);

        return Advance::create([
            'user_id' => $user->id,
            'task_id' => $data['task_id'] ?? null,
            'status_id' => $statusId,
            'title' => $data['title'] ?? 'Заявка на аванс',
            'amount_minor' => $amountMinor,
            'note' => $data['note'] ?? null,
        ])->fresh(['status', 'task', 'expenses.receipts']);
    }

    public function update(Advance $advance, array $data): Advance
    {
        $previousSlug = $advance->status?->slug;

        if (isset($data['status_id'])) {
            $advance->status_id = DictionaryResolver::advanceStatusId($data['status_id']);
        }
        if (array_key_exists('task_id', $data)) {
            $advance->task_id = $data['task_id'];
        }
        if (isset($data['title'])) {
            $advance->title = $data['title'];
        }
        if (array_key_exists('amount_minor', $data)) {
            $advance->amount_minor = (int) $data['amount_minor'];
        } elseif (array_key_exists('amount', $data)) {
            $advance->amount_minor = DictionaryResolver::rublesToMinor($data['amount']);
        }
        if (array_key_exists('note', $data)) {
            $advance->note = $data['note'];
        }

        $advance->save();
        $advance->load('status');

        if ($previousSlug !== 'issued' && $advance->status?->slug === 'issued') {
            $this->creditIssue($advance);
        }

        return $advance->fresh(['status', 'task', 'expenses.receipts']);
    }

    public function creditIssue(Advance $advance): void
    {
        DB::transaction(function () use ($advance) {
            $wallet = Wallet::where('user_id', $advance->user_id)->lockForUpdate()->firstOrFail();

            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => 'issue',
                'amount_minor' => $advance->amount_minor,
                'advance_id' => $advance->id,
            ]);

            $wallet->increment('balance_minor', $advance->amount_minor);
            $advance->issued_at = now();
            $advance->save();
        });
    }

    public function spentMinor(Advance $advance): int
    {
        return (int) $advance->expenses()->sum('amount_minor');
    }

    public function remainingMinor(Advance $advance): int
    {
        return (int) $advance->amount_minor - $this->spentMinor($advance);
    }

    public function returnRemainder(Advance $advance): void
    {
        $rem = $this->remainingMinor($advance);
        if ($rem <= 0) {
            throw new InvalidArgumentException('Нет остатка для возврата');
        }

        $this->ledger($advance, 'return', $rem);
        $this->markClosed($advance);
    }

    public function zeroAsUnknown(Advance $advance): void
    {
        $rem = $this->remainingMinor($advance);
        if ($rem <= 0) {
            throw new InvalidArgumentException('Нет остатка для обнуления');
        }

        $this->ledger($advance, 'zero_unknown', -$rem);
        $this->markClosed($advance);
    }

    public function recordOverspend(Advance $advance): void
    {
        $rem = $this->remainingMinor($advance);
        if ($rem >= 0) {
            throw new InvalidArgumentException('Нет перерасхода');
        }

        $this->ledger($advance, 'overspend', $rem);
        $this->markClosed($advance);
    }

    public function settle(Advance $advance): void
    {
        $rem = $this->remainingMinor($advance);
        if ($rem > 0) {
            $this->returnRemainder($advance);
        } elseif ($rem < 0) {
            $this->recordOverspend($advance);
        } else {
            $this->markClosed($advance);
        }
    }

    protected function ledger(Advance $advance, string $type, int $amountMinor): void
    {
        DB::transaction(function () use ($advance, $type, $amountMinor) {
            $wallet = Wallet::where('user_id', $advance->user_id)->lockForUpdate()->firstOrFail();

            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => $type,
                'amount_minor' => $amountMinor,
                'advance_id' => $advance->id,
            ]);

            $wallet->increment('balance_minor', $amountMinor);
        });
    }

    protected function markClosed(Advance $advance): void
    {
        $advance->status_id = DictionaryResolver::advanceStatusId('closed');
        $advance->closed_at = now();
        $advance->save();
    }
}
