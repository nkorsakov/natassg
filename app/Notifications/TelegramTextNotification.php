<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramMessage;

class TelegramTextNotification extends Notification
{
    public function __construct(public string $message)
    {
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        if (! $notifiable->routeNotificationForTelegram()) {
            return [];
        }

        return ['telegram'];
    }

    public function toTelegram(object $notifiable): TelegramMessage
    {
        return TelegramMessage::create()
            ->content($this->message);
    }
}
