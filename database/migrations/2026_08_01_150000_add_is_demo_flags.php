<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'tasks',
        'calendar_events',
        'advances',
        'expenses',
        'contacts',
        'suppliers',
        'reminders',
        'wallet_transactions',
        'receipts',
        'task_attachments',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'is_demo')) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->boolean('is_demo')->default(false)->index();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'is_demo')) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('is_demo');
            });
        }
    }
};
