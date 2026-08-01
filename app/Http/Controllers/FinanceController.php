<?php

namespace App\Http\Controllers;

use App\Models\Advance;
use App\Models\Expense;
use App\Models\Receipt;
use App\Services\AdvanceService;
use App\Services\ExpenseService;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        ]);

        try {
            $wallets->topUp($request->user(), $data);
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
            'status_id' => ['nullable', 'string'],
            'disbursement_method_id' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
        ]);

        try {
            $advance = $advances->create($request->user(), $data);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['status_id' => $e->getMessage()]);
        }

        return back()->with('created_advance_id', $advance->id);
    }

    public function updateAdvance(Request $request, Advance $advance, AdvanceService $advances): RedirectResponse
    {
        abort_unless($advance->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'task_ids' => ['nullable', 'array'],
            'task_ids.*' => ['integer', 'exists:tasks,id'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'status_id' => ['nullable', 'string'],
            'disbursement_method_id' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
        ]);

        try {
            $advances->update($advance, $data);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['status_id' => $e->getMessage()]);
        }

        return back();
    }

    public function release(Request $request, Advance $advance, AdvanceService $advances): RedirectResponse
    {
        return $this->settleAction($request, $advance, fn () => $advances->releaseToFree($advance));
    }

    public function returnRemainder(Request $request, Advance $advance, AdvanceService $advances): RedirectResponse
    {
        return $this->settleAction($request, $advance, fn () => $advances->returnToBoss($advance));
    }

    public function writeOff(Request $request, Advance $advance, AdvanceService $advances): RedirectResponse
    {
        return $this->settleAction($request, $advance, fn () => $advances->writeOffUnknown($advance));
    }

    public function storeExpense(Request $request, ExpenseService $expenses, ?Advance $advance = null): RedirectResponse
    {
        if ($advance) {
            abort_unless($advance->user_id === $request->user()->id, 403);
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'article_id' => ['required', 'string'],
            'supplier_contact_id' => ['required', 'integer', 'exists:contacts,id'],
            'task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'advance_id' => ['nullable', 'integer', 'exists:advances,id'],
        ]);

        $target = $advance;
        if (! $target && ! empty($data['advance_id'])) {
            $target = Advance::query()->findOrFail($data['advance_id']);
            abort_unless($target->user_id === $request->user()->id, 403);
        }

        try {
            $expenses->addExpense($request->user(), $data, $target);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        return back();
    }

    public function updateExpense(Request $request, Expense $expense, ExpenseService $expenses): RedirectResponse
    {
        abort_unless($expense->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'amount' => ['sometimes', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'article_id' => ['sometimes', 'string'],
            'supplier_contact_id' => ['sometimes', 'integer', 'exists:contacts,id'],
            'task_id' => ['nullable', 'integer', 'exists:tasks,id'],
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
        abort_unless($expense->user_id === $request->user()->id, 403);

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
        abort_unless($expense->user_id === $request->user()->id, 403);

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
        abort_unless($advance->user_id === $request->user()->id, 403);
        abort_unless($expense->advance_id === $advance->id, 404);

        return $this->storeReceipt($request, $expense, $expenses);
    }

    public function destroyReceipt(
        Request $request,
        Expense $expense,
        Receipt $receipt,
        ExpenseService $expenses,
    ): RedirectResponse {
        abort_unless($expense->user_id === $request->user()->id, 403);
        abort_unless($receipt->expense_id === $expense->id, 404);

        $expenses->destroyReceipt($receipt);

        return back();
    }

    protected function settleAction(Request $request, Advance $advance, callable $action): RedirectResponse
    {
        abort_unless($advance->user_id === $request->user()->id, 403);

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
