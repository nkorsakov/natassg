<?php

namespace App\Models;

use App\Enums\AdvanceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Advance extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'disbursement_method_id',
        'title',
        'amount_minor',
        'note',
        'needed_at',
        'issued_at',
        'closed_at',
        'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'status' => AdvanceStatus::class,
            'needed_at' => 'date',
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

    public function statusEnum(): AdvanceStatus
    {
        $status = $this->status;
        if ($status instanceof AdvanceStatus) {
            return $status;
        }

        return AdvanceStatus::fromSlug(is_string($status) ? $status : null);
    }
}
