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

        $user = User::query()->where('email', 'nataliya@skydesk.local')->firstOrFail();

        $this->assertTrue(Task::query()->where('is_demo', true)->exists());
        $this->assertTrue(Advance::query()->where('is_demo', true)->exists());
        $this->assertTrue(Expense::query()->where('is_demo', true)->exists());
        $this->assertGreaterThan(0, (int) $user->wallet()->value('balance_minor'));

        $this->artisan('demo:clear')->assertSuccessful();

        $this->assertSame(0, Task::withTrashed()->where('is_demo', true)->count());
        $this->assertSame(0, Advance::query()->where('is_demo', true)->count());
        $this->assertSame(0, Expense::query()->where('is_demo', true)->count());
        $this->assertSame(0, (int) $user->fresh()->wallet()->value('balance_minor'));

        // Пользователи и словари на месте
        $this->assertDatabaseHas('users', ['email' => 'nataliya@skydesk.local']);
        $this->assertDatabaseHas('task_statuses', ['slug' => 'new']);
    }
}
