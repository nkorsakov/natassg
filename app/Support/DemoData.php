<?php

namespace App\Support;

use App\Models\Advance;
use App\Models\CalendarEvent;
use App\Models\Comment;
use App\Models\Contact;
use App\Models\Expense;
use App\Models\ManagerReport;
use App\Models\Receipt;
use App\Models\Reminder;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
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
     * Сносит workspace всех is_demo пользователей + остатки is_demo=true.
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
                'comments' => 0,
                'tasks' => 0,
                'events' => 0,
                'suppliers' => 0,
                'contacts' => 0,
                'reports' => 0,
            ];

            foreach (User::query()->where('is_demo', true)->cursor() as $user) {
                $userStats = self::clearUserWorkspace($user);
                foreach ($userStats as $key => $count) {
                    $stats[$key] = ($stats[$key] ?? 0) + $count;
                }
            }

            // Хвосты старых is_demo (например, ранее на nataliya)
            $legacy = self::clearMarkedDemoRows();
            foreach ($legacy as $key => $count) {
                $stats[$key] = ($stats[$key] ?? 0) + $count;
            }

            self::recalculateWallets();

            return $stats;
        });
    }

    /**
     * Полный снос workspace одного пользователя (не только is_demo на сущностях).
     */
    public static function clearUserWorkspace(User $user): array
    {
        $stats = [
            'receipts' => 0,
            'wallet_transactions' => 0,
            'expenses' => 0,
            'advances' => 0,
            'reminders' => 0,
            'attachments' => 0,
            'comments' => 0,
            'tasks' => 0,
            'events' => 0,
            'suppliers' => 0,
            'contacts' => 0,
            'reports' => 0,
        ];

        $expenseIds = Expense::query()->where('user_id', $user->id)->pluck('id');
        $taskIds = Task::withTrashed()->where('user_id', $user->id)->pluck('id');
        $advanceIds = Advance::query()->where('user_id', $user->id)->pluck('id');
        $eventIds = CalendarEvent::withTrashed()->where('user_id', $user->id)->pluck('id');
        $walletIds = Wallet::query()->where('user_id', $user->id)->pluck('id');

        if ($expenseIds->isNotEmpty()) {
            $receipts = Receipt::query()->whereIn('expense_id', $expenseIds)->get();
            foreach ($receipts as $receipt) {
                if ($receipt->path) {
                    Storage::disk('public')->delete($receipt->path);
                }
                $receipt->delete();
                $stats['receipts']++;
            }
        }

        $attachments = TaskAttachment::query()
            ->where(function ($q) use ($user, $taskIds) {
                $q->where('user_id', $user->id);
                if ($taskIds->isNotEmpty()) {
                    $q->orWhereIn('task_id', $taskIds);
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

        $stats['reminders'] = Reminder::query()->where('user_id', $user->id)->delete();

        if ($taskIds->isNotEmpty()) {
            $stats['comments'] = Comment::query()
                ->where('commentable_type', (new Task)->getMorphClass())
                ->whereIn('commentable_id', $taskIds)
                ->delete();
        }

        if ($walletIds->isNotEmpty()) {
            $stats['wallet_transactions'] = WalletTransaction::query()
                ->whereIn('wallet_id', $walletIds)
                ->delete();
        }

        if ($advanceIds->isNotEmpty()) {
            DB::table('advance_task')->whereIn('advance_id', $advanceIds)->delete();
        }
        if ($taskIds->isNotEmpty()) {
            DB::table('advance_task')->whereIn('task_id', $taskIds)->delete();
            DB::table('task_event')->whereIn('task_id', $taskIds)->delete();
        }
        if ($eventIds->isNotEmpty()) {
            DB::table('task_event')->whereIn('event_id', $eventIds)->delete();
        }

        $stats['expenses'] = Expense::query()->where('user_id', $user->id)->delete();
        $stats['advances'] = Advance::query()->where('user_id', $user->id)->delete();

        $stats['tasks'] += Task::withTrashed()
            ->where('user_id', $user->id)
            ->whereNotNull('parent_id')
            ->forceDelete();
        $stats['tasks'] += Task::withTrashed()
            ->where('user_id', $user->id)
            ->forceDelete();

        $stats['events'] = CalendarEvent::withTrashed()
            ->where('user_id', $user->id)
            ->forceDelete();

        $stats['suppliers'] = Supplier::query()->where('user_id', $user->id)->delete();
        $stats['contacts'] = Contact::withTrashed()->where('user_id', $user->id)->forceDelete();
        $stats['reports'] = ManagerReport::query()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere('created_by', $user->id);
            })
            ->delete();

        return $stats;
    }

    /**
     * Удаляет оставшиеся записи с is_demo=true (legacy / чужие хвосты).
     */
    protected static function clearMarkedDemoRows(): array
    {
        $stats = [
            'receipts' => 0,
            'wallet_transactions' => 0,
            'expenses' => 0,
            'advances' => 0,
            'reminders' => 0,
            'attachments' => 0,
            'comments' => 0,
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

        if ($demoTaskIds->isNotEmpty()) {
            $stats['comments'] = Comment::query()
                ->where('commentable_type', (new Task)->getMorphClass())
                ->whereIn('commentable_id', $demoTaskIds)
                ->delete();
        }

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

        return $stats;
    }

    public static function recalculateWallets(): void
    {
        foreach (Wallet::query()->cursor() as $wallet) {
            $sum = (int) WalletTransaction::query()
                ->where('wallet_id', $wallet->id)
                ->where('account', WalletTransaction::ACCOUNT_WALLET)
                ->sum('amount_minor');
            $wallet->balance_minor = $sum;
            $wallet->save();
        }
    }
}
