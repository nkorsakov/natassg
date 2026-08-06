<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manager_reports', function (Blueprint $table) {
            $table->unsignedInteger('views_count')->default(0)->after('payload');
            $table->string('status', 32)->default('pending')->after('views_count');
            $table->timestamp('accepted_at')->nullable()->after('status');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('manager_reports', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['views_count', 'status', 'accepted_at']);
        });
    }
};
