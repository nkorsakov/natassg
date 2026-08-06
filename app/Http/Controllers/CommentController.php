<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Task;
use App\Services\CommentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CommentController extends Controller
{
    public function storeForTask(Request $request, Task $task, CommentService $comments): RedirectResponse
    {
        abort_unless($request->user()?->canAccessOwned($task->user_id), 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
        ]);

        try {
            $comments->create($request->user(), $task, $data['body']);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['body' => $e->getMessage()]);
        }

        return back();
    }

    public function updateForTask(
        Request $request,
        Task $task,
        Comment $comment,
        CommentService $comments,
    ): RedirectResponse {
        abort_unless($request->user()?->canAccessOwned($task->user_id), 403);
        abort_unless($this->commentBelongsToTask($comment, $task), 404);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
        ]);

        try {
            $comments->update($request->user(), $comment, $data['body']);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['body' => $e->getMessage()]);
        }

        return back();
    }

    public function destroyForTask(
        Request $request,
        Task $task,
        Comment $comment,
        CommentService $comments,
    ): RedirectResponse {
        abort_unless($request->user()?->canAccessOwned($task->user_id), 403);
        abort_unless($this->commentBelongsToTask($comment, $task), 404);

        try {
            $comments->destroy($request->user(), $comment);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['comment' => $e->getMessage()]);
        }

        return back();
    }

    protected function commentBelongsToTask(Comment $comment, Task $task): bool
    {
        return $comment->commentable_type === $task->getMorphClass()
            && (int) $comment->commentable_id === (int) $task->id;
    }
}
