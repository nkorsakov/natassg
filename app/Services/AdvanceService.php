<?php

namespace App\Services;

use App\Models\Advance;
use App\Models\Task;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\DictionaryResolver;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AdvanceService
{
    /** @var array<string, list<string>> */
    protected const TRANSITIONS = [
        'pending' => ['approved', 'issued', 'closed'],
        'approved' => ['pending', 'issued', 'closed'],
        'issued' => ['reporting', 'closed', 'pending', 'approved'],
        'reporting' => ['issued', 'closed', 'pending', 'approved'],
        'closed' => ['pending', 'approved', 'issued', 'reporting'],
    ];

    public function __construct(protected WalletLedger $ledger) {}

    public function create(User $user, array $data): Advance
    {
        $statusSlug = $data['status_id'] ?? 'pending';
        if (is_numeric($statusSlug)) {
            $statusSlug = \App\Models\AdvanceStatus::query()->whereKey((int) $statusSlug)->value('slug') ?? 'pending';
        }

        $amountMinor = array_key_exists('amount_minor', $data)
            ? (int) $data['amount_minor']
            : DictionaryResolver::rublesToMinor($data['amount'] ?? 0);

        $methodId = null;
        if (! empty($data['disbursement_method_id'])) {
            $methodId = DictionaryResolver::disbursementMethodId($data['disbursement_method_id']);
        }

        if ($statusSlug === 'issued') {
            if ($amountMinor <= 0) {
                throw new InvalidArgumentException('Перед выдачей укажите сумму больше нуля');
            }
            if (! $methodId) {
                throw new InvalidArgumentException('Укажите способ выдачи');
            }
        }

        $advance = Advance::create([
            'user_id' => $user->id,
            'status_id' => DictionaryResolver::advanceStatusId($statusSlug),
            'disbursement_method_id' => $methodId,
            'title' => array_key_exists('title', $data) ? (string) ($data['title'] ?? '') : '',
            'amount_minor' => $amountMinor,
            'note' => $data['note'] ?? null,
        ]);

        $taskIds = $this->normalizeTaskIds($data, $user);
        if ($taskIds !== null) {
            $advance->tasks()->sync($taskIds);
        }

        if ($statusSlug === 'issued') {
            $this->creditIssue($advance->fresh(['status']));
        }

        return $advance->fresh(['status', 'disbursementMethod', 'tasks', 'expenses.receipts', 'expenses.article', 'expenses.supplier']);
    }

    public function update(Advance $advance, array $data): Advance
    {
        return DB::transaction(function () use ($advance, $data) {
            $advance = Advance::whereKey($advance->id)->lockForUpdate()->firstOrFail();
            $advance->load('status');

            $previousSlug = $advance->status?->slug;
            $wasIssued = in_array($previousSlug, ['issued', 'reporting', 'closed'], true)
                || $this->ledger->hasIssue($advance->id);

            if (isset($data['status_id'])) {
                $newSlug = is_numeric($data['status_id'])
                    ? (\App\Models\AdvanceStatus::query()->whereKey((int) $data['status_id'])->value('slug') ?? null)
                    : (string) $data['status_id'];

                if (! $newSlug) {
                    throw new InvalidArgumentException('Неизвестный статус аванса');
                }

                $this->assertTransition($previousSlug, $newSlug);

                if ($newSlug === 'issued') {
                    $amountForIssue = array_key_exists('amount', $data) || array_key_exists('amount_minor', $data)
                        ? (array_key_exists('amount_minor', $data)
                            ? (int) $data['amount_minor']
                            : DictionaryResolver::rublesToMinor($data['amount']))
                        : (int) $advance->amount_minor;

                    $methodId = array_key_exists('disbursement_method_id', $data)
                        ? DictionaryResolver::disbursementMethodId($data['disbursement_method_id'])
                        : $advance->disbursement_method_id;

                    if ($amountForIssue <= 0) {
                        throw new InvalidArgumentException('Перед выдачей укажите сумму больше нуля');
                    }
                    if (! $methodId) {
                        throw new InvalidArgumentException('Укажите способ выдачи');
                    }

                    $advance->amount_minor = $amountForIssue;
                    $advance->disbursement_method_id = $methodId;
                }

                $advance->status_id = DictionaryResolver::advanceStatusId($newSlug);

                if ($newSlug === 'closed') {
                    $advance->closed_at = $advance->closed_at ?? now();
                } elseif ($previousSlug === 'closed' && $newSlug !== 'closed') {
                    $advance->closed_at = null;
                }
            }

            if (array_key_exists('disbursement_method_id', $data) && ! isset($data['status_id'])) {
                $advance->disbursement_method_id = $data['disbursement_method_id']
                    ? DictionaryResolver::disbursementMethodId($data['disbursement_method_id'])
                    : null;
            }

            if (isset($data['title'])) {
                $advance->title = $data['title'];
            }

            if (array_key_exists('note', $data)) {
                $advance->note = $data['note'];
            }

            $newAmount = null;
            if (array_key_exists('amount_minor', $data)) {
                $newAmount = (int) $data['amount_minor'];
            } elseif (array_key_exists('amount', $data)) {
                $newAmount = DictionaryResolver::rublesToMinor($data['amount']);
            }

            if ($newAmount !== null && $newAmount !== (int) $advance->amount_minor) {
                if ($wasIssued && $this->ledger->hasIssue($advance->id)) {
                    $delta = $newAmount - (int) $advance->amount_minor;
                    $advance->amount_minor = $newAmount;
                    $advance->save();
                    if ($delta !== 0) {
                        $this->ledger->apply($advance->user, WalletTransaction::TYPE_AMOUNT_ADJUST, $delta, [
                            'advance_id' => $advance->id,
                            'meta' => ['reason' => 'amount_change'],
                        ]);
                    }
                } else {
                    $advance->amount_minor = $newAmount;
                }
            }

            $advance->save();
            $advance->load('status');

            if ($previousSlug !== 'issued' && $advance->status?->slug === 'issued') {
                $this->creditIssue($advance);
            }

            $taskIds = $this->normalizeTaskIds($data, $advance->user);
            if ($taskIds !== null) {
                $advance->tasks()->sync($taskIds);
            }

            return $advance->fresh(['status', 'disbursementMethod', 'tasks', 'expenses.receipts', 'expenses.article', 'expenses.supplier']);
        });
    }

    public function creditIssue(Advance $advance): void
    {
        DB::transaction(function () use ($advance) {
            $advance = Advance::whereKey($advance->id)->lockForUpdate()->firstOrFail();

            if ($this->ledger->hasIssue($advance->id)) {
                return;
            }

            if ((int) $advance->amount_minor <= 0) {
                throw new InvalidArgumentException('Сумма выдачи должна быть больше нуля');
            }
            if (! $advance->disbursement_method_id) {
                throw new InvalidArgumentException('Укажите способ выдачи');
            }

            $this->ledger->apply($advance->user, WalletTransaction::TYPE_ISSUE, (int) $advance->amount_minor, [
                'advance_id' => $advance->id,
            ]);

            $advance->issued_at = $advance->issued_at ?? now();
            $advance->closed_at = null;
            $advance->save();
        });
    }

    public function spentMinor(Advance $advance): int
    {
        return (int) $advance->expenses()->sum('amount_minor');
    }

    public function remainingMinor(Advance $advance): int
    {
        return (int) $advance->amount_minor - $this->spentMinor($advance);
    }

    public function releaseToFree(Advance $advance): void
    {
        DB::transaction(function () use ($advance) {
            $advance = Advance::whereKey($advance->id)->lockForUpdate()->with('status')->firstOrFail();
            $this->assertOpenForSettle($advance);

            $rem = $this->remainingMinor($advance);
            if ($rem <= 0) {
                throw new InvalidArgumentException('Нет остатка для переноса');
            }

            $this->ledger->apply($advance->user, WalletTransaction::TYPE_RELEASE, 0, [
                'advance_id' => $advance->id,
                'meta' => ['released_minor' => $rem],
            ]);

            $this->markClosed($advance);
        });
    }

    public function returnToBoss(Advance $advance): void
    {
        DB::transaction(function () use ($advance) {
            $advance = Advance::whereKey($advance->id)->lockForUpdate()->with('status')->firstOrFail();
            $this->assertOpenForSettle($advance);

            $rem = $this->remainingMinor($advance);
            if ($rem <= 0) {
                throw new InvalidArgumentException('Нет остатка для возврата');
            }

            $this->ledger->apply($advance->user, WalletTransaction::TYPE_RETURN, -$rem, [
                'advance_id' => $advance->id,
            ]);

            $this->markClosed($advance);
        });
    }

    public function writeOffUnknown(Advance $advance): void
    {
        DB::transaction(function () use ($advance) {
            $advance = Advance::whereKey($advance->id)->lockForUpdate()->with('status')->firstOrFail();
            $this->assertOpenForSettle($advance);

            $rem = $this->remainingMinor($advance);
            if ($rem <= 0) {
                throw new InvalidArgumentException('Нет остатка для списания');
            }

            $this->ledger->apply($advance->user, WalletTransaction::TYPE_WRITEOFF, -$rem, [
                'advance_id' => $advance->id,
            ]);

            $this->markClosed($advance);
        });
    }

    public function maybeAutoClose(Advance $advance): void
    {
        $advance->refresh();
        $advance->load('status');

        if (! in_array($advance->status?->slug, ['issued', 'reporting'], true)) {
            return;
        }

        if ($this->remainingMinor($advance) === 0) {
            $this->markClosed($advance);
        }
    }

    public function markReportingIfNeeded(Advance $advance): void
    {
        $advance->loadMissing('status');
        if ($advance->status?->slug === 'issued') {
            $advance->status_id = DictionaryResolver::advanceStatusId('reporting');
            $advance->save();
        }
    }

    protected function markClosed(Advance $advance): void
    {
        $advance->status_id = DictionaryResolver::advanceStatusId('closed');
        $advance->closed_at = now();
        $advance->save();
    }

    protected function assertOpenForSettle(Advance $advance): void
    {
        $slug = $advance->status?->slug;
        if (! in_array($slug, ['issued', 'reporting'], true)) {
            throw new InvalidArgumentException('Закрытие доступно только для выданных авансов');
        }
        if (! $this->ledger->hasIssue($advance->id)) {
            throw new InvalidArgumentException('Аванс ещё не выдавался');
        }
    }

    protected function assertTransition(?string $from, string $to): void
    {
        if ($from === null || $from === $to) {
            return;
        }

        $allowed = self::TRANSITIONS[$from] ?? [];
        if (! in_array($to, $allowed, true)) {
            throw new InvalidArgumentException("Нельзя перейти из «{$from}» в «{$to}»");
        }
    }

    /**
     * @return list<int>|null
     */
    protected function normalizeTaskIds(array $data, User $user): ?array
    {
        if (array_key_exists('task_ids', $data)) {
            $ids = collect($data['task_ids'] ?? [])->filter()->map(fn ($id) => (int) $id)->unique()->values();
        } elseif (array_key_exists('task_id', $data)) {
            $ids = collect($data['task_id'] !== null && $data['task_id'] !== '' ? [(int) $data['task_id']] : []);
        } else {
            return null;
        }

        if ($ids->isEmpty()) {
            return [];
        }

        $owned = ($user->is_admin
            ? Task::query()
            : $user->tasks()
        )->whereIn('id', $ids)->pluck('id')->all();
        if (count($owned) !== $ids->count()) {
            throw new InvalidArgumentException('Поручение не найдено');
        }

        return $ids->all();
    }
}
