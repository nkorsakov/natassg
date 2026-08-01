<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\TelegramTextNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendTelegramNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $message,
    ) {
    }

    public function handle(): void
    {
        if (! $this->user->telegram_id) {
            return;
        }

        $this->user->notify(new TelegramTextNotification($this->message));
    }
}
