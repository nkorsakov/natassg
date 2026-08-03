<?php

namespace App\Services;

use App\Models\Advance;
use App\Models\Expense;
use App\Models\Receipt;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\DictionaryResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ExpenseService
{
    public function __construct(
        protected WalletLedger $ledger,
        protected AdvanceService $advances,
    ) {}

    public function addExpense(User $user, array $data, ?Advance $advance = null): Expense
    {
        return DB::transaction(function () use ($user, $data, $advance) {
            if ($advance) {
                $advance = Advance::whereKey($advance->id)->lockForUpdate()->with('status')->firstOrFail();
                if (! $user->canAccessOwned($advance->user_id)) {
                    throw new InvalidArgumentException('Чужой аванс');
                }
            }

            $amountMinor = array_key_exists('amount_minor', $data)
                ? (int) $data['amount_minor']
                : DictionaryResolver::rublesToMinor($data['amount'] ?? 0);

            if ($amountMinor <= 0) {
                throw new InvalidArgumentException('Сумма траты должна быть больше нуля');
            }

            $articleId = DictionaryResolver::expenseArticleId($data['article_id'] ?? null);
            if (! $articleId) {
                throw new InvalidArgumentException('Укажите статью расхода');
            }

            $owner = $this->resolveOwner($user, $advance?->user_id);
            $supplierId = $this->resolveSupplier($owner, $data['supplier_id'] ?? null);

            $taskId = null;
            if (! empty($data['task_id'])) {
                $taskId = (int) $data['task_id'];
                $task = Task::query()->find($taskId);
                if (! $task || ! $user->canAccessOwned($task->user_id)) {
                    throw new InvalidArgumentException('Поручение не найдено');
                }
            }

            $expense = Expense::create([
                'user_id' => $owner->id,
                'advance_id' => $advance?->id,
                'article_id' => $articleId,
                'supplier_id' => $supplierId,
                'task_id' => $taskId,
                'amount_minor' => $amountMinor,
                'description' => $data['description'] ?? null,
            ]);

            $this->ledger->apply($owner, WalletTransaction::TYPE_EXPENSE, -$amountMinor, [
                'advance_id' => $advance?->id,
                'expense_id' => $expense->id,
            ]);

            if ($advance) {
                $this->advances->markReportingIfNeeded($advance);
                $this->advances->maybeAutoClose($advance);
            }

            return $expense->fresh(['receipts', 'article', 'supplier']);
        });
    }

    public function updateExpense(User $user, Expense $expense, array $data): Expense
    {
        return DB::transaction(function () use ($user, $expense, $data) {
            $expense = Expense::whereKey($expense->id)->lockForUpdate()->firstOrFail();
            if (! $user->canAccessOwned($expense->user_id)) {
                throw new InvalidArgumentException('Чужая трата');
            }

            $advance = $expense->advance_id
                ? Advance::whereKey($expense->advance_id)->lockForUpdate()->with('status')->first()
                : null;

            $oldAmount = (int) $expense->amount_minor;

            if (array_key_exists('article_id', $data)) {
                $articleId = DictionaryResolver::expenseArticleId($data['article_id']);
                if (! $articleId) {
                    throw new InvalidArgumentException('Укажите статью расхода');
                }
                $expense->article_id = $articleId;
            }

            if (array_key_exists('supplier_id', $data)) {
                $expense->supplier_id = $this->resolveSupplier($user, $data['supplier_id']);
            }

            if (array_key_exists('description', $data)) {
                $expense->description = $data['description'];
            }

            if (array_key_exists('task_id', $data)) {
                if ($data['task_id'] === null || $data['task_id'] === '') {
                    $expense->task_id = null;
                } else {
                    $taskId = (int) $data['task_id'];
                    $task = Task::query()->find($taskId);
                    if (! $task || ! $user->canAccessOwned($task->user_id)) {
                        throw new InvalidArgumentException('Поручение не найдено');
                    }
                    $expense->task_id = $taskId;
                }
            }

            $newAmount = null;
            if (array_key_exists('amount_minor', $data)) {
                $newAmount = (int) $data['amount_minor'];
            } elseif (array_key_exists('amount', $data)) {
                $newAmount = DictionaryResolver::rublesToMinor($data['amount']);
            }

            if ($newAmount !== null) {
                if ($newAmount <= 0) {
                    throw new InvalidArgumentException('Сумма траты должна быть больше нуля');
                }
                $delta = $oldAmount - $newAmount; // positive => money back to wallet
                $expense->amount_minor = $newAmount;
                $expense->save();

                if ($delta !== 0) {
                    $owner = $this->resolveOwner($user, $expense->user_id);
                    $this->ledger->apply($owner, WalletTransaction::TYPE_EXPENSE, $delta, [
                        'advance_id' => $expense->advance_id,
                        'expense_id' => $expense->id,
                        'meta' => ['reason' => 'expense_update'],
                    ]);
                }
            } else {
                $expense->save();
            }

            if ($advance) {
                if (in_array($advance->status?->slug, ['closed'], true) && $this->advances->remainingMinor($advance) !== 0) {
                    $advance->status_id = DictionaryResolver::advanceStatusId('reporting');
                    $advance->closed_at = null;
                    $advance->save();
                }
                $this->advances->maybeAutoClose($advance->fresh('status'));
            }

            return $expense->fresh(['receipts', 'article', 'supplier']);
        });
    }

    public function destroyExpense(User $user, Expense $expense): void
    {
        DB::transaction(function () use ($user, $expense) {
            $expense = Expense::whereKey($expense->id)->lockForUpdate()->with('receipts')->firstOrFail();
            if (! $user->canAccessOwned($expense->user_id)) {
                throw new InvalidArgumentException('Чужая трата');
            }

            $advance = $expense->advance_id
                ? Advance::whereKey($expense->advance_id)->lockForUpdate()->with('status')->first()
                : null;

            $amount = (int) $expense->amount_minor;
            $owner = $this->resolveOwner($user, $expense->user_id);

            $this->ledger->apply($owner, WalletTransaction::TYPE_EXPENSE, $amount, [
                'advance_id' => $expense->advance_id,
                'expense_id' => $expense->id,
                'meta' => ['reason' => 'expense_delete'],
            ]);

            foreach ($expense->receipts as $receipt) {
                Storage::disk('public')->delete($receipt->path);
                $receipt->delete();
            }

            $expense->delete();

            if ($advance && $advance->status?->slug === 'closed') {
                $advance->status_id = DictionaryResolver::advanceStatusId('reporting');
                $advance->closed_at = null;
                $advance->save();
            }
        });
    }

    public function addReceipt(Expense $expense, UploadedFile $file): Receipt
    {
        $dir = 'receipts/'.($expense->advance_id ?: 'free')."/{$expense->id}";
        $name = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs($dir, $name, 'public');

        return Receipt::create([
            'expense_id' => $expense->id,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize() ?: 0,
        ]);
    }

    public function destroyReceipt(Receipt $receipt): void
    {
        Storage::disk('public')->delete($receipt->path);
        $receipt->delete();
    }

    protected function resolveOwner(User $actor, ?int $ownerId): User
    {
        if ($ownerId === null || (int) $ownerId === (int) $actor->id) {
            return $actor;
        }

        return User::query()->findOrFail($ownerId);
    }

    protected function resolveSupplier(User $user, mixed $supplierId): int
    {
        if ($supplierId === null || $supplierId === '') {
            throw new InvalidArgumentException('Укажите поставщика');
        }

        $query = Supplier::query()->whereKey((int) $supplierId);
        if (! $user->is_admin) {
            $query->where('user_id', $user->id);
        }

        $supplier = $query->first();

        if (! $supplier) {
            throw new InvalidArgumentException('Поставщик не найден');
        }

        return (int) $supplier->id;
    }
}
