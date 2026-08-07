<?php

use App\Enums\AdvanceStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('advances', 'status')) {
            Schema::table('advances', function (Blueprint $table) {
                $table->string('status', 32)->nullable()->after('user_id');
            });
        }

        if (Schema::hasColumn('advances', 'status_id') && Schema::hasTable('advance_statuses')) {
            $rows = DB::table('advances')
                ->leftJoin('advance_statuses', 'advances.status_id', '=', 'advance_statuses.id')
                ->select('advances.id', 'advance_statuses.slug')
                ->get();

            foreach ($rows as $row) {
                $status = AdvanceStatus::tryFromLegacy($row->slug)?->value
                    ?? AdvanceStatus::Pending->value;
                DB::table('advances')->where('id', $row->id)->update(['status' => $status]);
            }
        }

        DB::table('advances')->whereNull('status')->orWhere('status', '')->update([
            'status' => AdvanceStatus::Pending->value,
        ]);

        if (Schema::hasColumn('advances', 'status_id')) {
            $this->dropForeignKeysOnColumn('advances', 'status_id');

            // MySQL may use advances_user_id_status_id_index to support the user_id FK
            // (leftmost prefix). Do not drop that index manually — dropping status_id
            // rebuilds/removes it. SQLite needs the index gone before the column drop.
            if (Schema::getConnection()->getDriverName() === 'sqlite') {
                $this->dropIndexIfExists('advances', 'advances_user_id_status_id_index');
            }

            Schema::table('advances', function (Blueprint $table) {
                $table->dropColumn('status_id');
            });
        }

        if (! $this->indexExists('advances', 'advances_user_id_status_index')) {
            Schema::table('advances', function (Blueprint $table) {
                $table->index(['user_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('advances', 'advances_user_id_status_index')) {
            Schema::table('advances', function (Blueprint $table) {
                $table->dropIndex(['user_id', 'status']);
            });
        }

        if (Schema::hasColumn('advances', 'status')) {
            Schema::table('advances', function (Blueprint $table) {
                $table->unsignedBigInteger('status_id')->nullable()->after('user_id');
                $table->dropColumn('status');
            });
        }
    }

    private function dropForeignKeysOnColumn(string $table, string $column): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $database = Schema::getConnection()->getDatabaseName();
            $keys = DB::select(
                'select distinct CONSTRAINT_NAME as name
                 from information_schema.KEY_COLUMN_USAGE
                 where TABLE_SCHEMA = ?
                   and TABLE_NAME = ?
                   and COLUMN_NAME = ?
                   and REFERENCED_TABLE_NAME is not null',
                [$database, $table, $column]
            );

            foreach ($keys as $key) {
                DB::statement("alter table `{$table}` drop foreign key `{$key->name}`");
            }

            return;
        }

        // SQLite / others: Laravel naming convention.
        try {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropForeign([$column]);
            });
        } catch (\Throwable) {
            // Already dropped on a partial previous run.
        }
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (! $this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
            $blueprint->dropIndex($indexName);
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $database = Schema::getConnection()->getDatabaseName();
            $rows = DB::select(
                'select 1 from information_schema.STATISTICS
                 where TABLE_SCHEMA = ? and TABLE_NAME = ? and INDEX_NAME = ?
                 limit 1',
                [$database, $table, $indexName]
            );

            return $rows !== [];
        }

        if ($driver === 'sqlite') {
            $rows = DB::select("pragma index_list('{$table}')");
            foreach ($rows as $row) {
                $name = $row->name ?? $row->Name ?? null;
                if ($name === $indexName) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }
};
