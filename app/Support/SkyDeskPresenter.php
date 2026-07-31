<?php

namespace App\Support;

use App\Models\Advance;
use App\Models\CalendarEvent;
use App\Models\Contact;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\Storage;

class SkyDeskPresenter
{
    public static function money(int $minor): string
    {
        return number_format($minor / 100, 0, ',', ' ').' ₽';
    }

    public static function user(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'initials' => $user->initials ?: mb_strtoupper(mb_substr($user->name, 0, 2)),
            'role' => $user->role_title ?: 'Личный помощник',
            'email' => $user->email,
            'is_admin' => (bool) $user->is_admin,
            'telegram_id' => $user->telegram_id,
        ];
    }

    public static function dictionaries(): array
    {
        $map = fn ($rows) => $rows->map(fn ($r) => [
            'id' => $r->slug,
            'db_id' => $r->id,
            'slug' => $r->slug,
            'label' => $r->label,
            'color' => $r->color,
            'icon' => $r->icon ?? null,
            'sort' => $r->sort,
            'is_system' => (bool) $r->is_system,
        ])->values();

        return [
            'statuses' => $map(\App\Models\TaskStatus::orderBy('sort')->get()),
            'priorities' => $map(\App\Models\TaskPriority::orderBy('sort')->get()),
            'taskTypes' => $map(\App\Models\TaskType::orderBy('sort')->get()),
            'eventTypes' => $map(\App\Models\EventType::orderBy('sort')->get()),
            'advanceStatuses' => $map(\App\Models\AdvanceStatus::orderBy('sort')->get()),
        ];
    }

    public static function workspace(User $user): array
    {
        $tasks = $user->tasks()
            ->with(['status', 'priority', 'type', 'events', 'attachments', 'advances', 'children'])
            ->orderByDesc('id')
            ->get();

        $events = $user->events()
            ->with(['type', 'tasks'])
            ->orderBy('starts_at')
            ->get();

        $advances = $user->advances()
            ->with(['status', 'task', 'expenses.receipts'])
            ->orderByDesc('id')
            ->get();

        $contacts = $user->contacts()->orderBy('name')->get();
        $wallet = $user->wallet;

        return [
            'profile' => [
                'name' => $user->name,
                'initials' => $user->initials ?: mb_strtoupper(mb_substr($user->name, 0, 2)),
                'role' => $user->role_title ?: 'Личный помощник',
            ],
            'dictionaries' => self::dictionaries(),
            'tasks' => $tasks->map(fn (Task $t) => self::task($t))->values(),
            'events' => $events->map(fn (CalendarEvent $e) => self::event($e))->values(),
            'advances' => $advances->map(fn (Advance $a) => self::advance($a))->values(),
            'contacts' => $contacts->map(fn (Contact $c) => self::contact($c))->values(),
            'wallet' => self::wallet($wallet),
            'expenses' => $advances->flatMap(fn (Advance $a) => collect(self::advance($a)['expenses'])->map(
                fn ($e) => [...$e, 'advance_id' => $a->id]
            ))->values(),
            'receipts' => $advances->flatMap(fn (Advance $a) => $a->expenses->flatMap(
                fn ($e) => $e->receipts->map(fn ($r) => [
                    'id' => $r->id,
                    'expense_id' => $e->id,
                    'name' => $r->original_name,
                    'url' => Storage::disk('public')->url($r->path),
                ])
            ))->values(),
        ];
    }

    public static function task(Task $task): array
    {
        $task->loadMissing(['status', 'priority', 'type', 'events', 'attachments', 'advances', 'children']);

        return [
            'id' => $task->id,
            'parent_id' => $task->parent_id,
            'title' => $task->title,
            'note' => $task->note ?? '',
            'deadline' => optional($task->deadline)?->format('Y-m-d\TH:i'),
            'status_id' => $task->status?->slug,
            'priority_id' => $task->priority?->slug,
            'type_id' => $task->type?->slug,
            'event_ids' => $task->events->pluck('id')->values(),
            'advance_ids' => $task->advances->pluck('id')->values(),
            'children_count' => $task->children->count(),
            'attachments' => $task->attachments->map(fn (TaskAttachment $a) => self::attachment($a))->values(),
        ];
    }

    public static function attachment(TaskAttachment $a): array
    {
        return [
            'id' => $a->id,
            'kind' => $a->kind,
            'original_name' => $a->original_name,
            'mime' => $a->mime,
            'size' => $a->size,
            'width' => $a->width,
            'height' => $a->height,
            'url' => Storage::disk('public')->url($a->path),
        ];
    }

    public static function event(CalendarEvent $event): array
    {
        $event->loadMissing(['type', 'tasks']);

        return [
            'id' => $event->id,
            'title' => $event->title,
            'type_id' => $event->type?->slug,
            'start' => optional($event->starts_at)?->format('Y-m-d\TH:i'),
            'end' => optional($event->ends_at)?->format('Y-m-d\TH:i'),
            'allDay' => (bool) $event->all_day,
            'place' => $event->place ?? '',
            'note' => $event->note ?? '',
            'task_ids' => $event->tasks->pluck('id')->values(),
        ];
    }

    public static function advance(Advance $advance): array
    {
        $advance->loadMissing(['status', 'task', 'expenses.receipts']);
        $spent = (int) $advance->expenses->sum('amount_minor');
        $amount = DictionaryResolver::minorToRubles((int) $advance->amount_minor);

        return [
            'id' => $advance->id,
            'title' => $advance->title,
            'task_id' => $advance->task_id,
            'amount' => $amount,
            'amount_minor' => $advance->amount_minor,
            'note' => $advance->note ?? '',
            'status_id' => $advance->status?->slug,
            'expense_ids' => $advance->expenses->pluck('id')->values(),
            'spent' => DictionaryResolver::minorToRubles($spent),
            'remaining' => DictionaryResolver::minorToRubles((int) $advance->amount_minor - $spent),
            'expenses' => $advance->expenses->map(fn ($e) => [
                'id' => $e->id,
                'advance_id' => $advance->id,
                'amount' => DictionaryResolver::minorToRubles((int) $e->amount_minor),
                'description' => $e->description ?? '',
                'receipt_ids' => $e->receipts->pluck('id')->values(),
                'receipts' => $e->receipts->map(fn ($r) => [
                    'id' => $r->id,
                    'expense_id' => $e->id,
                    'name' => $r->original_name,
                    'url' => Storage::disk('public')->url($r->path),
                ])->values(),
            ])->values(),
        ];
    }

    public static function contact(Contact $contact): array
    {
        return [
            'id' => $contact->id,
            'name' => $contact->name,
            'role' => $contact->role ?? '',
            'phone' => $contact->phone ?? '',
            'note' => $contact->note ?? '',
        ];
    }

    public static function wallet(?Wallet $wallet): array
    {
        $minor = $wallet?->balance_minor ?? 0;

        return [
            'balance' => DictionaryResolver::minorToRubles((int) $minor),
            'balance_minor' => $minor,
            'currency' => $wallet?->currency ?? 'RUB',
        ];
    }
}
