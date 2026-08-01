<?php

namespace App\Services;

use App\Models\Reminder;
use App\Models\Task;
use App\Support\DictionaryResolver;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class ReminderService
{
    public function syncForTask(Task $task): void
    {
        $task->loadMissing('status');

        if ($this->taskIsClosed($task) || ! $task->deadline) {
            $this->cancelPendingAuto($task);

            return;
        }

        $remindAt = $this->autoRemindAt($task->deadline);

        $existing = Reminder::query()
            ->where('task_id', $task->id)
            ->where('kind', Reminder::KIND_DEADLINE_AUTO)
            ->pending()
            ->first();

        if ($existing) {
            $existing->update(['remind_at' => $remindAt]);

            return;
        }

        Reminder::create([
            'user_id' => $task->user_id,
            'task_id' => $task->id,
            'kind' => Reminder::KIND_DEADLINE_AUTO,
            'remind_at' => $remindAt,
            'message' => null,
        ]);
    }

    /**
     * @param  list<int>  $taskIds
     */
    public function cancelPendingForTasks(array $taskIds): void
    {
        if ($taskIds === []) {
            return;
        }

        Reminder::query()
            ->whereIn('task_id', $taskIds)
            ->pending()
            ->update(['cancelled_at' => now()]);
    }

    public function createManual(Task $task, CarbonInterface $remindAt, ?string $message = null): Reminder
    {
        return Reminder::create([
            'user_id' => $task->user_id,
            'task_id' => $task->id,
            'kind' => Reminder::KIND_MANUAL,
            'remind_at' => $remindAt,
            'message' => $message,
        ]);
    }

    public function cancel(Reminder $reminder): void
    {
        if (! $reminder->isPending()) {
            return;
        }

        $reminder->update(['cancelled_at' => now()]);
    }

    public function resolveMessage(Reminder $reminder): string
    {
        if (filled($reminder->message)) {
            return (string) $reminder->message;
        }

        $reminder->loadMissing('task');
        $title = $reminder->task?->title ?: 'Без названия';
        $tz = config('notifications.timezone', config('app.timezone'));
        $when = optional($reminder->task?->deadline)?->timezone($tz)->format('d.m H:i');

        if ($reminder->kind === Reminder::KIND_DEADLINE_AUTO) {
            return $when
                ? "⏰ Дедлайн скоро: «{$title}» · {$when}"
                : "⏰ Напоминание: «{$title}»";
        }

        return "🔔 Напоминание: «{$title}»";
    }

    protected function autoRemindAt(CarbonInterface $deadline): Carbon
    {
        $offset = (int) config('notifications.deadline_offset_hours', 2);
        $at = Carbon::parse($deadline)->subHours($offset);

        if ($at->lessThanOrEqualTo(now())) {
            return Carbon::parse($deadline);
        }

        return $at;
    }

    protected function taskIsClosed(Task $task): bool
    {
        $slug = $task->status?->slug
            ?? DictionaryResolver::statusSlugById($task->status_id);

        return in_array($slug, ['done', 'cancelled'], true) || $task->closed_at !== null;
    }

    protected function cancelPendingAuto(Task $task): void
    {
        Reminder::query()
            ->where('task_id', $task->id)
            ->where('kind', Reminder::KIND_DEADLINE_AUTO)
            ->pending()
            ->update(['cancelled_at' => now()]);
    }
}
