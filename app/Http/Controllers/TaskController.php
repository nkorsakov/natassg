<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Services\TaskAttachmentService;
use App\Services\TaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class TaskController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Tasks/Index');
    }

    public function store(Request $request, TaskService $tasks): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'status_id' => ['nullable', 'string'],
            'priority_id' => ['nullable', 'string'],
            'type_id' => ['nullable', 'string'],
            'deadline' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
            'event_id' => ['nullable', 'integer', 'exists:calendar_events,id'],
            'event_ids' => ['nullable', 'array'],
            'event_ids.*' => ['integer', 'exists:calendar_events,id'],
        ]);

        try {
            $task = $tasks->create($request->user(), $data);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['status_id' => $e->getMessage()]);
        }

        return back()->with('created_task_id', $task->id);
    }

    public function update(Request $request, Task $task, TaskService $tasks): RedirectResponse
    {
        $this->authorizeTask($request, $task);

        $data = $request->validate([
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'status_id' => ['nullable', 'string'],
            'priority_id' => ['nullable', 'string'],
            'type_id' => ['nullable', 'string'],
            'deadline' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
        ]);

        try {
            $tasks->update($task, $data);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['status_id' => $e->getMessage()]);
        }

        return back();
    }

    public function makeRoot(Request $request, Task $task, TaskService $tasks): RedirectResponse
    {
        $this->authorizeTask($request, $task);
        $tasks->makeRoot($task);

        return back();
    }

    public function close(Request $request, Task $task, TaskService $tasks): RedirectResponse
    {
        $this->authorizeTask($request, $task);
        $tasks->closeCascade($task);

        return back();
    }

    public function linkEvent(Request $request, Task $task, TaskService $tasks): RedirectResponse
    {
        $this->authorizeTask($request, $task);
        $data = $request->validate([
            'event_id' => ['required', 'integer', 'exists:calendar_events,id'],
        ]);
        $tasks->linkEvent($task, (int) $data['event_id']);

        return back();
    }

    public function unlinkEvent(Request $request, Task $task, int $event, TaskService $tasks): RedirectResponse
    {
        $this->authorizeTask($request, $task);
        $tasks->unlinkEvent($task, $event);

        return back();
    }

    public function storeAttachment(
        Request $request,
        Task $task,
        TaskAttachmentService $attachments,
    ): RedirectResponse {
        $this->authorizeTask($request, $task);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'width' => ['nullable', 'integer'],
            'height' => ['nullable', 'integer'],
        ]);

        $attachments->store(
            $request->user(),
            $task,
            $data['file'],
            $data['width'] ?? null,
            $data['height'] ?? null,
        );

        return back();
    }

    public function destroyAttachment(
        Request $request,
        Task $task,
        int $attachment,
        TaskAttachmentService $attachments,
    ): RedirectResponse {
        $this->authorizeTask($request, $task);
        $model = $task->attachments()->whereKey($attachment)->firstOrFail();
        $attachments->destroy($model);

        return back();
    }

    protected function authorizeTask(Request $request, Task $task): void
    {
        abort_unless($request->user()?->canAccessOwned($task->user_id), 403);
    }
}
