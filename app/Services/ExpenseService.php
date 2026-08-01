<?php

namespace App\Services;

use App\Models\Advance;
use App\Models\Contact;
use App\Models\Expense;
use App\Models\Receipt;
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
                if ($advance->user_id !== $user->id) {
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

            $supplierId = $this->resolveSupplier($user, $data['supplier_contact_id'] ?? null);

            $taskId = null;
            if (! empty($data['task_id'])) {
                $taskId = (int) $data['task_id'];
                if (! $user->tasks()->whereKey($taskId)->exists()) {
                    throw new InvalidArgumentException('Поручение не найдено');
                }
            }

            $expense = Expense::create([
                'user_id' => $user->id,
                'advance_id' => $advance?->id,
                'article_id' => $articleId,
                'supplier_contact_id' => $supplierId,
                'task_id' => $taskId,
                'amount_minor' => $amountMinor,
                'description' => $data['description'] ?? null,
            ]);

            $this->ledger->apply($user, WalletTransaction::TYPE_EXPENSE, -$amountMinor, [
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
            if ($expense->user_id !== $user->id) {
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

            if (array_key_exists('supplier_contact_id', $data)) {
                $expense->supplier_contact_id = $this->resolveSupplier($user, $data['supplier_contact_id']);
            }

            if (array_key_exists('description', $data)) {
                $expense->description = $data['description'];
            }

            if (array_key_exists('task_id', $data)) {
                if ($data['task_id'] === null || $data['task_id'] === '') {
                    $expense->task_id = null;
                } else {
                    $taskId = (int) $data['task_id'];
                    if (! $user->tasks()->whereKey($taskId)->exists()) {
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
                    $this->ledger->apply($user, WalletTransaction::TYPE_EXPENSE, $delta, [
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
            if ($expense->user_id !== $user->id) {
                throw new InvalidArgumentException('Чужая трата');
            }

            $advance = $expense->advance_id
                ? Advance::whereKey($expense->advance_id)->lockForUpdate()->with('status')->first()
                : null;

            $amount = (int) $expense->amount_minor;

            $this->ledger->apply($user, WalletTransaction::TYPE_EXPENSE, $amount, [
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

    protected function resolveSupplier(User $user, mixed $contactId): int
    {
        if ($contactId === null || $contactId === '') {
            throw new InvalidArgumentException('Укажите поставщика');
        }

        $contact = Contact::query()
            ->where('user_id', $user->id)
            ->whereKey((int) $contactId)
            ->first();

        if (! $contact) {
            throw new InvalidArgumentException('Поставщик не найден');
        }

        if (! $contact->is_supplier) {
            $contact->is_supplier = true;
            $contact->save();
        }

        return (int) $contact->id;
    }
}
