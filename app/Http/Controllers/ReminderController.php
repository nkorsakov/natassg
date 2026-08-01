<?php

namespace App\Http\Controllers;

use App\Models\Reminder;
use App\Models\Task;
use App\Services\ReminderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReminderController extends Controller
{
    public function store(Request $request, Task $task, ReminderService $reminders): RedirectResponse
    {
        abort_unless($task->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'remind_at' => ['required', 'date'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $reminders->createManual(
            $task,
            Carbon::parse($data['remind_at']),
            $data['message'] ?? null,
        );

        return back();
    }

    public function destroy(Request $request, Task $task, Reminder $reminder, ReminderService $reminders): RedirectResponse
    {
        abort_unless($task->user_id === $request->user()->id, 403);
        abort_unless($reminder->task_id === $task->id, 404);
        abort_unless($reminder->user_id === $request->user()->id, 403);
        abort_unless($reminder->kind === Reminder::KIND_MANUAL, 422);

        $reminders->cancel($reminder);

        return back();
    }
}
