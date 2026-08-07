<?php

namespace App\Http\Controllers;

use App\Models\Advance;
use App\Models\Expense;
use App\Models\Receipt;
use App\Models\WalletTransaction;
use App\Services\AdvanceService;
use App\Services\ExpenseService;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Throwable;

class FinanceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Finance/Index');
    }

    public function topUp(Request $request, WalletService $wallets): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'disbursement_method_id' => ['required', 'string'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        try {
            $wallets->topUp($request->user(), $data);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        return back();
    }

    public function updateTopUp(Request $request, WalletTransaction $transaction, WalletService $wallets): RedirectResponse
    {
        $transaction->loadMissing('wallet');
        abort_unless($request->user()?->canAccessOwned($transaction->wallet?->user_id), 403);

        $data = $request->validate([
            'amount' => ['sometimes', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'disbursement_method_id' => ['sometimes', 'string'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        try {
            $wallets->updateTopUp($request->user(), $transaction, $data);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        return back();
    }

    public function storeAdvance(Request $request, AdvanceService $advances): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'task_ids' => ['nullable', 'array'],
            'task_ids.*' => ['integer', 'exists:tasks,id'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'disbursement_method_id' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
            'needed_at' => ['nullable', 'date'],
        ]);

        try {
            $advance = $advances->create($request->user(), $data);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['advance' => $e->getMessage()]);
        }

        return back()->with('created_advance_id', $advance->id);
    }

    public function updateAdvance(Request $request, Advance $advance, AdvanceService $advances): RedirectResponse
    {
        abort_unless($request->user()?->canAccessOwned($advance->user_id), 403);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'task_ids' => ['nullable', 'array'],
            'task_ids.*' => ['integer', 'exists:tasks,id'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'disbursement_method_id' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
            'needed_at' => ['nullable', 'date'],
        ]);

        try {
            $advances->update($advance, $data);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['advance' => $e->getMessage()]);
        }

        return back();
    }

    public function destroyAdvance(Request $request, Advance $advance, AdvanceService $advances): RedirectResponse
    {
        abort_unless($request->user()?->canAccessOwned($advance->user_id), 403);

        try {
            $advances->destroy($advance);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['advance' => $e->getMessage()]);
        }

        return back();
    }

    public function approveAdvance(Request $request, Advance $advance, AdvanceService $advances): RedirectResponse
    {
        abort_unless($request->user()?->canAccessOwned($advance->user_id), 403);

        try {
            $advances->approve($advance);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['advance' => $e->getMessage()]);
        }

        return back();
    }

    public function receiveAdvance(Request $request, Advance $advance, AdvanceService $advances): RedirectResponse
    {
        abort_unless($request->user()?->canAccessOwned($advance->user_id), 403);

        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'gt:0'],
            'disbursement_method_id' => ['nullable', 'string'],
            'issued_at' => ['nullable', 'date'],
        ]);

        try {
            $advances->receive($advance, $data);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['advance' => $e->getMessage()]);
        }

        return back();
    }

    public function destroyTransaction(
        Request $request,
        WalletTransaction $transaction,
        WalletService $wallets,
        ExpenseService $expenses,
    ): RedirectResponse {
        $transaction->loadMissing('wallet');
        abort_unless($request->user()?->canAccessOwned($transaction->wallet?->user_id), 403);

        try {
            if (
                $transaction->type === WalletTransaction::TYPE_INCOME
                && $transaction->account === WalletTransaction::ACCOUNT_WALLET
            ) {
                $wallets->destroyIncome($request->user(), $transaction);
            } elseif (
                $transaction->type === WalletTransaction::TYPE_EXPENSE
                && $transaction->expense_id
            ) {
                $expense = Expense::query()->findOrFail($transaction->expense_id);
                abort_unless($request->user()?->canAccessOwned($expense->user_id), 403);
                $expenses->destroyExpense($request->user(), $expense);
            } else {
                throw new InvalidArgumentException('Эту проводку удалите через связанный аванс.');
            }
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['transaction' => $e->getMessage()]);
        }

        return back();
    }

    public function closeToWallet(Request $request, Advance $advance, AdvanceService $advances): RedirectResponse
    {
        return $this->closeAction($request, $advance, fn () => $advances->closeToWallet($advance));
    }

    public function attachExpense(Request $request, Advance $advance, Expense $expense, ExpenseService $expenses): RedirectResponse
    {
        abort_unless($request->user()?->canAccessOwned($advance->user_id), 403);
        abort_unless($request->user()?->canAccessOwned($expense->user_id), 403);

        $data = $request->validate([
            'debit_account' => ['nullable', Rule::in(['wallet', 'advance'])],
        ]);

        try {
            $expenses->attachToAdvance($advance, $expense, $data['debit_account'] ?? null);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['expense' => $e->getMessage()]);
        }

        return back();
    }

    public function detachExpense(Request $request, Advance $advance, Expense $expense, ExpenseService $expenses): RedirectResponse
    {
        abort_unless($request->user()?->canAccessOwned($advance->user_id), 403);
        abort_unless($request->user()?->canAccessOwned($expense->user_id), 403);

        try {
            $expenses->detachFromAdvance($advance, $expense);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['expense' => $e->getMessage()]);
        }

        return back();
    }

    public function storeFreeExpense(Request $request, ExpenseService $expenses): RedirectResponse
    {
        return $this->storeExpense($request, $expenses, null);
    }

    public function storeExpense(Request $request, ExpenseService $expenses, ?Advance $advance = null): RedirectResponse
    {
        if ($advance) {
            abort_unless($request->user()?->canAccessOwned($advance->user_id), 403);
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'article_id' => ['required', 'string'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'advance_id' => ['nullable', 'integer', 'exists:advances,id'],
            'debit_account' => ['nullable', Rule::in(['wallet', 'advance', 'unassigned'])],
            'occurred_at' => ['nullable', 'date'],
            'receipts' => ['nullable', 'array'],
            'receipts.*' => ['file', 'max:20480'],
        ]);

        $target = $advance;
        if (! $target && ! empty($data['advance_id'])) {
            $target = Advance::query()->findOrFail($data['advance_id']);
            abort_unless($request->user()?->canAccessOwned($target->user_id), 403);
        }

        $data['receipt_files'] = collect($request->file('receipts') ?? [])
            ->filter(fn ($file) => $file instanceof \Illuminate\Http\UploadedFile)
            ->values()
            ->all();

        try {
            $expenses->addExpense($request->user(), $data, $target);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        return back();
    }

    public function updateExpense(Request $request, Expense $expense, ExpenseService $expenses): RedirectResponse
    {
        abort_unless($request->user()?->canAccessOwned($expense->user_id), 403);

        $data = $request->validate([
            'amount' => ['sometimes', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'article_id' => ['sometimes', 'string'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'debit_account' => ['sometimes', Rule::in(['wallet', 'advance', 'unassigned'])],
            'occurred_at' => ['nullable', 'date'],
        ]);

        try {
            $expenses->updateExpense($request->user(), $expense, $data);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        return back();
    }

    public function destroyExpense(Request $request, Expense $expense, ExpenseService $expenses): RedirectResponse
    {
        abort_unless($request->user()?->canAccessOwned($expense->user_id), 403);

        try {
            $expenses->destroyExpense($request->user(), $expense);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['expense' => $e->getMessage()]);
        }

        return back();
    }

    public function storeReceipt(
        Request $request,
        Expense $expense,
        ExpenseService $expenses,
    ): RedirectResponse {
        abort_unless($request->user()?->canAccessOwned($expense->user_id), 403);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:20480'],
        ]);

        $expenses->addReceipt($expense, $data['file']);

        return back();
    }

    public function storeAdvanceReceipt(
        Request $request,
        Advance $advance,
        Expense $expense,
        ExpenseService $expenses,
    ): RedirectResponse {
        abort_unless($request->user()?->canAccessOwned($advance->user_id), 403);
        abort_unless($expense->advance_id === $advance->id, 404);

        return $this->storeReceipt($request, $expense, $expenses);
    }

    public function destroyReceipt(
        Request $request,
        Expense $expense,
        Receipt $receipt,
        ExpenseService $expenses,
    ): RedirectResponse {
        abort_unless($request->user()?->canAccessOwned($expense->user_id), 403);
        abort_unless($receipt->expense_id === $expense->id, 404);

        $expenses->destroyReceipt($receipt);

        return back();
    }

    protected function closeAction(Request $request, Advance $advance, callable $action): RedirectResponse
    {
        abort_unless($request->user()?->canAccessOwned($advance->user_id), 403);

        try {
            $action();
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['advance' => $e->getMessage()]);
        } catch (Throwable $e) {
            throw $e;
        }

        return back();
    }
}
