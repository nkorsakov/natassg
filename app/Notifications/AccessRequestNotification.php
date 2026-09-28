<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramMessage;

class AccessRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{name: ?string, phone: ?string, email: ?string, telegram: ?string, place: ?string}  $lead
     */
    public function __construct(public array $lead, public string $submittedAt)
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
        $lines = ['🆕 Заявка на доступ к SkyDesk', ''];

        foreach ([
            'name' => 'Имя',
            'phone' => 'Телефон',
            'email' => 'Email',
            'telegram' => 'Telegram',
        ] as $key => $label) {
            if (filled($this->lead[$key] ?? null)) {
                $lines[] = "{$label}: {$this->lead[$key]}";
            }
        }

        $lines[] = '';
        $lines[] = 'Откуда: '.($this->lead['place'] ?: 'лендинг').' · '.$this->submittedAt;

        // Plain text: user input may contain Markdown control chars (e.g. "_" in emails).
        return TelegramMessage::create()
            ->content(implode("\n", $lines))
            ->normal();
    }
}
