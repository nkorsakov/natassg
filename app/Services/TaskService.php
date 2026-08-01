<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use App\Support\DictionaryResolver;
use Illuminate\Support\Facades\DB;

class TaskService
{
    public function __construct(
        protected ReminderService $reminders,
    ) {
    }

    public function create(User $user, array $data): Task
    {
        $statusId = DictionaryResolver::statusId($data['status_id'] ?? 'new');
        $priorityId = DictionaryResolver::priorityId($data['priority_id'] ?? 'normal');
        $typeId = DictionaryResolver::taskTypeId($data['type_id'] ?? 'purchase');

        $task = Task::create([
            'user_id' => $user->id,
            'parent_id' => $data['parent_id'] ?? null,
            'status_id' => $statusId,
            'priority_id' => $priorityId,
            'type_id' => $typeId,
            'title' => array_key_exists('title', $data) ? (string) ($data['title'] ?? '') : 'Новое поручение',
            'note' => $data['note'] ?? null,
            'deadline' => $data['deadline'] ?? null,
        ]);

        if (! empty($data['event_id'])) {
            $task->events()->syncWithoutDetaching([(int) $data['event_id']]);
        }

        if (! empty($data['event_ids']) && is_array($data['event_ids'])) {
            $task->events()->syncWithoutDetaching(array_map('intval', $data['event_ids']));
        }

        $this->reminders->syncForTask($task);

        return $task->fresh(['status', 'priority', 'type', 'events', 'attachments', 'advances', 'children', 'reminders']);
    }

    public function update(Task $task, array $data): Task
    {
        if (array_key_exists('title', $data) && $data['title'] !== null) {
            $task->title = $data['title'];
        }
        if (array_key_exists('note', $data)) {
            $task->note = $data['note'];
        }
        if (array_key_exists('deadline', $data)) {
            $task->deadline = $data['deadline'] ?: null;
        }
        if (array_key_exists('parent_id', $data)) {
            $task->parent_id = $data['parent_id'];
        }
        if (array_key_exists('status_id', $data) && $data['status_id'] !== null) {
            $task->status_id = DictionaryResolver::statusId($data['status_id']);
            if (($data['status_id'] === 'done' || $data['status_id'] === DictionaryResolver::statusId('done'))
                && ! $task->closed_at) {
                // closed_at set only via cascade helper usually
            }
        }
        if (array_key_exists('priority_id', $data) && $data['priority_id'] !== null) {
            $task->priority_id = DictionaryResolver::priorityId($data['priority_id']);
        }
        if (array_key_exists('type_id', $data) && $data['type_id'] !== null) {
            $task->type_id = DictionaryResolver::taskTypeId($data['type_id']);
        }

        $task->save();
        $this->reminders->syncForTask($task->fresh(['status']));

        return $task->fresh(['status', 'priority', 'type', 'events', 'attachments', 'advances', 'children', 'reminders']);
    }

    public function makeRoot(Task $task): Task
    {
        $task->parent_id = null;
        $task->save();

        return $task;
    }

    public function closeCascade(Task $task): void
    {
        $doneId = DictionaryResolver::statusId('done');

        DB::transaction(function () use ($task, $doneId) {
            $ids = $this->descendantIds($task);
            $ids[] = $task->id;

            Task::whereIn('id', $ids)->update([
                'status_id' => $doneId,
                'closed_at' => now(),
            ]);

            $this->reminders->cancelPendingForTasks($ids);
        });
    }

    public function linkEvent(Task $task, int $eventId): void
    {
        $task->events()->syncWithoutDetaching([$eventId]);
    }

    public function unlinkEvent(Task $task, int $eventId): void
    {
        $task->events()->detach($eventId);
    }

    /** @return list<int> */
    public function descendantIds(Task $task): array
    {
        $ids = [];
        $walk = function (int $parentId) use (&$walk, &$ids) {
            $children = Task::where('parent_id', $parentId)->pluck('id');
            foreach ($children as $id) {
                $ids[] = (int) $id;
                $walk((int) $id);
            }
        };
        $walk($task->id);

        return $ids;
    }
}
