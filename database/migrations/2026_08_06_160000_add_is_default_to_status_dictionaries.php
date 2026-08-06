<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_statuses', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('is_system');
        });

        Schema::table('advance_statuses', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('is_system');
        });

        $this->markDefault('task_statuses', 'new');
        $this->markDefault('advance_statuses', 'pending');
    }

    public function down(): void
    {
        Schema::table('task_statuses', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });

        Schema::table('advance_statuses', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }

    protected function markDefault(string $table, string $preferredSlug): void
    {
        $id = DB::table($table)->where('slug', $preferredSlug)->value('id');
        if (! $id) {
            $id = DB::table($table)->orderBy('sort')->orderBy('id')->value('id');
        }
        if ($id) {
            DB::table($table)->where('id', $id)->update(['is_default' => true]);
        }
    }
};
