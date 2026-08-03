<?php

use App\Models\AdvanceStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Start finance from scratch per product decision.
        if (Schema::hasTable('wallet_transactions')) {
            DB::table('wallet_transactions')->delete();
        }
        if (Schema::hasTable('receipts')) {
            DB::table('receipts')->delete();
        }
        if (Schema::hasTable('expenses')) {
            DB::table('expenses')->delete();
        }
        if (Schema::hasTable('advance_task')) {
            DB::table('advance_task')->delete();
        }
        if (Schema::hasTable('advances')) {
            DB::table('advances')->delete();
        }
        if (Schema::hasTable('wallets')) {
            DB::table('wallets')->update(['balance_minor' => 0]);
        }

        if (Schema::hasTable('expenses') && ! Schema::hasColumn('expenses', 'debit_account')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->string('debit_account', 16)->default('unassigned')->after('advance_id');
                $table->index(['user_id', 'debit_account']);
            });
        }

        if (Schema::hasTable('wallet_transactions') && ! Schema::hasColumn('wallet_transactions', 'account')) {
            Schema::table('wallet_transactions', function (Blueprint $table) {
                $table->string('account', 16)->default('wallet')->after('type');
                $table->index(['wallet_id', 'account']);
            });
        }

        // Status set: заявка → получены → на отчёте → закрыта
        if (Schema::hasTable('advance_statuses')) {
            AdvanceStatus::query()->where('slug', 'approved')->delete();

            AdvanceStatus::updateOrCreate(
                ['slug' => 'pending'],
                ['label' => 'Заявка', 'color' => '#FFAD4D', 'sort' => 10, 'is_system' => true]
            );
            AdvanceStatus::updateOrCreate(
                ['slug' => 'received'],
                ['label' => 'Деньги получены', 'color' => '#6957EE', 'sort' => 20, 'is_system' => true]
            );
            AdvanceStatus::updateOrCreate(
                ['slug' => 'reporting'],
                ['label' => 'На отчёте', 'color' => '#0D9488', 'sort' => 30, 'is_system' => true]
            );
            AdvanceStatus::updateOrCreate(
                ['slug' => 'closed'],
                ['label' => 'Закрыта', 'color' => '#626571', 'sort' => 40, 'is_system' => true]
            );

            // Keep issued as alias label if still referenced; prefer deleting after remap
            if (AdvanceStatus::query()->where('slug', 'issued')->exists()) {
                AdvanceStatus::query()->where('slug', 'issued')->delete();
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('expenses') && Schema::hasColumn('expenses', 'debit_account')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->dropIndex(['user_id', 'debit_account']);
                $table->dropColumn('debit_account');
            });
        }

        if (Schema::hasTable('wallet_transactions') && Schema::hasColumn('wallet_transactions', 'account')) {
            Schema::table('wallet_transactions', function (Blueprint $table) {
                $table->dropIndex(['wallet_id', 'account']);
                $table->dropColumn('account');
            });
        }
    }
};
