<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Upgrade path for DBs that already ran the pre-canon finance schema.
 * Fresh installs get the final schema from earlier migrations and skip most steps.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('expense_articles')) {
            Schema::create('expense_articles', function (Blueprint $table) {
                $table->id();
                $table->string('slug')->unique();
                $table->string('label');
                $table->string('color', 32);
                $table->unsignedInteger('sort')->default(0);
                $table->boolean('is_system')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('disbursement_methods')) {
            Schema::create('disbursement_methods', function (Blueprint $table) {
                $table->id();
                $table->string('slug')->unique();
                $table->string('label');
                $table->string('color', 32);
                $table->unsignedInteger('sort')->default(0);
                $table->boolean('is_system')->default(false);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('contacts') && ! Schema::hasColumn('contacts', 'is_supplier')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->boolean('is_supplier')->default(false)->after('note');
                $table->index(['user_id', 'is_supplier']);
            });
        }

        if (Schema::hasTable('advances') && Schema::hasColumn('advances', 'task_id')) {
            if (! Schema::hasTable('advance_task')) {
                Schema::create('advance_task', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('advance_id')->constrained()->cascadeOnDelete();
                    $table->foreignId('task_id')->constrained()->cascadeOnDelete();
                    $table->timestamps();
                    $table->unique(['advance_id', 'task_id']);
                });
            }

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

        if (Schema::hasTable('advances') && ! Schema::hasColumn('advances', 'disbursement_method_id')) {
            Schema::table('advances', function (Blueprint $table) {
                $table->foreignId('disbursement_method_id')
                    ->nullable()
                    ->after('status_id')
                    ->constrained('disbursement_methods')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('expenses') && ! Schema::hasColumn('expenses', 'user_id')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->dropForeign(['advance_id']);
            });

            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                DB::statement('ALTER TABLE expenses MODIFY advance_id BIGINT UNSIGNED NULL');
            }

            Schema::table('expenses', function (Blueprint $table) {
                $table->foreign('advance_id')->references('id')->on('advances')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('article_id')->nullable()->after('advance_id')->constrained('expense_articles')->restrictOnDelete();
                $table->foreignId('supplier_contact_id')->nullable()->after('article_id')->constrained('contacts')->restrictOnDelete();
                $table->foreignId('task_id')->nullable()->after('supplier_contact_id')->constrained('tasks')->nullOnDelete();
            });

            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                DB::statement('UPDATE expenses e INNER JOIN advances a ON a.id = e.advance_id SET e.user_id = a.user_id WHERE e.user_id IS NULL');
            }
        }

        if (Schema::hasTable('wallet_transactions') && ! Schema::hasColumn('wallet_transactions', 'expense_id')) {
            Schema::table('wallet_transactions', function (Blueprint $table) {
                $table->foreignId('expense_id')->nullable()->after('advance_id')->constrained('expenses')->nullOnDelete();
                $table->index(['advance_id', 'type']);
            });
        }
    }

    public function down(): void
    {
        // Irreversible safely for mixed fresh/upgrade installs.
    }
};
