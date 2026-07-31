<?php

namespace App\Http\Controllers;

use App\Models\Advance;
use App\Models\Expense;
use App\Services\AdvanceService;
use App\Services\ExpenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinanceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Finance/Index');
    }

    public function storeAdvance(Request $request, AdvanceService $advances): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'status_id' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
        ]);

        $advance = $advances->create($request->user(), $data);

        return back()->with('created_advance_id', $advance->id);
    }

    public function updateAdvance(Request $request, Advance $advance, AdvanceService $advances): RedirectResponse
    {
        abort_unless($advance->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'status_id' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
        ]);

        $advances->update($advance, $data);

        return back();
    }

    public function returnRemainder(Request $request, Advance $advance, AdvanceService $advances): RedirectResponse
    {
        abort_unless($advance->user_id === $request->user()->id, 403);
        $advances->returnRemainder($advance);

        return back();
    }

    public function zeroUnknown(Request $request, Advance $advance, AdvanceService $advances): RedirectResponse
    {
        abort_unless($advance->user_id === $request->user()->id, 403);
        $advances->zeroAsUnknown($advance);

        return back();
    }

    public function overspend(Request $request, Advance $advance, AdvanceService $advances): RedirectResponse
    {
        abort_unless($advance->user_id === $request->user()->id, 403);
        $advances->recordOverspend($advance);

        return back();
    }

    public function settle(Request $request, Advance $advance, AdvanceService $advances): RedirectResponse
    {
        abort_unless($advance->user_id === $request->user()->id, 403);
        $advances->settle($advance);

        return back();
    }

    public function storeExpense(Request $request, Advance $advance, ExpenseService $expenses): RedirectResponse
    {
        abort_unless($advance->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $expenses->addExpense($advance->load('status'), $data);

        return back();
    }

    public function storeReceipt(
        Request $request,
        Advance $advance,
        Expense $expense,
        ExpenseService $expenses,
    ): RedirectResponse {
        abort_unless($advance->user_id === $request->user()->id, 403);
        abort_unless($expense->advance_id === $advance->id, 404);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:20480'],
        ]);

        $expenses->addReceipt($expense, $data['file']);

        return back();
    }
}
