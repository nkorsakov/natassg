<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 32);
            $table->timestamp('remind_at');
            $table->text('message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['remind_at', 'sent_at', 'cancelled_at']);
            $table->index(['task_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
