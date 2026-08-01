<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    public const TYPE_TOPUP = 'topup';

    public const TYPE_ISSUE = 'issue';

    public const TYPE_EXPENSE = 'expense';

    public const TYPE_RETURN = 'return';

    public const TYPE_WRITEOFF = 'writeoff';

    public const TYPE_AMOUNT_ADJUST = 'amount_adjust';

    public const TYPE_RELEASE = 'release';

    protected $fillable = [
        'wallet_id',
        'type',
        'amount_minor',
        'advance_id',
        'expense_id',
        'meta',
        'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'is_demo' => 'boolean',
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
