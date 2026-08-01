<?php

namespace App\Console\Commands;

use App\Jobs\SendTelegramNotificationJob;
use App\Models\User;
use Illuminate\Console\Command;

class TelegramTestCommand extends Command
{
    protected $signature = 'telegram:test
                            {email? : Email пользователя (по умолчанию админ)}
                            {--queue : Положить Job в очередь вместо sync}';

    protected $description = 'Отправить тестовое Telegram-уведомление';

    public function handle(): int
    {
        $token = config('services.telegram-bot-api.token') ?: config('services.telegram.bot_token');

        if (! $token) {
            $this->error('TELEGRAM_BOT_TOKEN не задан в .env');

            return self::FAILURE;
        }

        $email = $this->argument('email');

        $user = $email
            ? User::query()->where('email', $email)->first()
            : User::query()->where('is_admin', true)->orderBy('id')->first()
                ?? User::query()->orderBy('id')->first();

        if (! $user) {
            $this->error('Пользователь не найден');

            return self::FAILURE;
        }

        if (! $user->telegram_id) {
            $this->error("У {$user->email} нет telegram_id");

            return self::FAILURE;
        }

        $tz = config('notifications.timezone', config('app.timezone'));
        $message = 'SkyDesk: тестовое уведомление · '.now()->timezone($tz)->format('d.m.Y H:i');

        if ($this->option('queue')) {
            SendTelegramNotificationJob::dispatch($user, $message);
            $this->info("В очередь для {$user->email} (telegram_id={$user->telegram_id})");
        } else {
            SendTelegramNotificationJob::dispatchSync($user, $message);
            $this->info("Отправлено {$user->email} (telegram_id={$user->telegram_id})");
        }

        return self::SUCCESS;
    }
}
