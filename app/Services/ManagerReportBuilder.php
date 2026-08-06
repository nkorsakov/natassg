<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\ManagerReport;
use App\Models\Task;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Support\DictionaryResolver;
use App\Support\SkyDeskPresenter;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ManagerReportBuilder
{
    public function __construct(protected ReportOnHandCalculator $onHand) {}

    /**
     * @param  list<int>  $excludeTaskIds
     */
    public function create(
        User $subject,
        Carbon $periodFrom,
        Carbon $periodTo,
        ?User $createdBy = null,
        array $excludeTaskIds = [],
    ): ManagerReport {
        $from = $periodFrom->copy()->startOfDay();
        $to = $periodTo->copy()->startOfDay();

        if ($to->lt($from)) {
            throw new \InvalidArgumentException('Дата окончания раньше даты начала');
        }

        $payload = $this->buildPayload($subject, $from, $to, $excludeTaskIds);

        return ManagerReport::create([
            'token' => Str::random(48),
            'user_id' => $subject->id,
            'created_by' => ($createdBy ?? $subject)->id,
            'period_from' => $from->toDateString(),
            'period_to' => $to->toDateString(),
            'payload' => $payload,
            'views_count' => 0,
            'status' => ManagerReport::STATUS_PENDING,
            'accepted_at' => null,
        ]);
    }

    /**
     * @param  list<int>  $excludeTaskIds
     * @return array<string, mixed>
     */
    public function buildPayload(User $subject, Carbon $from, Carbon $to, array $excludeTaskIds = []): array
    {
        $rangeStart = $from->copy()->startOfDay();
        $rangeEnd = $to->copy()->endOfDay();
        $exclude = array_values(array_unique(array_map('intval', $excludeTaskIds)));

        $closedTasks = Task::query()
            ->with(['status', 'priority', 'type'])
            ->where('user_id', $subject->id)
            ->whereNotNull('closed_at')
            ->whereBetween('closed_at', [$rangeStart, $rangeEnd])
            ->when($exclude !== [], fn ($q) => $q->whereNotIn('id', $exclude))
            ->orderBy('closed_at')
            ->get();

        $closedIds = $closedTasks->pluck('id')->all();

        $activeTasks = Task::query()
            ->with(['status', 'priority', 'type'])
            ->where('user_id', $subject->id)
            ->where('created_at', '<=', $rangeEnd)
            ->where(function ($q) use ($rangeStart) {
                $q->whereNull('closed_at')
                    ->orWhere('closed_at', '>=', $rangeStart);
            })
            ->when($closedIds !== [], fn ($q) => $q->whereNotIn('id', $closedIds))
            ->when($exclude !== [], fn ($q) => $q->whereNotIn('id', $exclude))
            ->orderBy('created_at')
            ->get();

        $events = CalendarEvent::query()
            ->with('type')
            ->where('user_id', $subject->id)
            ->whereBetween('starts_at', [$rangeStart, $rangeEnd])
            ->orderBy('starts_at')
            ->get();

        $opening = $this->onHand->opening($subject, $from);
        $closing = $this->onHand->closing($subject, $to);

        $movements = $this->movements($subject, $rangeStart, $rangeEnd);

        $incomeMinor = 0;
        $expenseMinor = 0;
        foreach ($movements as $row) {
            if ($row['type'] === WalletTransaction::TYPE_INCOME) {
                $incomeMinor += (int) $row['amount_minor'];
            } elseif ($row['type'] === WalletTransaction::TYPE_EXPENSE) {
                $expenseMinor += abs((int) $row['amount_minor']);
            }
        }

        return [
            'subject' => [
                'id' => $subject->id,
                'name' => $subject->name,
                'initials' => $subject->initials,
                'role' => $subject->role_title,
            ],
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'work' => [
                'closed' => $closedTasks->map(fn (Task $t) => $this->taskRow($t))->values()->all(),
                'active' => $activeTasks->map(fn (Task $t) => $this->taskRow($t))->values()->all(),
                'events' => $events->map(fn (CalendarEvent $e) => $this->eventRow($e))->values()->all(),
            ],
            'finance' => [
                'currency' => $subject->wallet?->currency ?? 'RUB',
                'opening_on_hand' => DictionaryResolver::minorToRubles($opening['on_hand_minor']),
                'opening_on_hand_minor' => $opening['on_hand_minor'],
                'closing_on_hand' => DictionaryResolver::minorToRubles($closing['on_hand_minor']),
                'closing_on_hand_minor' => $closing['on_hand_minor'],
                'income_total' => DictionaryResolver::minorToRubles($incomeMinor),
                'income_total_minor' => $incomeMinor,
                'expense_total' => DictionaryResolver::minorToRubles($expenseMinor),
                'expense_total_minor' => $expenseMinor,
                'movements' => $movements,
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function movements(User $subject, Carbon $rangeStart, Carbon $rangeEnd): array
    {
        $wallet = Wallet::query()->where('user_id', $subject->id)->first();
        if (! $wallet) {
            return [];
        }

        $txs = WalletTransaction::query()
            ->with(['advance', 'expense.article'])
            ->where('wallet_id', $wallet->id)
            ->whereBetween('occurred_at', [$rangeStart, $rangeEnd])
            ->whereIn('type', [
                WalletTransaction::TYPE_INCOME,
                WalletTransaction::TYPE_EXPENSE,
                WalletTransaction::TYPE_TRANSFER,
            ])
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get()
            ->filter(function (WalletTransaction $tx) {
                $meta = is_array($tx->meta) ? $tx->meta : [];

                if (($meta['reason'] ?? null) === 'expense_reverse') {
                    return false;
                }

                if (
                    $tx->type === WalletTransaction::TYPE_EXPENSE
                    && $tx->expense_id === null
                    && ($meta['kind'] ?? null) !== 'close_writeoff'
                ) {
                    return false;
                }

                return true;
            })
            ->values();

        return $txs->map(fn (WalletTransaction $tx) => [
            'id' => $tx->id,
            'type' => $tx->type,
            'account' => $tx->account,
            'title' => SkyDeskPresenter::transactionTitle($tx),
            'amount' => DictionaryResolver::minorToRubles((int) $tx->amount_minor),
            'amount_minor' => (int) $tx->amount_minor,
            'occurred_at' => optional($tx->occurred_at ?? $tx->created_at)?->toDateString(),
        ])->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function taskRow(Task $task): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'status_id' => $task->status?->slug,
            'status_label' => $task->status?->label,
            'status_color' => $task->status?->color,
            'priority_id' => $task->priority?->slug,
            'type_id' => $task->type?->slug,
            'type_label' => $task->type?->label,
            'type_color' => $task->type?->color,
            'closed_at' => optional($task->closed_at)?->toDateString(),
            'created_at' => optional($task->created_at)?->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function eventRow(CalendarEvent $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'type_id' => $event->type?->slug,
            'type_label' => $event->type?->label,
            'type_color' => $event->type?->color,
            'start' => optional($event->starts_at)?->format('Y-m-d\TH:i'),
            'end' => optional($event->ends_at)?->format('Y-m-d\TH:i'),
            'all_day' => (bool) $event->all_day,
            'place' => $event->place ?? '',
        ];
    }
}
