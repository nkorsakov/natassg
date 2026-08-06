<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\TaskType;
use App\Models\User;
use Database\Seeders\DictionarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentCrudTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $other;

    protected Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DictionarySeeder::class);

        $this->owner = User::factory()->create();
        $this->other = User::factory()->create(['is_admin' => true]);

        $this->task = Task::create([
            'user_id' => $this->owner->id,
            'status_id' => TaskStatus::query()->where('slug', 'new')->value('id'),
            'priority_id' => TaskPriority::query()->where('slug', 'normal')->value('id'),
            'type_id' => TaskType::query()->where('slug', 'purchase')->value('id'),
            'title' => 'Задача с комментариями',
        ]);
    }

    public function test_owner_and_admin_can_comment_on_task(): void
    {
        $this->actingAs($this->owner)
            ->post("/tasks/{$this->task->id}/comments", [
                'body' => 'Первый от владельца https://example.com',
            ])
            ->assertRedirect();

        $this->actingAs($this->other)
            ->post("/tasks/{$this->task->id}/comments", [
                'body' => 'Второй от админа',
            ])
            ->assertRedirect();

        $this->assertSame(2, Comment::query()->where('commentable_id', $this->task->id)->count());
        $this->assertDatabaseHas('comments', [
            'user_id' => $this->owner->id,
            'commentable_type' => 'task',
            'commentable_id' => $this->task->id,
            'body' => 'Первый от владельца https://example.com',
        ]);
    }

    public function test_author_can_update_and_delete_own_comment(): void
    {
        $comment = Comment::create([
            'user_id' => $this->owner->id,
            'commentable_type' => 'task',
            'commentable_id' => $this->task->id,
            'body' => 'Черновик',
        ]);

        $this->actingAs($this->owner)
            ->put("/tasks/{$this->task->id}/comments/{$comment->id}", [
                'body' => 'Готово',
            ])
            ->assertRedirect();

        $this->assertSame('Готово', $comment->fresh()->body);

        $this->actingAs($this->owner)
            ->delete("/tasks/{$this->task->id}/comments/{$comment->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
    }

    public function test_cannot_edit_or_delete_foreign_comment(): void
    {
        $comment = Comment::create([
            'user_id' => $this->owner->id,
            'commentable_type' => 'task',
            'commentable_id' => $this->task->id,
            'body' => 'Чужой',
        ]);

        $this->actingAs($this->other)
            ->from('/tasks')
            ->put("/tasks/{$this->task->id}/comments/{$comment->id}", [
                'body' => 'Взлом',
            ])
            ->assertRedirect('/tasks')
            ->assertSessionHasErrors('body');

        $this->assertSame('Чужой', $comment->fresh()->body);

        $this->actingAs($this->other)
            ->from('/tasks')
            ->delete("/tasks/{$this->task->id}/comments/{$comment->id}")
            ->assertRedirect('/tasks')
            ->assertSessionHasErrors('comment');

        $this->assertDatabaseHas('comments', ['id' => $comment->id]);
    }

    public function test_empty_body_rejected(): void
    {
        $this->actingAs($this->owner)
            ->from('/tasks')
            ->post("/tasks/{$this->task->id}/comments", [
                'body' => '   ',
            ])
            ->assertRedirect('/tasks')
            ->assertSessionHasErrors('body');
    }
}
