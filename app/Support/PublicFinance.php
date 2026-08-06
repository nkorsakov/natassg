<?php

namespace App\Support;

use App\Models\Advance;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;

class PublicFinance
{
    public const SESSION_UNLOCKED = 'public_finance_unlocked';

    public static function pin(): string
    {
        return (string) config('skydesk.public_pin', '4608');
    }

    public static function pinMatches(string $pin): bool
    {
        return hash_equals(self::pin(), $pin);
    }

    public static function subject(): ?User
    {
        $id = config('skydesk.public_finance_user_id');
        if ($id) {
            return User::query()->find((int) $id);
        }

        $email = (string) config('skydesk.public_finance_user_email', '');
        if ($email !== '') {
            $byEmail = User::query()->where('email', $email)->first();
            if ($byEmail) {
                return $byEmail;
            }
        }

        return User::query()
            ->where('is_admin', false)
            ->orderBy('id')
            ->first();
    }

    /**
     * Live read-only finance payload for the public cashflow page.
     *
     * @return array<string, mixed>
     */
    public static function payload(User $user): array
    {
        $advances = Advance::query()
            ->with(['status', 'disbursementMethod'])
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->get();

        $wallet = Wallet::query()->where('user_id', $user->id)->first();
        if ($wallet) {
            $wallet->load([
                'transactions' => fn ($q) => $q
                    ->with(['advance', 'expense.article'])
                    ->orderByDesc('occurred_at')
                    ->orderByDesc('id')
                    ->limit(80),
            ]);
        }

        $openAdvances = $advances
            ->filter(fn (Advance $a) => in_array($a->status?->slug, ['pending', 'received', 'reporting'], true))
            ->values()
            ->map(function (Advance $a) {
                $presented = SkyDeskPresenter::advance($a);

                return [
                    'id' => $a->id,
                    'title' => $a->title,
                    'status_id' => $a->status?->slug,
                    'status_label' => $a->status?->label,
                    'status_color' => $a->status?->color,
                    'amount' => $presented['amount'] ?? 0,
                    'remaining' => $presented['remaining'] ?? 0,
                ];
            })
            ->all();

        $walletPayload = SkyDeskPresenter::wallet(
            $wallet,
            $advances->map(fn (Advance $a) => SkyDeskPresenter::advance($a))->values(),
        );

        return [
            'subject' => [
                'id' => $user->id,
                'name' => $user->name,
                'initials' => $user->initials ?: mb_strtoupper(mb_substr($user->name, 0, 2)),
                'role' => $user->role_title,
            ],
            'wallet' => [
                'on_hand' => $walletPayload['on_hand'] ?? 0,
                'wallet' => $walletPayload['wallet'] ?? 0,
                'in_advances' => $walletPayload['in_advances'] ?? 0,
                'unassigned' => $walletPayload['unassigned'] ?? 0,
                'currency' => $walletPayload['currency'] ?? 'RUB',
            ],
            'advances' => $openAdvances,
            'transactions' => $walletPayload['transactions'] ?? [],
            'refreshed_at' => now()->toIso8601String(),
        ];
    }
}
