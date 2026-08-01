<?php

namespace App\Support;

use App\Models\Advance;
use App\Models\CalendarEvent;
use App\Models\Contact;
use App\Models\Expense;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
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
            'expenseArticles' => $map(\App\Models\ExpenseArticle::orderBy('sort')->get()),
            'disbursementMethods' => $map(\App\Models\DisbursementMethod::orderBy('sort')->get()),
        ];
    }

    public static function workspace(User $user): array
    {
        $tasks = $user->tasks()
            ->with(['status', 'priority', 'type', 'events', 'attachments', 'advances', 'children', 'reminders' => fn ($q) => $q->pending()->orderBy('remind_at')])
            ->orderByDesc('id')
            ->get();

        $events = $user->events()
            ->with(['type', 'tasks'])
            ->orderBy('starts_at')
            ->get();

        $advances = $user->advances()
            ->with(['status', 'disbursementMethod', 'tasks', 'expenses.receipts', 'expenses.article', 'expenses.supplier'])
            ->orderByDesc('id')
            ->get();

        $expenses = Expense::query()
            ->where('user_id', $user->id)
            ->with(['receipts', 'article', 'supplier'])
            ->orderByDesc('id')
            ->get();

        $contacts = $user->contacts()->orderBy('name')->get();
        $suppliers = $user->suppliers()->with('contact')->orderBy('name')->get();
        $wallet = $user->wallet;
        if ($wallet) {
            $wallet->load([
                'transactions' => fn ($q) => $q
                    ->with(['advance', 'expense.article', 'expense.supplier'])
                    ->orderByDesc('id')
                    ->limit(100),
            ]);
        }

        $presentedAdvances = $advances->map(fn (Advance $a) => self::advance($a))->values();

        return [
            'profile' => [
                'name' => $user->name,
                'initials' => $user->initials ?: mb_strtoupper(mb_substr($user->name, 0, 2)),
                'role' => $user->role_title ?: 'Личный помощник',
            ],
            'dictionaries' => self::dictionaries(),
            'tasks' => $tasks->map(fn (Task $t) => self::task($t))->values(),
            'events' => $events->map(fn (CalendarEvent $e) => self::event($e))->values(),
            'advances' => $presentedAdvances,
            'contacts' => $contacts->map(fn (Contact $c) => self::contact($c))->values(),
            'suppliers' => $suppliers->map(fn (Supplier $s) => self::supplier($s))->values(),
            'wallet' => self::wallet($wallet, $presentedAdvances),
            'expenses' => $expenses->map(fn (Expense $e) => self::expense($e))->values(),
            'receipts' => $expenses->flatMap(
                fn (Expense $e) => $e->receipts->map(fn ($r) => self::receipt($r, $e->id))
            )->values(),
        ];
    }

    public static function task(Task $task): array
    {
        $task->loadMissing(['status', 'priority', 'type', 'events', 'attachments', 'advances', 'children', 'reminders']);

        $pendingReminders = $task->reminders
            ->filter(fn ($r) => $r->sent_at === null && $r->cancelled_at === null)
            ->sortBy('remind_at')
            ->values();

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
            'reminders' => $pendingReminders->map(fn ($r) => [
                'id' => $r->id,
                'kind' => $r->kind,
                'remind_at' => optional($r->remind_at)?->format('Y-m-d\TH:i'),
                'message' => $r->message,
            ])->values(),
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
        $advance->loadMissing(['status', 'disbursementMethod', 'tasks', 'expenses.receipts', 'expenses.article', 'expenses.supplier']);
        $spent = (int) $advance->expenses->sum('amount_minor');
        $amount = DictionaryResolver::minorToRubles((int) $advance->amount_minor);

        return [
            'id' => $advance->id,
            'title' => $advance->title,
            'task_id' => $advance->tasks->first()?->id,
            'task_ids' => $advance->tasks->pluck('id')->values(),
            'amount' => $amount,
            'amount_minor' => $advance->amount_minor,
            'note' => $advance->note ?? '',
            'status_id' => $advance->status?->slug,
            'disbursement_method_id' => $advance->disbursementMethod?->slug,
            'expense_ids' => $advance->expenses->pluck('id')->values(),
            'spent' => DictionaryResolver::minorToRubles($spent),
            'remaining' => DictionaryResolver::minorToRubles((int) $advance->amount_minor - $spent),
            'expenses' => $advance->expenses->map(fn ($e) => self::expense($e))->values(),
        ];
    }

    public static function expense(Expense $expense): array
    {
        $expense->loadMissing(['receipts', 'article', 'supplier']);

        return [
            'id' => $expense->id,
            'advance_id' => $expense->advance_id,
            'task_id' => $expense->task_id,
            'article_id' => $expense->article?->slug,
            'supplier_id' => $expense->supplier_id,
            'amount' => DictionaryResolver::minorToRubles((int) $expense->amount_minor),
            'description' => $expense->description ?? '',
            'receipt_ids' => $expense->receipts->pluck('id')->values(),
            'receipts' => $expense->receipts->map(fn ($r) => self::receipt($r, $expense->id))->values(),
        ];
    }

    public static function receipt($receipt, ?int $expenseId = null): array
    {
        $mime = (string) ($receipt->mime ?? '');
        $isImage = str_starts_with($mime, 'image/')
            || preg_match('/\.(jpe?g|png|gif|webp|heic|bmp)$/i', (string) $receipt->original_name);

        return [
            'id' => $receipt->id,
            'expense_id' => $expenseId ?? $receipt->expense_id,
            'name' => $receipt->original_name,
            'original_name' => $receipt->original_name,
            'mime' => $mime ?: null,
            'kind' => $isImage ? 'image' : 'file',
            'url' => Storage::disk('public')->url($receipt->path),
        ];
    }

    public static function supplier(Supplier $supplier): array
    {
        $supplier->loadMissing('contact');

        return [
            'id' => $supplier->id,
            'name' => $supplier->name,
            'contact_id' => $supplier->contact_id,
            'note' => $supplier->note ?? '',
            'contact_name' => $supplier->contact?->name,
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
            'is_supplier' => (bool) $contact->is_supplier,
        ];
    }

    public static function wallet(?Wallet $wallet, $advances = null): array
    {
        $minor = (int) ($wallet?->balance_minor ?? 0);

        $inAdvancesMinor = 0;
        if ($advances) {
            foreach ($advances as $a) {
                $status = is_array($a) ? ($a['status_id'] ?? null) : $a->status?->slug;
                if (! in_array($status, ['issued', 'reporting'], true)) {
                    continue;
                }
                $remaining = is_array($a)
                    ? DictionaryResolver::rublesToMinor($a['remaining'] ?? 0)
                    : ((int) $a->amount_minor - (int) $a->expenses->sum('amount_minor'));
                if ($remaining > 0) {
                    $inAdvancesMinor += $remaining;
                }
            }
        }

        $transactions = [];
        if ($wallet && $wallet->relationLoaded('transactions')) {
            $transactions = $wallet->transactions->map(fn (WalletTransaction $tx) => [
                'id' => $tx->id,
                'type' => $tx->type,
                'title' => self::transactionTitle($tx),
                'amount' => DictionaryResolver::minorToRubles((int) $tx->amount_minor),
                'amount_minor' => $tx->amount_minor,
                'advance_id' => $tx->advance_id,
                'expense_id' => $tx->expense_id,
                'meta' => $tx->meta,
                'created_at' => optional($tx->created_at)?->toIso8601String(),
            ])->values();
        }

        return [
            'balance' => DictionaryResolver::minorToRubles($minor),
            'balance_minor' => $minor,
            'in_advances' => DictionaryResolver::minorToRubles($inAdvancesMinor),
            'in_advances_minor' => $inAdvancesMinor,
            'free' => DictionaryResolver::minorToRubles($minor - $inAdvancesMinor),
            'free_minor' => $minor - $inAdvancesMinor,
            'currency' => $wallet?->currency ?? 'RUB',
            'transactions' => $transactions,
        ];
    }

    public static function transactionTitle(WalletTransaction $tx): string
    {
        $meta = is_array($tx->meta) ? $tx->meta : [];
        $advanceTitle = $tx->advance?->title;
        $expenseTitle = $tx->expense?->description
            ?: $tx->expense?->article?->label;

        return match ($tx->type) {
            WalletTransaction::TYPE_TOPUP => trim((string) ($meta['title'] ?? ''))
                ?: trim((string) ($meta['note'] ?? ''))
                ?: 'Пополнение',
            WalletTransaction::TYPE_ISSUE => trim((string) ($advanceTitle ?? '')) ?: 'Выдача аванса',
            WalletTransaction::TYPE_EXPENSE => trim((string) ($expenseTitle ?? '')) ?: 'Трата',
            WalletTransaction::TYPE_RETURN => $advanceTitle
                ? 'Возврат: '.$advanceTitle
                : 'Возврат',
            WalletTransaction::TYPE_WRITEOFF => $advanceTitle
                ? 'Списание: '.$advanceTitle
                : 'Списание',
            WalletTransaction::TYPE_RELEASE => $advanceTitle
                ? 'В свободно: '.$advanceTitle
                : 'В свободный остаток',
            WalletTransaction::TYPE_AMOUNT_ADJUST => $advanceTitle
                ? 'Корректировка: '.$advanceTitle
                : 'Корректировка суммы',
            default => (string) $tx->type,
        };
    }
}
