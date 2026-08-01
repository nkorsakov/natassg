<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_articles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('label');
            $table->string('color', 32);
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('disbursement_methods', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('label');
            $table->string('color', 32);
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->boolean('is_supplier')->default(false)->after('note');
            $table->index(['user_id', 'is_supplier']);
        });

        Schema::create('advance_task', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['advance_id', 'task_id']);
        });

        if (Schema::hasColumn('advances', 'task_id')) {
            $rows = DB::table('advances')->whereNotNull('task_id')->get(['id', 'task_id']);
            foreach ($rows as $row) {
                DB::table('advance_task')->insertOrIgnore([
                    'advance_id' => $row->id,
                    'task_id' => $row->task_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            Schema::table('advances', function (Blueprint $table) {
                $table->dropConstrainedForeignId('task_id');
            });
        }

        Schema::table('advances', function (Blueprint $table) {
            $table->foreignId('disbursement_method_id')
                ->nullable()
                ->after('status_id')
                ->constrained('disbursement_methods')
                ->nullOnDelete();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['advance_id']);
        });

        DB::statement('ALTER TABLE expenses MODIFY advance_id BIGINT UNSIGNED NULL');

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreign('advance_id')->references('id')->on('advances')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('article_id')->nullable()->after('advance_id')->constrained('expense_articles')->restrictOnDelete();
            $table->foreignId('supplier_contact_id')->nullable()->after('article_id')->constrained('contacts')->restrictOnDelete();
            $table->foreignId('task_id')->nullable()->after('supplier_contact_id')->constrained('tasks')->nullOnDelete();
        });

        DB::statement('UPDATE expenses e INNER JOIN advances a ON a.id = e.advance_id SET e.user_id = a.user_id WHERE e.user_id IS NULL');

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->foreignId('expense_id')->nullable()->after('advance_id')->constrained('expenses')->nullOnDelete();
            $table->index(['advance_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('expense_id');
            $table->dropIndex(['wallet_transactions_advance_id_type_index']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('task_id');
            $table->dropConstrainedForeignId('supplier_contact_id');
            $table->dropConstrainedForeignId('article_id');
            $table->dropConstrainedForeignId('user_id');
            $table->dropForeign(['advance_id']);
        });

        DB::statement('ALTER TABLE expenses MODIFY advance_id BIGINT UNSIGNED NOT NULL');

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreign('advance_id')->references('id')->on('advances')->cascadeOnDelete();
        });

        Schema::table('advances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('disbursement_method_id');
            $table->foreignId('task_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        $pivots = DB::table('advance_task')->get();
        foreach ($pivots->groupBy('advance_id') as $advanceId => $rows) {
            DB::table('advances')->where('id', $advanceId)->update([
                'task_id' => $rows->first()->task_id,
            ]);
        }

        Schema::dropIfExists('advance_task');

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_supplier']);
            $table->dropColumn('is_supplier');
        });

        Schema::dropIfExists('disbursement_methods');
        Schema::dropIfExists('expense_articles');
    }
};
