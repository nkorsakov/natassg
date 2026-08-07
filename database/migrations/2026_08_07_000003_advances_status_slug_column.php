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
        Schema::table('advances', function (Blueprint $table) {
            $table->string('status', 32)->nullable()->after('user_id');
        });

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
            Schema::table('advances', function (Blueprint $table) {
                $table->dropForeign(['status_id']);
            });

            // SQLite cannot drop a column while a composite index still references it.
            Schema::table('advances', function (Blueprint $table) {
                $table->dropIndex(['user_id', 'status_id']);
            });

            Schema::table('advances', function (Blueprint $table) {
                $table->dropColumn('status_id');
            });
        }

        Schema::table('advances', function (Blueprint $table) {
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('advances', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
            $table->unsignedBigInteger('status_id')->nullable()->after('user_id');
            $table->dropColumn('status');
        });
    }
};
