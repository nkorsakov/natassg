<?php

namespace App\Http\Controllers;

use App\Models\ManagerReport;
use App\Services\ManagerReportBuilder;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request, ManagerReportBuilder $builder): Response
    {
        $user = $request->user();

        $data = $request->validate([
            'period_from' => ['nullable', 'date'],
            'period_to' => ['nullable', 'date', 'after_or_equal:period_from'],
        ]);

        [$from, $to] = $this->resolvePeriod($data['period_from'] ?? null, $data['period_to'] ?? null);

        $preview = $builder->buildPayload($user, $from, $to);

        $recent = ManagerReport::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(30)
            ->get()
            ->map(fn (ManagerReport $r) => $this->summary($r));

        return Inertia::render('Reports/Compose', [
            'period_from' => $from->toDateString(),
            'period_to' => $to->toDateString(),
            'preview' => $preview,
            'recent' => $recent,
            'created' => $request->session()->get('created_report'),
            'creating' => $request->boolean('creating'),
        ]);
    }

    public function store(Request $request, ManagerReportBuilder $builder): RedirectResponse
    {
        $data = $request->validate([
            'period_from' => ['required', 'date'],
            'period_to' => ['required', 'date', 'after_or_equal:period_from'],
            'exclude_task_ids' => ['nullable', 'array'],
            'exclude_task_ids.*' => ['integer'],
        ]);

        $user = $request->user();
        $from = Carbon::parse($data['period_from'])->startOfDay();
        $to = Carbon::parse($data['period_to'])->startOfDay();
        $exclude = array_values(array_unique(array_map('intval', $data['exclude_task_ids'] ?? [])));

        $report = $builder->create($user, $from, $to, $user, $exclude);

        return redirect()
            ->route('reports.index')
            ->with('created_report', [
                'report' => $this->summary($report),
                'url' => $report->publicUrl(),
            ]);
    }

    public function show(string $token): Response
    {
        $report = ManagerReport::query()->where('token', $token)->firstOrFail();

        return Inertia::render('Reports/Public', [
            'report' => [
                'token' => $report->token,
                'period_from' => $report->period_from?->toDateString(),
                'period_to' => $report->period_to?->toDateString(),
                'created_at' => optional($report->created_at)?->toIso8601String(),
                'payload' => $report->payload,
            ],
        ]);
    }

    public function destroy(Request $request, ManagerReport $report): RedirectResponse|JsonResponse
    {
        abort_unless((int) $report->user_id === (int) $request->user()->id, 403);

        $report->delete();

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['ok' => true]);
        }

        return back();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function resolvePeriod(?string $from, ?string $to): array
    {
        if ($from && $to) {
            return [
                Carbon::parse($from)->startOfDay(),
                Carbon::parse($to)->startOfDay(),
            ];
        }

        $today = now()->startOfDay();
        $start = $today->copy()->startOfWeek(Carbon::MONDAY);
        $end = $today->copy()->endOfWeek(Carbon::SUNDAY)->startOfDay();

        return [$start, $end];
    }

    /**
     * @return array<string, mixed>
     */
    protected function summary(ManagerReport $report): array
    {
        $payload = $report->payload ?? [];
        $finance = $payload['finance'] ?? [];
        $work = $payload['work'] ?? [];

        return [
            'id' => $report->id,
            'token' => $report->token,
            'url' => $report->publicUrl(),
            'period_from' => $report->period_from?->toDateString(),
            'period_to' => $report->period_to?->toDateString(),
            'created_at' => optional($report->created_at)?->toIso8601String(),
            'summary' => [
                'closed_count' => count($work['closed'] ?? []),
                'active_count' => count($work['active'] ?? []),
                'events_count' => count($work['events'] ?? []),
                'opening_on_hand' => $finance['opening_on_hand'] ?? 0,
                'closing_on_hand' => $finance['closing_on_hand'] ?? 0,
                'income_total' => $finance['income_total'] ?? 0,
                'expense_total' => $finance['expense_total'] ?? 0,
            ],
        ];
    }
}
