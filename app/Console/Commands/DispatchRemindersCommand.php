<?php

namespace App\Console\Commands;

use App\Jobs\SendTelegramNotificationJob;
use App\Models\Reminder;
use App\Services\ReminderService;
use Illuminate\Console\Command;

class DispatchRemindersCommand extends Command
{
    protected $signature = 'reminders:dispatch';

    protected $description = 'Отправить наступившие напоминания в Telegram';

    public function handle(ReminderService $reminders): int
    {
        $due = Reminder::query()
            ->due()
            ->with(['user', 'task'])
            ->orderBy('remind_at')
            ->limit(100)
            ->get();

        $sent = 0;

        foreach ($due as $reminder) {
            $user = $reminder->user;

            if (! $user?->telegram_id) {
                $reminder->update(['sent_at' => now()]);
                continue;
            }

            SendTelegramNotificationJob::dispatchSync(
                $user,
                $reminders->resolveMessage($reminder),
            );

            $reminder->update(['sent_at' => now()]);
            $sent++;
        }

        $this->info("Отправлено: {$sent}");

        return self::SUCCESS;
    }
}
