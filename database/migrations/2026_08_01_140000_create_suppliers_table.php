<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'name']);
            $table->unique(['user_id', 'contact_id']);
        });

        if (Schema::hasTable('expenses') && Schema::hasColumn('expenses', 'supplier_contact_id')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->foreignId('supplier_id')->nullable()->after('article_id')->constrained('suppliers')->restrictOnDelete();
            });

            $contactIds = DB::table('expenses')
                ->whereNotNull('supplier_contact_id')
                ->distinct()
                ->pluck('supplier_contact_id')
                ->merge(
                    DB::table('contacts')->where('is_supplier', true)->pluck('id')
                )
                ->unique()
                ->filter()
                ->values();

            $supplierByContact = [];

            foreach ($contactIds as $contactId) {
                $contact = DB::table('contacts')->where('id', $contactId)->first();
                if (! $contact) {
                    continue;
                }

                $supplierId = DB::table('suppliers')->insertGetId([
                    'user_id' => $contact->user_id,
                    'name' => $contact->name ?: 'Поставщик',
                    'contact_id' => $contact->id,
                    'note' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $supplierByContact[(int) $contact->id] = $supplierId;
            }

            foreach ($supplierByContact as $contactId => $supplierId) {
                DB::table('expenses')
                    ->where('supplier_contact_id', $contactId)
                    ->update(['supplier_id' => $supplierId]);
            }

            Schema::table('expenses', function (Blueprint $table) {
                $table->dropConstrainedForeignId('supplier_contact_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('expenses') && Schema::hasColumn('expenses', 'supplier_id')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->foreignId('supplier_contact_id')->nullable()->after('article_id')->constrained('contacts')->restrictOnDelete();
            });

            $suppliers = DB::table('suppliers')->whereNotNull('contact_id')->get();
            foreach ($suppliers as $supplier) {
                DB::table('expenses')
                    ->where('supplier_id', $supplier->id)
                    ->update(['supplier_contact_id' => $supplier->contact_id]);
            }

            Schema::table('expenses', function (Blueprint $table) {
                $table->dropConstrainedForeignId('supplier_id');
            });
        }

        Schema::dropIfExists('suppliers');
    }
};
