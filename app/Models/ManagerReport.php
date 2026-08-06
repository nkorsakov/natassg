<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManagerReport extends Model
{
    /** Soft public PIN — source of truth in config/skydesk.php */
    public static function acceptPin(): string
    {
        return (string) config('skydesk.public_pin', '4608');
    }

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    /** @deprecated use acceptPin() */
    public const ACCEPT_PIN = '4608';

    protected $fillable = [
        'token',
        'user_id',
        'created_by',
        'period_from',
        'period_to',
        'payload',
        'views_count',
        'status',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'payload' => 'array',
            'accepted_at' => 'datetime',
            'views_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publicUrl(): string
    {
        return url('/r/'.$this->token);
    }

    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function recordView(): void
    {
        static::query()->whereKey($this->id)->increment('views_count');
        $this->views_count = (int) $this->views_count + 1;
    }

    public function acceptWithPin(string $pin): bool
    {
        if ($this->isAccepted()) {
            return true;
        }

        if ($pin !== self::acceptPin()) {
            return false;
        }

        $this->forceFill([
            'status' => self::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ])->save();

        return true;
    }
}
