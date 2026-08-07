<?php

namespace Tests\Feature;

use App\Models\Advance;
use App\Models\CalendarEvent;
use App\Models\DisbursementMethod;
use App\Models\Expense;
use App\Models\ExpenseArticle;
use App\Models\Task;
use App\Models\User;
use App\Models\WalletTransaction;
use Database\Seeders\DictionarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EntityCrudRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected ExpenseArticle $article;

    protected DisbursementMethod $method;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DictionarySeeder::class);
        $this->user = User::factory()->create();
        $this->article = ExpenseArticle::query()->firstOrFail();
        $this->method = DisbursementMethod::query()->firstOrFail();
    }

    public function test_task_store_update_close_link_unlink_attachment_reminder(): void
    {
        Storage::fake('public');

        $create = $this->actingAs($this->user)->post('/tasks', [
            'title' => 'CRUD поручение',
            'status_id' => 'new',
            'priority_id' => 'normal',
            'type_id' => 'purchase',
            'deadline' => '2026-08-10T12:00',
            'note' => 'заметка',
        ]);
        $create->assertRedirect();
        $create->assertSessionHas('created_task_id');
        $taskId = (int) session('created_task_id');
        $this->assertDatabaseHas('tasks', ['id' => $taskId, 'title' => 'CRUD поручение']);

        $this->actingAs($this->user)->put("/tasks/{$taskId}", [
            'title' => 'Обновлённое поручение',
            'status_id' => 'in_progress',
            'priority_id' => 'high',
            'type_id' => 'purchase',
            'deadline' => '2026-08-11T15:00',
            'note' => 'обновлено',
        ])->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'id' => $taskId,
            'title' => 'Обновлённое поручение',
        ]);
        $this->assertSame('in_progress', Task::find($taskId)?->status?->slug);

        $event = CalendarEvent::create([
            'user_id' => $this->user->id,
            'event_type_id' => \App\Models\EventType::query()->firstOrFail()->id,
            'title' => 'Связанное событие',
            'starts_at' => now(),
            'all_day' => false,
        ]);

        $this->actingAs($this->user)->post("/tasks/{$taskId}/events", [
            'event_id' => $event->id,
        ])->assertRedirect();
        $this->assertTrue(Task::find($taskId)->events()->whereKey($event->id)->exists());

        $this->actingAs($this->user)->delete("/tasks/{$taskId}/events/{$event->id}")
            ->assertRedirect();
        $this->assertFalse(Task::find($taskId)->events()->whereKey($event->id)->exists());

        $file = UploadedFile::fake()->create('note.pdf', 100, 'application/pdf');
        $this->actingAs($this->user)->post("/tasks/{$taskId}/attachments", [
            'file' => $file,
        ])->assertRedirect();
        $attachmentId = Task::find($taskId)->attachments()->value('id');
        $this->assertNotNull($attachmentId);

        $this->actingAs($this->user)->delete("/tasks/{$taskId}/attachments/{$attachmentId}")
            ->assertRedirect();
        $this->assertSame(0, Task::find($taskId)->attachments()->count());

        $this->actingAs($this->user)->post("/tasks/{$taskId}/reminders", [
            'remind_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'message' => 'Напомни',
        ])->assertRedirect();
        $reminderId = Task::find($taskId)->reminders()->where('kind', 'manual')->value('id');
        $this->assertNotNull($reminderId);

        $this->actingAs($this->user)->delete("/tasks/{$taskId}/reminders/{$reminderId}")
            ->assertRedirect();

        $child = $this->actingAs($this->user)->post('/tasks', [
            'title' => 'Подзадача',
            'parent_id' => $taskId,
            'status_id' => 'new',
        ]);
        $child->assertRedirect();
        $childId = (int) session('created_task_id');

        $this->actingAs($this->user)->post("/tasks/{$childId}/make-root")->assertRedirect();
        $this->assertNull(Task::find($childId)?->parent_id);

        $this->actingAs($this->user)->post("/tasks/{$taskId}/close")->assertRedirect();
        $this->assertSame('done', Task::find($taskId)?->fresh('status')->status?->slug);
    }

    public function test_task_destroy_soft_deletes_with_children(): void
    {
        $parent = $this->actingAs($this->user)->post('/tasks', [
            'title' => 'Родитель',
            'status_id' => 'new',
        ]);
        $parentId = (int) session('created_task_id');

        $this->actingAs($this->user)->post('/tasks', [
            'title' => 'Ребёнок',
            'parent_id' => $parentId,
            'status_id' => 'new',
        ]);
        $childId = (int) session('created_task_id');

        $event = CalendarEvent::create([
            'user_id' => $this->user->id,
            'event_type_id' => \App\Models\EventType::query()->firstOrFail()->id,
            'title' => 'Событие',
            'starts_at' => now(),
            'all_day' => false,
        ]);
        $this->actingAs($this->user)->post("/tasks/{$parentId}/events", [
            'event_id' => $event->id,
        ])->assertRedirect();

        $this->actingAs($this->user)->delete("/tasks/{$parentId}")->assertRedirect();

        $this->assertSoftDeleted('tasks', ['id' => $parentId]);
        $this->assertSoftDeleted('tasks', ['id' => $childId]);
        $this->assertDatabaseMissing('task_event', [
            'task_id' => $parentId,
            'event_id' => $event->id,
        ]);
        $this->assertDatabaseHas('calendar_events', ['id' => $event->id]);
    }

    public function test_event_store_update_destroy(): void
    {
        $create = $this->actingAs($this->user)->post('/events', [
            'title' => 'Встреча',
            'type_id' => 'other',
            'start' => '2026-08-10T10:00',
            'end' => '2026-08-10T11:00',
            'allDay' => false,
            'place' => 'Офис',
            'note' => 'важно',
        ]);
        $create->assertRedirect();
        $create->assertSessionHas('created_event_id');
        $eventId = (int) session('created_event_id');

        $this->actingAs($this->user)->put("/events/{$eventId}", [
            'title' => 'Встреча 2',
            'type_id' => 'other',
            'start' => '2026-08-10T12:00',
            'end' => null,
            'allDay' => true,
            'place' => 'Дом',
            'note' => '',
        ])->assertRedirect();

        $event = CalendarEvent::find($eventId);
        $this->assertSame('Встреча 2', $event?->title);
        $this->assertTrue((bool) $event?->all_day);
        $this->assertNull($event?->ends_at);

        $this->actingAs($this->user)->delete("/events/{$eventId}")->assertRedirect();
        $this->assertSoftDeleted('calendar_events', ['id' => $eventId]);
    }

    public function test_finance_full_crud_matrix(): void
    {
        Storage::fake('public');

        $this->actingAs($this->user)->post('/wallet/topups', [
            'amount' => 5000,
            'title' => 'Пополнение',
            'note' => 'тест',
            'disbursement_method_id' => $this->method->slug,
            'occurred_at' => '2026-08-01',
        ])->assertRedirect();

        $topUp = WalletTransaction::query()
            ->where('type', WalletTransaction::TYPE_INCOME)
            ->where('account', WalletTransaction::ACCOUNT_WALLET)
            ->latest('id')
            ->first();
        $this->assertNotNull($topUp);

        $this->actingAs($this->user)->put("/wallet/topups/{$topUp->id}", [
            'amount' => 4500,
            'title' => 'Пополнение прав',
            'note' => 'правка',
            'disbursement_method_id' => $this->method->slug,
            'occurred_at' => '2026-08-02',
        ])->assertRedirect();
        $this->assertSame(450000, (int) $topUp->fresh()->amount_minor);

        $advCreate = $this->actingAs($this->user)->post('/advances', [
            'title' => 'Аванс CRUD',
            'amount' => 1000,
            'disbursement_method_id' => $this->method->slug,
            'note' => 'n',
        ]);
        $advCreate->assertRedirect();
        $advCreate->assertSessionHas('created_advance_id');
        $advanceId = (int) session('created_advance_id');

        $this->actingAs($this->user)->put("/advances/{$advanceId}", [
            'title' => 'Аванс CRUD 2',
            'amount' => 1200,
            'disbursement_method_id' => $this->method->slug,
            'note' => 'ok',
        ])->assertRedirect();
        $this->assertSame('pending', Advance::find($advanceId)?->fresh()->statusEnum()->value);

        $this->actingAs($this->user)->post("/advances/{$advanceId}/approve")->assertRedirect();
        $this->assertSame('approved', Advance::find($advanceId)?->fresh()->statusEnum()->value);

        $this->actingAs($this->user)->post("/advances/{$advanceId}/receive", [
            'amount' => 1200,
            'disbursement_method_id' => $this->method->slug,
            'issued_at' => '2026-08-02',
        ])->assertRedirect();
        $this->assertSame('reporting', Advance::find($advanceId)?->fresh()->statusEnum()->value);

        $this->actingAs($this->user)->post("/advances/{$advanceId}/expenses", [
            'amount' => 300,
            'description' => 'Трата аванса',
            'article_id' => $this->article->slug,
            'debit_account' => 'advance',
            'occurred_at' => '2026-08-03',
        ])->assertRedirect();

        $advanceExpense = Expense::query()->where('advance_id', $advanceId)->latest('id')->first();
        $this->assertNotNull($advanceExpense);

        $this->actingAs($this->user)->put("/expenses/{$advanceExpense->id}", [
            'amount' => 350,
            'description' => 'Трата прав',
            'article_id' => $this->article->slug,
            'debit_account' => 'advance',
            'occurred_at' => '2026-08-04',
        ])->assertRedirect();
        $this->assertSame(35000, (int) $advanceExpense->fresh()->amount_minor);

        $file = UploadedFile::fake()->image('receipt.jpg');
        $this->actingAs($this->user)->post("/advances/{$advanceId}/expenses/{$advanceExpense->id}/receipts", [
            'file' => $file,
        ])->assertRedirect();
        $receiptId = $advanceExpense->fresh()->receipts()->value('id');
        $this->assertNotNull($receiptId);

        $this->actingAs($this->user)->delete("/expenses/{$advanceExpense->id}/receipts/{$receiptId}")
            ->assertRedirect();

        $this->actingAs($this->user)->post('/expenses', [
            'amount' => 200,
            'description' => 'Свободная',
            'article_id' => $this->article->slug,
            'debit_account' => 'unassigned',
            'occurred_at' => '2026-08-05',
        ])->assertRedirect();

        $free = Expense::query()->whereNull('advance_id')->latest('id')->first();
        $this->assertNotNull($free);

        $this->actingAs($this->user)->post("/advances/{$advanceId}/expenses/{$free->id}/attach", [
            'debit_account' => 'advance',
        ])->assertRedirect();
        $this->assertSame($advanceId, $free->fresh()->advance_id);

        $this->actingAs($this->user)->post("/advances/{$advanceId}/expenses/{$free->id}/detach")
            ->assertRedirect();
        $this->assertNull($free->fresh()->advance_id);

        $this->actingAs($this->user)->delete("/expenses/{$free->id}")->assertRedirect();
        $this->assertDatabaseMissing('expenses', ['id' => $free->id]);

        $this->actingAs($this->user)->post("/advances/{$advanceId}/close-to-wallet")->assertRedirect();
        $this->assertSame('closed', Advance::find($advanceId)?->fresh()->statusEnum()->value);

        $toDelete = $this->actingAs($this->user)->post('/advances', [
            'title' => 'Удалить',
            'amount' => 100,
        ]);
        $delId = (int) session('created_advance_id');
        $this->actingAs($this->user)->delete("/advances/{$delId}")->assertRedirect();
        $this->assertDatabaseMissing('advances', ['id' => $delId]);

        $this->actingAs($this->user)->delete("/wallet/transactions/{$topUp->id}")->assertRedirect();
        $this->assertDatabaseMissing('wallet_transactions', ['id' => $topUp->id]);
    }

    public function test_task_update_allows_empty_title(): void
    {
        $task = Task::create([
            'user_id' => $this->user->id,
            'status_id' => \App\Models\TaskStatus::query()->where('slug', 'new')->value('id'),
            'priority_id' => \App\Models\TaskPriority::query()->where('slug', 'normal')->value('id'),
            'type_id' => \App\Models\TaskType::query()->where('slug', 'purchase')->value('id'),
            'title' => 'Было',
        ]);

        $this->actingAs($this->user)->put("/tasks/{$task->id}", [
            'title' => '',
        ])->assertRedirect();

        $this->assertSame('', $task->fresh()->title);
    }

    public function test_event_update_with_false_all_day(): void
    {
        $event = CalendarEvent::create([
            'user_id' => $this->user->id,
            'event_type_id' => \App\Models\EventType::query()->firstOrFail()->id,
            'title' => 'All day',
            'starts_at' => '2026-08-10 00:00:00',
            'all_day' => true,
        ]);

        $this->actingAs($this->user)->put("/events/{$event->id}", [
            'allDay' => false,
            'start' => '2026-08-10T14:00',
        ])->assertRedirect();

        $this->assertFalse((bool) $event->fresh()->all_day);
    }

    public function test_expense_on_pending_advance_returns_validation_error(): void
    {
        $advance = Advance::create([
            'user_id' => $this->user->id,
            'status' => \App\Enums\AdvanceStatus::Pending,
            'title' => 'Pending',
            'amount_minor' => 10000,
        ]);

        $this->actingAs($this->user)
            ->from('/finance')
            ->post("/advances/{$advance->id}/expenses", [
                'amount' => 100,
                'article_id' => $this->article->slug,
                'debit_account' => 'advance',
            ])
            ->assertRedirect('/finance')
            ->assertSessionHasErrors('amount');
    }
}
