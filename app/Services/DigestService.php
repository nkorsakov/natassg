<?php

namespace App\Services;

use App\Jobs\SendTelegramNotificationJob;
use App\Models\User;
use App\Support\DictionaryResolver;
use Illuminate\Support\Carbon;

class DigestService
{
    public function sendMorning(): int
    {
        return $this->sendForDay('morning', Carbon::now($this->tz())->startOfDay());
    }

    public function sendEvening(): int
    {
        return $this->sendForDay('evening', Carbon::now($this->tz())->addDay()->startOfDay());
    }

    protected function sendForDay(string $kind, Carbon $dayStart): int
    {
        $dayEnd = $dayStart->copy()->endOfDay();
        $label = $kind === 'morning' ? 'Сегодня' : 'Завтра';
        $dateLabel = $dayStart->format('d.m.Y');
        $sent = 0;

        $users = User::query()
            ->whereNotNull('telegram_id')
            ->get();

        foreach ($users as $user) {
            $message = $this->buildMessage($user, $dayStart, $dayEnd, $label, $dateLabel, $kind === 'morning');

            if ($message === null) {
                continue;
            }

            SendTelegramNotificationJob::dispatchSync($user, $message);
            $sent++;
        }

        return $sent;
    }

    protected function buildMessage(
        User $user,
        Carbon $dayStart,
        Carbon $dayEnd,
        string $label,
        string $dateLabel,
        bool $includeImportant,
    ): ?string {
        $closedIds = [
            DictionaryResolver::statusId('done'),
            DictionaryResolver::statusId('cancelled'),
        ];

        $events = $user->events()
            ->whereBetween('starts_at', [$dayStart, $dayEnd])
            ->orderBy('starts_at')
            ->get();

        $deadlineTasks = $user->tasks()
            ->with(['priority', 'status'])
            ->whereNotNull('deadline')
            ->whereBetween('deadline', [$dayStart, $dayEnd])
            ->whereNotIn('status_id', $closedIds)
            ->orderBy('deadline')
            ->get();

        $important = collect();
        if ($includeImportant) {
            $highIds = array_filter([
                DictionaryResolver::priorityId('high'),
                DictionaryResolver::priorityId('urgent'),
            ]);

            $important = $user->tasks()
                ->with(['priority', 'status'])
                ->whereIn('priority_id', $highIds)
                ->whereNotIn('status_id', $closedIds)
                ->whereNull('closed_at')
                ->orderByDesc('id')
                ->limit(15)
                ->get()
                ->reject(fn ($t) => $deadlineTasks->contains('id', $t->id))
                ->values();
        }

        if ($events->isEmpty() && $deadlineTasks->isEmpty() && $important->isEmpty()) {
            return "SkyDesk · {$label} ({$dateLabel})\n\nПока тихо — событий и важных поручений нет.";
        }

        $lines = ["SkyDesk · {$label} ({$dateLabel})"];

        if ($events->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '📅 События:';
            foreach ($events as $event) {
                $time = $event->all_day
                    ? 'весь день'
                    : $event->starts_at->timezone($this->tz())->format('H:i');
                $lines[] = "· {$time} — {$event->title}";
            }
        }

        if ($deadlineTasks->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '⏱ Дедлайны:';
            foreach ($deadlineTasks as $task) {
                $time = $task->deadline->timezone($this->tz())->format('H:i');
                $title = $task->title ?: 'Без названия';
                $lines[] = "· {$time} — {$title}";
            }
        }

        if ($important->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '🔥 Важные поручения:';
            foreach ($important as $task) {
                $prio = $task->priority?->label ?? '';
                $title = $task->title ?: 'Без названия';
                $lines[] = $prio ? "· [{$prio}] {$title}" : "· {$title}";
            }
        }

        return implode("\n", $lines);
    }

    protected function tz(): string
    {
        return (string) config('notifications.timezone', config('app.timezone'));
    }
}
