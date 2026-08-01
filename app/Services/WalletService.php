<?php

namespace App\Services;

use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\DictionaryResolver;
use InvalidArgumentException;

class WalletService
{
    public function __construct(protected WalletLedger $ledger) {}

    public function topUp(User $user, array $data): WalletTransaction
    {
        $amountMinor = array_key_exists('amount_minor', $data)
            ? (int) $data['amount_minor']
            : DictionaryResolver::rublesToMinor($data['amount'] ?? 0);

        if ($amountMinor <= 0) {
            throw new InvalidArgumentException('Сумма пополнения должна быть больше нуля');
        }

        return $this->ledger->apply($user, WalletTransaction::TYPE_TOPUP, $amountMinor, [
            'meta' => [
                'note' => $data['note'] ?? null,
            ],
        ]);
    }
}
