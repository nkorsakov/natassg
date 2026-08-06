<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\DisbursementMethod;
use App\Models\EventType;
use App\Models\ManagerReport;
use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\TaskType;
use App\Models\User;
use App\Services\ManagerReportBuilder;
use App\Services\ReportOnHandCalculator;
use App\Services\WalletService;
use Carbon\Carbon;
use Database\Seeders\DictionarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ManagerReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected DisbursementMethod $method;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DictionarySeeder::class);
        $this->user = User::factory()->create();
        $this->method = DisbursementMethod::query()->firstOrFail();
    }

    public function test_guest_can_view_public_report_by_token(): void
    {
        $report = app(ManagerReportBuilder::class)->create(
            $this->user,
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-07'),
            $this->user,
        );

        $this->get('/r/'.$report->token)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Reports/Public', false)
                ->where('report.token', $report->token)
                ->where('report.period_from', '2026-08-01')
                ->where('report.period_to', '2026-08-07')
            );
    }

    public function test_unknown_token_returns_404(): void
    {
        $this->get('/r/'.str_repeat('a', 48))->assertNotFound();
    }

    public function test_compose_page_shows_preview(): void
    {
        $this->actingAs($this->user)
            ->get('/reports?period_from=2026-08-01&period_to=2026-08-07')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Reports/Compose', false)
                ->where('period_from', '2026-08-01')
                ->where('period_to', '2026-08-07')
                ->where('creating', false)
                ->has('preview.work')
                ->has('preview.finance')
                ->has('recent')
            );
    }

    public function test_compose_creating_flag(): void
    {
        $this->actingAs($this->user)
            ->get('/reports?period_from=2026-08-01&period_to=2026-08-07&creating=1')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Reports/Compose', false)
                ->where('creating', true)
            );
    }

    public function test_auth_user_can_create_and_delete_own_report(): void
    {
        $create = $this->actingAs($this->user)->post('/reports', [
            'period_from' => '2026-08-01',
            'period_to' => '2026-08-07',
        ]);

        $create->assertRedirect();
        $create->assertSessionHas('created_report');

        $reportId = (int) ManagerReport::query()->value('id');
        $this->assertNotSame(0, $reportId);
        $token = (string) ManagerReport::query()->value('token');

        $this->actingAs($this->user)
            ->get('/reports?period_from=2026-08-01&period_to=2026-08-07')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Reports/Compose', false)
                ->has('recent', 1)
            );

        $this->actingAs($this->user)->deleteJson("/reports/{$reportId}")
            ->assertOk();

        $this->assertDatabaseMissing('manager_reports', ['id' => $reportId]);
        $this->get('/r/'.$token)->assertNotFound();
    }

    public function test_exclude_task_ids_omitted_from_payload(): void
    {
        $statusNew = TaskStatus::query()->where('slug', 'new')->value('id');
        $statusDone = TaskStatus::query()->where('slug', 'done')->value('id');
        $priority = TaskPriority::query()->where('slug', 'normal')->value('id');
        $type = TaskType::query()->where('slug', 'purchase')->value('id');

        $keep = Task::create([
            'user_id' => $this->user->id,
            'status_id' => $statusDone,
            'priority_id' => $priority,
            'type_id' => $type,
            'title' => 'Оставить',
            'closed_at' => '2026-08-03 15:00:00',
            'created_at' => '2026-07-20 10:00:00',
        ]);

        $drop = Task::create([
            'user_id' => $this->user->id,
            'status_id' => $statusNew,
            'priority_id' => $priority,
            'type_id' => $type,
            'title' => 'Убрать',
            'created_at' => '2026-08-02 10:00:00',
        ]);

        $this->actingAs($this->user)->post('/reports', [
            'period_from' => '2026-08-01',
            'period_to' => '2026-08-07',
            'exclude_task_ids' => [$drop->id],
        ])->assertRedirect();

        $payload = ManagerReport::query()->latest('id')->value('payload');
        $closedIds = collect($payload['work']['closed'] ?? [])->pluck('id')->all();
        $activeIds = collect($payload['work']['active'] ?? [])->pluck('id')->all();

        $this->assertContains($keep->id, $closedIds);
        $this->assertNotContains($drop->id, $activeIds);
        $this->assertNotContains($drop->id, $closedIds);
    }

    public function test_other_user_cannot_delete_report(): void
    {
        $report = app(ManagerReportBuilder::class)->create(
            $this->user,
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-07'),
            $this->user,
        );

        $other = User::factory()->create();

        $this->actingAs($other)->deleteJson("/reports/{$report->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('manager_reports', ['id' => $report->id]);
    }

    public function test_guest_cannot_create_report(): void
    {
        $this->post('/reports', [
            'period_from' => '2026-08-01',
            'period_to' => '2026-08-07',
        ])->assertRedirect('/login');
    }

    public function test_work_sections_closed_active_and_events(): void
    {
        $statusNew = TaskStatus::query()->where('slug', 'new')->value('id');
        $statusDone = TaskStatus::query()->where('slug', 'done')->value('id');
        $priority = TaskPriority::query()->where('slug', 'normal')->value('id');
        $type = TaskType::query()->where('slug', 'purchase')->value('id');
        $eventType = EventType::query()->firstOrFail()->id;

        $closed = Task::create([
            'user_id' => $this->user->id,
            'status_id' => $statusDone,
            'priority_id' => $priority,
            'type_id' => $type,
            'title' => 'Закрытое в периоде',
            'closed_at' => '2026-08-03 15:00:00',
            'created_at' => '2026-07-20 10:00:00',
        ]);

        $active = Task::create([
            'user_id' => $this->user->id,
            'status_id' => $statusNew,
            'priority_id' => $priority,
            'type_id' => $type,
            'title' => 'Активное в периоде',
            'created_at' => '2026-07-28 10:00:00',
        ]);

        Task::create([
            'user_id' => $this->user->id,
            'status_id' => $statusDone,
            'priority_id' => $priority,
            'type_id' => $type,
            'title' => 'Закрыто до периода',
            'closed_at' => '2026-07-20 12:00:00',
            'created_at' => '2026-07-01 10:00:00',
        ]);

        $event = CalendarEvent::create([
            'user_id' => $this->user->id,
            'event_type_id' => $eventType,
            'title' => 'Встреча в периоде',
            'starts_at' => '2026-08-05 11:00:00',
            'ends_at' => '2026-08-05 12:00:00',
            'all_day' => false,
        ]);

        CalendarEvent::create([
            'user_id' => $this->user->id,
            'event_type_id' => $eventType,
            'title' => 'Вне периода',
            'starts_at' => '2026-07-10 11:00:00',
            'all_day' => false,
        ]);

        $report = app(ManagerReportBuilder::class)->create(
            $this->user,
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-07'),
            $this->user,
        );

        $work = $report->payload['work'];
        $this->assertCount(1, $work['closed']);
        $this->assertSame($closed->id, $work['closed'][0]['id']);
        $this->assertSame('Закрытое в периоде', $work['closed'][0]['title']);

        $this->assertCount(1, $work['active']);
        $this->assertSame($active->id, $work['active'][0]['id']);

        $this->assertCount(1, $work['events']);
        $this->assertSame($event->id, $work['events'][0]['id']);
        $this->assertSame('Встреча в периоде', $work['events'][0]['title']);
    }

    public function test_on_hand_opening_closing_and_movements(): void
    {
        $wallets = app(WalletService::class);

        $wallets->topUp($this->user, [
            'amount' => 1000,
            'disbursement_method_id' => $this->method->slug,
            'occurred_at' => '2026-07-20',
            'title' => 'До периода',
        ]);

        $wallets->topUp($this->user, [
            'amount' => 500,
            'disbursement_method_id' => $this->method->slug,
            'occurred_at' => '2026-08-03',
            'title' => 'В периоде',
        ]);

        $opening = app(ReportOnHandCalculator::class)->opening($this->user, Carbon::parse('2026-08-01'));
        $closing = app(ReportOnHandCalculator::class)->closing($this->user, Carbon::parse('2026-08-07'));

        $this->assertSame(100000, $opening['on_hand_minor']);
        $this->assertSame(150000, $closing['on_hand_minor']);

        $report = app(ManagerReportBuilder::class)->create(
            $this->user,
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-07'),
            $this->user,
        );

        $finance = $report->payload['finance'];
        $this->assertSame(1000.0, (float) $finance['opening_on_hand']);
        $this->assertSame(1500.0, (float) $finance['closing_on_hand']);
        $this->assertSame(500.0, (float) $finance['income_total']);
        $this->assertCount(1, $finance['movements']);
        $this->assertSame('В периоде', $finance['movements'][0]['title']);
    }

    public function test_report_scoped_to_single_user(): void
    {
        $other = User::factory()->create();
        $statusNew = TaskStatus::query()->where('slug', 'new')->value('id');
        $priority = TaskPriority::query()->where('slug', 'normal')->value('id');
        $type = TaskType::query()->where('slug', 'call')->value('id');

        Task::create([
            'user_id' => $other->id,
            'status_id' => $statusNew,
            'priority_id' => $priority,
            'type_id' => $type,
            'title' => 'Чужое поручение',
            'created_at' => '2026-08-02 10:00:00',
        ]);

        Task::create([
            'user_id' => $this->user->id,
            'status_id' => $statusNew,
            'priority_id' => $priority,
            'type_id' => $type,
            'title' => 'Моё поручение',
            'created_at' => '2026-08-02 10:00:00',
        ]);

        app(WalletService::class)->topUp($other, [
            'amount' => 999,
            'disbursement_method_id' => $this->method->slug,
            'occurred_at' => '2026-08-03',
        ]);

        $report = app(ManagerReportBuilder::class)->create(
            $this->user,
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-07'),
            $this->user,
        );

        $this->assertCount(1, $report->payload['work']['active']);
        $this->assertSame('Моё поручение', $report->payload['work']['active'][0]['title']);
        $this->assertSame(0.0, (float) $report->payload['finance']['income_total']);
        $this->assertSame(0.0, (float) $report->payload['finance']['closing_on_hand']);
    }
}
