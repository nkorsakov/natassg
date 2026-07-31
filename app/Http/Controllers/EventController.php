<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Support\DictionaryResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Calendar/Index');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type_id' => ['nullable', 'string'],
            'start' => ['required', 'date'],
            'end' => ['nullable', 'date'],
            'allDay' => ['sometimes', 'boolean'],
            'place' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
            'task_ids' => ['nullable', 'array'],
            'task_ids.*' => ['integer', 'exists:tasks,id'],
        ]);

        $event = CalendarEvent::create([
            'user_id' => $request->user()->id,
            'event_type_id' => DictionaryResolver::eventTypeId($data['type_id'] ?? 'other'),
            'title' => $data['title'],
            'starts_at' => $data['start'],
            'ends_at' => $data['end'] ?? null,
            'all_day' => (bool) ($data['allDay'] ?? false),
            'place' => $data['place'] ?? null,
            'note' => $data['note'] ?? null,
        ]);

        if (! empty($data['task_ids'])) {
            $event->tasks()->sync($data['task_ids']);
        }

        return back()->with('created_event_id', $event->id);
    }

    public function update(Request $request, CalendarEvent $event): RedirectResponse
    {
        abort_unless($event->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'type_id' => ['nullable', 'string'],
            'start' => ['sometimes', 'date'],
            'end' => ['nullable', 'date'],
            'allDay' => ['sometimes', 'boolean'],
            'place' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
            'task_ids' => ['nullable', 'array'],
            'task_ids.*' => ['integer', 'exists:tasks,id'],
        ]);

        if (isset($data['title'])) {
            $event->title = $data['title'];
        }
        if (array_key_exists('type_id', $data) && $data['type_id'] !== null) {
            $event->event_type_id = DictionaryResolver::eventTypeId($data['type_id']);
        }
        if (isset($data['start'])) {
            $event->starts_at = $data['start'];
        }
        if (array_key_exists('end', $data)) {
            $event->ends_at = $data['end'] ?: null;
        }
        if (array_key_exists('allDay', $data)) {
            $event->all_day = (bool) $data['allDay'];
        }
        if (array_key_exists('place', $data)) {
            $event->place = $data['place'];
        }
        if (array_key_exists('note', $data)) {
            $event->note = $data['note'];
        }

        $event->save();

        if (array_key_exists('task_ids', $data)) {
            $event->tasks()->sync($data['task_ids'] ?? []);
        }

        return back();
    }

    public function destroy(Request $request, CalendarEvent $event): RedirectResponse
    {
        abort_unless($event->user_id === $request->user()->id, 403);
        $event->delete();

        return back();
    }
}
