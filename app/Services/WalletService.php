<?php

namespace App\Services;

use App\Models\DisbursementMethod;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Support\DictionaryResolver;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WalletService
{
    public function __construct(protected FinanceLedger $ledger) {}

    public function topUp(User $user, array $data): WalletTransaction
    {
        $amountMinor = array_key_exists('amount_minor', $data)
            ? (int) $data['amount_minor']
            : DictionaryResolver::rublesToMinor($data['amount'] ?? 0);

        if ($amountMinor <= 0) {
            throw new InvalidArgumentException('Сумма прихода должна быть больше нуля');
        }

        $methodId = DictionaryResolver::disbursementMethodId($data['disbursement_method_id'] ?? null);
        if (! $methodId) {
            throw new InvalidArgumentException('Укажите способ получения денег');
        }

        $method = DisbursementMethod::query()->find($methodId);

        return $this->ledger->apply(
            $user,
            WalletTransaction::TYPE_INCOME,
            WalletTransaction::ACCOUNT_WALLET,
            $amountMinor,
            [
                'meta' => [
                    'note' => $data['note'] ?? null,
                    'disbursement_method_id' => $method?->slug,
                    'title' => $data['title'] ?? null,
                    'kind' => 'wallet_income',
                ],
            ]
        );
    }

    public function updateTopUp(User $user, WalletTransaction $tx, array $data): WalletTransaction
    {
        return DB::transaction(function () use ($user, $tx, $data) {
            $tx = WalletTransaction::whereKey($tx->id)->lockForUpdate()->firstOrFail();
            $wallet = Wallet::whereKey($tx->wallet_id)->lockForUpdate()->firstOrFail();

            if (! $user->canAccessOwned($wallet->user_id)) {
                throw new InvalidArgumentException('Чужая операция');
            }
            if ($tx->type !== WalletTransaction::TYPE_INCOME || $tx->account !== WalletTransaction::ACCOUNT_WALLET) {
                throw new InvalidArgumentException('Можно править только приход на кошелёк');
            }

            $newAmount = array_key_exists('amount_minor', $data)
                ? (int) $data['amount_minor']
                : (array_key_exists('amount', $data)
                    ? DictionaryResolver::rublesToMinor($data['amount'])
                    : (int) $tx->amount_minor);

            if ($newAmount <= 0) {
                throw new InvalidArgumentException('Сумма прихода должна быть больше нуля');
            }

            $meta = is_array($tx->meta) ? $tx->meta : [];
            if (array_key_exists('note', $data)) {
                $meta['note'] = $data['note'];
            }
            if (array_key_exists('title', $data)) {
                $meta['title'] = $data['title'];
            }
            if (array_key_exists('disbursement_method_id', $data)) {
                $methodId = DictionaryResolver::disbursementMethodId($data['disbursement_method_id']);
                if (! $methodId) {
                    throw new InvalidArgumentException('Укажите способ получения денег');
                }
                $meta['disbursement_method_id'] = DisbursementMethod::query()->whereKey($methodId)->value('slug');
            }
            if (empty($meta['disbursement_method_id'])) {
                throw new InvalidArgumentException('Укажите способ получения денег');
            }
            $meta['kind'] = $meta['kind'] ?? 'wallet_income';

            $delta = $newAmount - (int) $tx->amount_minor;
            $tx->amount_minor = $newAmount;
            $tx->meta = $meta;
            $tx->save();

            if ($delta !== 0) {
                $wallet->increment('balance_minor', $delta);
            }

            return $tx->fresh();
        });
    }
}
