<?php

namespace Tests\Feature;

use App\Models\Advance;
use App\Models\Expense;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\DictionarySeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seed_and_clear_roundtrip(): void
    {
        $this->seed([DictionarySeeder::class, UserSeeder::class, DemoSeeder::class]);

        $user = User::query()->where('email', 'demo@skydesk.local')->firstOrFail();
        $this->assertTrue($user->is_demo);

        $this->assertTrue(Task::query()->where('user_id', $user->id)->where('is_demo', true)->exists());
        $this->assertTrue(Advance::query()->where('user_id', $user->id)->where('is_demo', true)->exists());
        $this->assertTrue(Expense::query()->where('user_id', $user->id)->where('is_demo', true)->exists());
        $this->assertGreaterThan(0, (int) $user->wallet()->value('balance_minor'));

        // Посетитель создал запись без is_demo — clear всё равно снесёт workspace demo-user
        $statusId = \App\Models\TaskStatus::query()->where('slug', 'new')->value('id');
        $priorityId = \App\Models\TaskPriority::query()->where('slug', 'normal')->value('id');
        $typeId = \App\Models\TaskType::query()->where('slug', 'call')->value('id');
        Task::query()->create([
            'user_id' => $user->id,
            'title' => 'Visitor junk',
            'status_id' => $statusId,
            'priority_id' => $priorityId,
            'type_id' => $typeId,
            'is_demo' => false,
        ]);

        $this->artisan('demo:clear')->assertSuccessful();

        $this->assertSame(0, Task::withTrashed()->where('user_id', $user->id)->count());
        $this->assertSame(0, Advance::query()->where('user_id', $user->id)->count());
        $this->assertSame(0, Expense::query()->where('user_id', $user->id)->count());
        $this->assertSame(0, (int) $user->fresh()->wallet()->value('balance_minor'));

        $this->assertDatabaseHas('users', ['email' => 'demo@skydesk.local', 'is_demo' => true]);
        $this->assertDatabaseHas('users', ['email' => 'nataliya@skydesk.local']);
        $this->assertDatabaseHas('task_statuses', ['slug' => 'new']);
    }
}
