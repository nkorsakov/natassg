<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wallet_transactions')) {
            return;
        }

        if (! Schema::hasColumn('wallet_transactions', 'occurred_at')) {
            Schema::table('wallet_transactions', function (Blueprint $table) {
                $table->timestamp('occurred_at')->nullable()->after('meta');
                $table->index(['wallet_id', 'occurred_at']);
            });
        }

        DB::table('wallet_transactions')
            ->whereNull('occurred_at')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('wallet_transactions')
                        ->where('id', $row->id)
                        ->update(['occurred_at' => $row->created_at ?? now()]);
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasTable('wallet_transactions') && Schema::hasColumn('wallet_transactions', 'occurred_at')) {
            Schema::table('wallet_transactions', function (Blueprint $table) {
                $table->dropIndex(['wallet_id', 'occurred_at']);
                $table->dropColumn('occurred_at');
            });
        }
    }
};
