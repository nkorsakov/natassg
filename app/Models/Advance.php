<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Advance extends Model
{
    protected $fillable = [
        'user_id',
        'status_id',
        'disbursement_method_id',
        'title',
        'amount_minor',
        'note',
        'issued_at',
        'closed_at',
        'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'closed_at' => 'datetime',
            'is_demo' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'advance_task')
            ->withTimestamps();
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(AdvanceStatus::class, 'status_id');
    }

    public function disbursementMethod(): BelongsTo
    {
        return $this->belongsTo(DisbursementMethod::class, 'disbursement_method_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }
}
