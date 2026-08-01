<?php

namespace App\Support;

use App\Models\Advance;
use App\Models\CalendarEvent;
use App\Models\Contact;
use App\Models\Expense;
use App\Models\Receipt;
use App\Models\Reminder;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DemoData
{
    public static function mark(Model $model): Model
    {
        $model->forceFill(['is_demo' => true])->save();

        return $model;
    }

    public static function markMany(iterable $models): void
    {
        foreach ($models as $model) {
            if ($model instanceof Model) {
                self::mark($model);
            }
        }
    }

    public static function markWalletForAdvance(Advance $advance): void
    {
        WalletTransaction::query()
            ->where('advance_id', $advance->id)
            ->update(['is_demo' => true]);
    }

    public static function markWalletForExpense(Expense $expense): void
    {
        WalletTransaction::query()
            ->where('expense_id', $expense->id)
            ->update(['is_demo' => true]);
    }

    /**
     * Удаляет только записи с is_demo=true и пересчитывает балансы кошельков.
     * Словари и пользователей не трогает. migrate:fresh НЕ использует.
     */
    public static function clear(): array
    {
        return DB::transaction(function () {
            $stats = [
                'receipts' => 0,
                'wallet_transactions' => 0,
                'expenses' => 0,
                'advances' => 0,
                'reminders' => 0,
                'attachments' => 0,
                'tasks' => 0,
                'events' => 0,
                'suppliers' => 0,
                'contacts' => 0,
            ];

            $demoExpenseIds = Expense::query()->where('is_demo', true)->pluck('id');
            $demoTaskIds = Task::withTrashed()->where('is_demo', true)->pluck('id');
            $demoAdvanceIds = Advance::query()->where('is_demo', true)->pluck('id');
            $demoEventIds = CalendarEvent::withTrashed()->where('is_demo', true)->pluck('id');

            $receipts = Receipt::query()
                ->where(function ($q) use ($demoExpenseIds) {
                    $q->where('is_demo', true);
                    if ($demoExpenseIds->isNotEmpty()) {
                        $q->orWhereIn('expense_id', $demoExpenseIds);
                    }
                })
                ->get();
            foreach ($receipts as $receipt) {
                if ($receipt->path) {
                    Storage::disk('public')->delete($receipt->path);
                }
                $receipt->delete();
                $stats['receipts']++;
            }

            $attachments = TaskAttachment::query()
                ->where(function ($q) use ($demoTaskIds) {
                    $q->where('is_demo', true);
                    if ($demoTaskIds->isNotEmpty()) {
                        $q->orWhereIn('task_id', $demoTaskIds);
                    }
                })
                ->get();
            foreach ($attachments as $attachment) {
                if ($attachment->path) {
                    Storage::disk('public')->delete($attachment->path);
                }
                $attachment->delete();
                $stats['attachments']++;
            }

            $stats['reminders'] = Reminder::query()
                ->where(function ($q) use ($demoTaskIds) {
                    $q->where('is_demo', true);
                    if ($demoTaskIds->isNotEmpty()) {
                        $q->orWhereIn('task_id', $demoTaskIds);
                    }
                })
                ->delete();

            $stats['wallet_transactions'] = WalletTransaction::query()
                ->where('is_demo', true)
                ->delete();

            if ($demoAdvanceIds->isNotEmpty()) {
                DB::table('advance_task')->whereIn('advance_id', $demoAdvanceIds)->delete();
            }
            if ($demoTaskIds->isNotEmpty()) {
                DB::table('advance_task')->whereIn('task_id', $demoTaskIds)->delete();
                DB::table('task_event')->whereIn('task_id', $demoTaskIds)->delete();
            }
            if ($demoEventIds->isNotEmpty()) {
                DB::table('task_event')->whereIn('event_id', $demoEventIds)->delete();
            }

            $stats['expenses'] = Expense::query()->where('is_demo', true)->delete();
            $stats['advances'] = Advance::query()->where('is_demo', true)->delete();

            $stats['tasks'] += Task::withTrashed()
                ->where('is_demo', true)
                ->whereNotNull('parent_id')
                ->forceDelete();
            $stats['tasks'] += Task::withTrashed()
                ->where('is_demo', true)
                ->forceDelete();

            $stats['events'] = CalendarEvent::withTrashed()
                ->where('is_demo', true)
                ->forceDelete();

            $stats['suppliers'] = Supplier::query()->where('is_demo', true)->delete();
            $stats['contacts'] = Contact::withTrashed()->where('is_demo', true)->forceDelete();

            self::recalculateWallets();

            return $stats;
        });
    }

    public static function recalculateWallets(): void
    {
        foreach (Wallet::query()->cursor() as $wallet) {
            $sum = (int) WalletTransaction::query()
                ->where('wallet_id', $wallet->id)
                ->sum('amount_minor');
            $wallet->balance_minor = $sum;
            $wallet->save();
        }
    }
}
