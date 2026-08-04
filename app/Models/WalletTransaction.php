<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    public const TYPE_INCOME = 'income';

    public const TYPE_EXPENSE = 'expense';

    public const TYPE_TRANSFER = 'transfer';

    public const ACCOUNT_WALLET = 'wallet';

    public const ACCOUNT_ADVANCE = 'advance';

    public const ACCOUNT_UNASSIGNED = 'unassigned';

    protected $fillable = [
        'wallet_id',
        'type',
        'account',
        'amount_minor',
        'advance_id',
        'expense_id',
        'meta',
        'occurred_at',
        'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'is_demo' => 'boolean',
            'occurred_at' => 'datetime',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function advance(): BelongsTo
    {
        return $this->belongsTo(Advance::class);
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }
}
