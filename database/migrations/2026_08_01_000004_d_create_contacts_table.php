<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contacts')) {
            if (! Schema::hasColumn('contacts', 'is_supplier')) {
                Schema::table('contacts', function (Blueprint $table) {
                    $table->boolean('is_supplier')->default(false)->after('note');
                    $table->index(['user_id', 'is_supplier']);
                });
            }

            return;
        }

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('role')->nullable();
            $table->string('phone')->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_supplier')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'name']);
            $table->index(['user_id', 'is_supplier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
