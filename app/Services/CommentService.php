<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class CommentService
{
    public function create(User $author, Model $commentable, string $body): Comment
    {
        $body = trim($body);
        if ($body === '') {
            throw new InvalidArgumentException('Комментарий не может быть пустым');
        }

        return Comment::create([
            'user_id' => $author->id,
            'commentable_type' => $commentable->getMorphClass(),
            'commentable_id' => $commentable->getKey(),
            'body' => $body,
        ])->load('user');
    }

    public function update(User $actor, Comment $comment, string $body): Comment
    {
        if ((int) $comment->user_id !== (int) $actor->id) {
            throw new InvalidArgumentException('Чужой комментарий нельзя менять');
        }

        $body = trim($body);
        if ($body === '') {
            throw new InvalidArgumentException('Комментарий не может быть пустым');
        }

        $comment->body = $body;
        $comment->save();

        return $comment->fresh('user');
    }

    public function destroy(User $actor, Comment $comment): void
    {
        if ((int) $comment->user_id !== (int) $actor->id) {
            throw new InvalidArgumentException('Чужой комментарий нельзя удалить');
        }

        $comment->delete();
    }
}
