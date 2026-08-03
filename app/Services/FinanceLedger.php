<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class FinanceLedger
{
    /**
     * @param  array{advance_id?: int|null, expense_id?: int|null, meta?: array|null, account?: string}  $links
     */
    public function apply(
        User $user,
        string $type,
        string $account,
        int $amountMinor,
        array $links = [],
    ): WalletTransaction {
        return DB::transaction(function () use ($user, $type, $account, $amountMinor, $links) {
            $wallet = Wallet::query()->firstOrCreate(
                ['user_id' => $user->id],
                ['balance_minor' => 0, 'currency' => 'RUB'],
            );
            $wallet = Wallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();

            $tx = WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => $type,
                'account' => $account,
                'amount_minor' => $amountMinor,
                'advance_id' => $links['advance_id'] ?? null,
                'expense_id' => $links['expense_id'] ?? null,
                'meta' => $links['meta'] ?? null,
            ]);

            // Only wallet-account movements change stored wallet balance.
            if ($account === WalletTransaction::ACCOUNT_WALLET && $amountMinor !== 0) {
                $wallet->increment('balance_minor', $amountMinor);
            }

            return $tx;
        });
    }

    public function hasIncomeForAdvance(int $advanceId): bool
    {
        return WalletTransaction::query()
            ->where('advance_id', $advanceId)
            ->where('type', WalletTransaction::TYPE_INCOME)
            ->where('account', WalletTransaction::ACCOUNT_ADVANCE)
            ->exists();
    }

    public function advanceBalanceMinor(int $advanceId): int
    {
        return (int) WalletTransaction::query()
            ->where('advance_id', $advanceId)
            ->where('account', WalletTransaction::ACCOUNT_ADVANCE)
            ->sum('amount_minor');
    }
}
