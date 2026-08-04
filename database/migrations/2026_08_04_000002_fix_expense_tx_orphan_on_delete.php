<?php

use App\Models\WalletTransaction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove ghost expense ledger rows left by destroy + nullOnDelete(expense_id).
        // Keep intentional nulls: close_writeoff has no expense row.
        WalletTransaction::query()
            ->whereNull('expense_id')
            ->where('type', WalletTransaction::TYPE_EXPENSE)
            ->orderBy('id')
            ->each(function (WalletTransaction $tx) {
                $meta = is_array($tx->meta) ? $tx->meta : [];
                if (($meta['kind'] ?? null) === 'close_writeoff') {
                    return;
                }
                $tx->delete();
            });

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropForeign(['expense_id']);
        });

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->foreign('expense_id')
                ->references('id')
                ->on('expenses')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropForeign(['expense_id']);
        });

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->foreign('expense_id')
                ->references('id')
                ->on('expenses')
                ->nullOnDelete();
        });
    }
};
