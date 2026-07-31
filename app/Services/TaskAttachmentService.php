<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TaskAttachmentService
{
    public function store(User $user, Task $task, UploadedFile $file, ?int $width = null, ?int $height = null): TaskAttachment
    {
        $mime = $file->getMimeType() ?? 'application/octet-stream';
        $kind = str_starts_with($mime, 'image/') ? 'image' : 'document';
        $dir = "task-attachments/{$user->id}/{$task->id}";
        $name = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs($dir, $name, 'public');

        return TaskAttachment::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'kind' => $kind,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $mime,
            'size' => $file->getSize() ?: 0,
            'width' => $width,
            'height' => $height,
        ]);
    }

    public function destroy(TaskAttachment $attachment): void
    {
        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();
    }
}
