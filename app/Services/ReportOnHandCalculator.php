<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportOnHandCalculator
{
    /**
     * Reconstruct «на руках» as of end of $asOfDay (inclusive).
     *
     * @return array{
     *     on_hand_minor: int,
     *     wallet_minor: int,
     *     in_advances_minor: int,
     *     unassigned_minor: int
     * }
     */
    public function asOf(User $user, Carbon $asOfDay): array
    {
        $wallet = Wallet::query()->where('user_id', $user->id)->first();
        if (! $wallet) {
            return [
                'on_hand_minor' => 0,
                'wallet_minor' => 0,
                'in_advances_minor' => 0,
                'unassigned_minor' => 0,
            ];
        }

        $until = $asOfDay->copy()->endOfDay();

        $walletMinor = (int) WalletTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->where('account', WalletTransaction::ACCOUNT_WALLET)
            ->where('occurred_at', '<=', $until)
            ->sum('amount_minor');

        $advanceRows = WalletTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->where('account', WalletTransaction::ACCOUNT_ADVANCE)
            ->where('occurred_at', '<=', $until)
            ->whereNotNull('advance_id')
            ->select('advance_id', DB::raw('SUM(amount_minor) as remaining_minor'))
            ->groupBy('advance_id')
            ->get();

        $inAdvancesMinor = 0;
        foreach ($advanceRows as $row) {
            $remaining = (int) $row->remaining_minor;
            if ($remaining > 0) {
                $inAdvancesMinor += $remaining;
            }
        }

        $unassignedMinor = (int) abs((int) WalletTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->where('account', WalletTransaction::ACCOUNT_UNASSIGNED)
            ->where('occurred_at', '<=', $until)
            ->sum('amount_minor'));

        return [
            'on_hand_minor' => $walletMinor + $inAdvancesMinor - $unassignedMinor,
            'wallet_minor' => $walletMinor,
            'in_advances_minor' => $inAdvancesMinor,
            'unassigned_minor' => $unassignedMinor,
        ];
    }

    /**
     * Opening balance: state before period_from (exclusive of period_from day).
     *
     * @return array{
     *     on_hand_minor: int,
     *     wallet_minor: int,
     *     in_advances_minor: int,
     *     unassigned_minor: int
     * }
     */
    public function opening(User $user, Carbon $periodFrom): array
    {
        $dayBefore = $periodFrom->copy()->subDay()->startOfDay();

        return $this->asOf($user, $dayBefore);
    }

    /**
     * Closing balance: state at end of period_to (inclusive).
     *
     * @return array{
     *     on_hand_minor: int,
     *     wallet_minor: int,
     *     in_advances_minor: int,
     *     unassigned_minor: int
     * }
     */
    public function closing(User $user, Carbon $periodTo): array
    {
        return $this->asOf($user, $periodTo->copy()->startOfDay());
    }
}
