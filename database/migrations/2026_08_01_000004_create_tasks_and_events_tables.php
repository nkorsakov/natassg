<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->foreignId('status_id')->constrained('task_statuses');
            $table->foreignId('priority_id')->constrained('task_priorities');
            $table->foreignId('type_id')->constrained('task_types');
            $table->string('title');
            $table->text('note')->nullable();
            $table->dateTime('deadline')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'parent_id']);
            $table->index(['user_id', 'status_id']);
            $table->index(['user_id', 'deadline']);
        });

        Schema::create('task_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 128)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamps();
        });

        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_type_id')->constrained('event_types');
            $table->string('title');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->boolean('all_day')->default(false);
            $table->string('place')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'starts_at']);
        });

        Schema::create('task_event', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('calendar_events')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['task_id', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_event');
        Schema::dropIfExists('calendar_events');
        Schema::dropIfExists('task_attachments');
        Schema::dropIfExists('tasks');
    }
};
