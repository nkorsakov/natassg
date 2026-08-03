<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'initials',
        'role_title',
        'email',
        'password',
        'is_admin',
        'telegram_id',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'telegram_id' => 'integer',
        ];
    }

    public function routeNotificationForTelegram(): ?int
    {
        return $this->telegram_id ? (int) $this->telegram_id : null;
    }

    public function canAccessOwned(?int $ownerId): bool
    {
        if ($this->is_admin) {
            return true;
        }

        return $ownerId !== null && (int) $ownerId === (int) $this->id;
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    protected static function booted(): void
    {
        static::created(function (User $user) {
            $user->wallet()->create([
                'balance_minor' => 0,
                'currency' => 'RUB',
            ]);
        });
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }

    public function advances(): HasMany
    {
        return $this->hasMany(Advance::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }
}
