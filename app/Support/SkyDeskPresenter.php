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
            'is_default' => (bool) ($r->is_default ?? false),
        ])->values();

        return [
            'statuses' => $map(\App\Models\TaskStatus::orderBy('sort')->get()),
            'priorities' => $map(\App\Models\TaskPriority::orderBy('sort')->get()),
            'taskTypes' => $map(\App\Models\TaskType::orderBy('sort')->get()),
            'eventTypes' => $map(\App\Models\EventType::orderBy('sort')->get()),
            'advanceStatuses' => \App\Enums\AdvanceStatus::dictionary(),
            'expenseArticles' => $map(\App\Models\ExpenseArticle::orderBy('sort')->get()),
            'disbursementMethods' => $map(\App\Models\DisbursementMethod::orderBy('sort')->get()),
        ];
    }

    public static function workspace(User $user): array
    {
        $isAdmin = (bool) $user->is_admin;

        $taskQuery = Task::query()
            ->with([
                'user',
                'status',
                'priority',
                'type',
                'events',
                'attachments',
                'advances',
                'children',
                'reminders' => fn ($q) => $q->pending()->orderBy('remind_at'),
                'comments' => fn ($q) => $q->with('user')->orderByDesc('created_at'),
            ])
            ->orderByDesc('id');

        $eventQuery = CalendarEvent::query()
            ->with(['user', 'type', 'tasks'])
            ->orderBy('starts_at');

        $advanceQuery = Advance::query()
            ->with([
                'user',
                'disbursementMethod',
                'tasks',
                'expenses.receipts',
                'expenses.article',
                'expenses.supplier',
            ])
            ->orderByDesc('id');

        $expenseQuery = Expense::query()
            ->with(['user', 'receipts', 'article', 'supplier'])
            ->orderByDesc('id');

        if (! $isAdmin) {
            $taskQuery->where('user_id', $user->id);
            $eventQuery->where('user_id', $user->id);
            $advanceQuery->where('user_id', $user->id);
            $expenseQuery->where('user_id', $user->id);
        }

        $tasks = $taskQuery->get();
        $events = $eventQuery->get();
        $advances = $advanceQuery->get();
        $expenses = $expenseQuery->get();

        $contacts = $user->contacts()->orderBy('name')->get();
        $suppliers = $user->suppliers()->with('contact')->orderBy('name')->get();
        $wallet = $user->wallet;
        if ($wallet) {
            $wallet->load([
                'transactions' => fn ($q) => $q
                    ->with(['advance', 'expense.article', 'expense.supplier'])
                    ->orderByDesc('occurred_at')
                    ->orderByDesc('id')
                    ->limit(100),
            ]);
        }

        $presentedAdvances = $advances->map(fn (Advance $a) => self::advance($a))->values();
        $walletAdvances = $isAdmin
            ? $presentedAdvances->filter(fn (array $a) => (int) ($a['user_id'] ?? 0) === (int) $user->id)->values()
            : $presentedAdvances;

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
            'wallet' => self::wallet($wallet, $walletAdvances),
            'expenses' => $expenses->map(fn (Expense $e) => self::expense($e))->values(),
            'receipts' => $expenses->flatMap(
                fn (Expense $e) => $e->receipts->map(fn ($r) => self::receipt($r, $e->id))
            )->values(),
        ];
    }

    public static function owner(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'initials' => $user->initials ?: mb_strtoupper(mb_substr($user->name, 0, 2)),
        ];
    }

    public static function task(Task $task): array
    {
        $task->loadMissing([
            'user',
            'status',
            'priority',
            'type',
            'events',
            'attachments',
            'advances',
            'children',
            'reminders',
            'comments.user',
        ]);

        $pendingReminders = $task->reminders
            ->filter(fn ($r) => $r->sent_at === null && $r->cancelled_at === null)
            ->sortBy('remind_at')
            ->values();

        $comments = $task->comments
            ->sortByDesc('created_at')
            ->values();

        return [
            'id' => $task->id,
            'user_id' => $task->user_id,
            'user' => self::owner($task->user),
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
            'comments' => $comments->map(fn ($c) => self::comment($c))->values(),
        ];
    }

    public static function comment($comment): array
    {
        $comment->loadMissing('user');

        return [
            'id' => $comment->id,
            'user_id' => $comment->user_id,
            'user' => self::owner($comment->user),
            'body' => $comment->body,
            'created_at' => optional($comment->created_at)?->format('Y-m-d\TH:i'),
            'updated_at' => optional($comment->updated_at)?->format('Y-m-d\TH:i'),
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
        $event->loadMissing(['user', 'type', 'tasks']);

        return [
            'id' => $event->id,
            'user_id' => $event->user_id,
            'user' => self::owner($event->user),
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
        $advance->loadMissing(['user', 'disbursementMethod', 'tasks', 'expenses.receipts', 'expenses.article', 'expenses.supplier']);
        $remainingMinor = (int) WalletTransaction::query()
            ->where('advance_id', $advance->id)
            ->where('account', WalletTransaction::ACCOUNT_ADVANCE)
            ->sum('amount_minor');

        $status = $advance->statusEnum();
        $isFunded = $status->isFunded();

        // Pending/approved have no ledger credit yet — do not treat empty balance as "fully spent".
        if (! $isFunded) {
            $spentMinor = 0;
            $remainingMinor = 0;
        } else {
            $spentMinor = (int) $advance->expenses
                ->where('debit_account', WalletTransaction::ACCOUNT_ADVANCE)
                ->sum('amount_minor');
            $remainingMinor = max(0, $remainingMinor);
        }

        $amount = DictionaryResolver::minorToRubles((int) $advance->amount_minor);

        return [
            'id' => $advance->id,
            'user_id' => $advance->user_id,
            'user' => self::owner($advance->user),
            'title' => $advance->title,
            'task_id' => $advance->tasks->first()?->id,
            'task_ids' => $advance->tasks->pluck('id')->values(),
            'amount' => $amount,
            'amount_minor' => $advance->amount_minor,
            'note' => $advance->note ?? '',
            'status_id' => $status->value,
            'disbursement_method_id' => $advance->disbursementMethod?->slug,
            'expense_ids' => $advance->expenses->pluck('id')->values(),
            'spent' => DictionaryResolver::minorToRubles($spentMinor),
            'remaining' => DictionaryResolver::minorToRubles($remainingMinor),
            'remaining_minor' => $remainingMinor,
            'expenses' => $advance->expenses->map(fn ($e) => self::expense($e))->values(),
            'needed_at' => optional($advance->needed_at)?->toDateString(),
            'issued_at' => optional($advance->issued_at)?->toDateString(),
            'closed_at' => optional($advance->closed_at)?->toDateString(),
            'created_at' => optional($advance->created_at)?->toDateString(),
        ];
    }

    public static function expense(Expense $expense): array
    {
        $expense->loadMissing(['receipts', 'article', 'supplier']);
        $occurredAt = WalletTransaction::query()
            ->where('expense_id', $expense->id)
            ->where('type', WalletTransaction::TYPE_EXPENSE)
            ->orderByDesc('id')
            ->get()
            ->first(function (WalletTransaction $tx) {
                $meta = is_array($tx->meta) ? $tx->meta : [];

                return ($meta['reason'] ?? null) !== 'expense_reverse';
            })
            ?->occurred_at;

        return [
            'id' => $expense->id,
            'advance_id' => $expense->advance_id,
            'debit_account' => $expense->debit_account ?? WalletTransaction::ACCOUNT_UNASSIGNED,
            'task_id' => $expense->task_id,
            'article_id' => $expense->article?->slug,
            'supplier_id' => $expense->supplier_id,
            'amount' => DictionaryResolver::minorToRubles((int) $expense->amount_minor),
            'description' => $expense->description ?? '',
            'occurred_at' => optional($occurredAt)?->toDateString(),
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
        $walletMinor = (int) ($wallet?->balance_minor ?? 0);

        $inAdvancesMinor = 0;
        if ($advances) {
            foreach ($advances as $a) {
                $status = is_array($a)
                    ? ($a['status_id'] ?? null)
                    : $a->statusEnum()->value;
                if ($status !== \App\Enums\AdvanceStatus::Reporting->value) {
                    continue;
                }
                $remaining = is_array($a)
                    ? (int) ($a['remaining_minor'] ?? DictionaryResolver::rublesToMinor($a['remaining'] ?? 0))
                    : (int) WalletTransaction::query()
                        ->where('advance_id', $a->id)
                        ->where('account', WalletTransaction::ACCOUNT_ADVANCE)
                        ->sum('amount_minor');
                if ($remaining > 0) {
                    $inAdvancesMinor += $remaining;
                }
            }
        }

        $unassignedMinor = 0;
        if ($wallet) {
            $unassignedMinor = (int) abs((int) WalletTransaction::query()
                ->where('wallet_id', $wallet->id)
                ->where('account', WalletTransaction::ACCOUNT_UNASSIGNED)
                ->sum('amount_minor'));
        }

        $onHandMinor = $walletMinor + $inAdvancesMinor - $unassignedMinor;

        $transactions = [];
        if ($wallet && $wallet->relationLoaded('transactions')) {
            $transactions = $wallet->transactions
                ->filter(function (WalletTransaction $tx) {
                    $meta = is_array($tx->meta) ? $tx->meta : [];

                    if (($meta['reason'] ?? null) === 'expense_reverse') {
                        return false;
                    }

                    // Orphans after expense delete + nullOnDelete FK: no expense row, but debit still shown.
                    if (
                        $tx->type === WalletTransaction::TYPE_EXPENSE
                        && $tx->expense_id === null
                        && ($meta['kind'] ?? null) !== 'close_writeoff'
                    ) {
                        return false;
                    }

                    return true;
                })
                ->map(fn (WalletTransaction $tx) => [
                    'id' => $tx->id,
                    'type' => $tx->type,
                    'account' => $tx->account,
                    'title' => self::transactionTitle($tx),
                    'amount' => DictionaryResolver::minorToRubles((int) $tx->amount_minor),
                    'amount_minor' => $tx->amount_minor,
                    'advance_id' => $tx->advance_id,
                    'expense_id' => $tx->expense_id,
                    'meta' => $tx->meta,
                    'occurred_at' => optional($tx->occurred_at ?? $tx->created_at)?->toDateString(),
                    'created_at' => optional($tx->created_at)?->toIso8601String(),
                ])->values();
        }

        return [
            'wallet' => DictionaryResolver::minorToRubles($walletMinor),
            'wallet_minor' => $walletMinor,
            'balance' => DictionaryResolver::minorToRubles($onHandMinor),
            'balance_minor' => $onHandMinor,
            'on_hand' => DictionaryResolver::minorToRubles($onHandMinor),
            'on_hand_minor' => $onHandMinor,
            'in_advances' => DictionaryResolver::minorToRubles($inAdvancesMinor),
            'in_advances_minor' => $inAdvancesMinor,
            'unassigned' => DictionaryResolver::minorToRubles($unassignedMinor),
            'unassigned_minor' => $unassignedMinor,
            // aliases for older UI fields
            'free' => DictionaryResolver::minorToRubles($walletMinor),
            'free_minor' => $walletMinor,
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

        if ($tx->type === WalletTransaction::TYPE_INCOME) {
            if ($tx->account === WalletTransaction::ACCOUNT_ADVANCE) {
                return trim((string) ($advanceTitle ?? '')) ?: 'Аванс получен';
            }

            return trim((string) ($meta['title'] ?? ''))
                ?: trim((string) ($meta['note'] ?? ''))
                ?: 'Приход';
        }

        if ($tx->type === WalletTransaction::TYPE_TRANSFER) {
            $kind = $meta['kind'] ?? null;
            if ($kind === 'close_to_wallet') {
                return $advanceTitle ? 'В кошелёк: '.$advanceTitle : 'Закрытие в кошелёк';
            }

            return $advanceTitle ? 'Перевод: '.$advanceTitle : 'Перевод';
        }

        if ($tx->type === WalletTransaction::TYPE_EXPENSE) {
            if (($meta['kind'] ?? null) === 'close_writeoff') {
                return $advanceTitle ? 'Списание без отчёта: '.$advanceTitle : 'Списание без отчёта';
            }
            if (($meta['reason'] ?? null) === 'expense_reverse') {
                return 'Корректировка траты';
            }

            return trim((string) ($expenseTitle ?? '')) ?: 'Трата';
        }

        return (string) $tx->type;
    }
}
