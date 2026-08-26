<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A single store trades in a single currency, and that currency is a
     * setting. Copying it onto every order and expense implied records could
     * differ, which they cannot, and left reports summing across a distinction
     * that never existed.
     *
     * Guarded so it is a no-op on a database created after the column was
     * removed from the table's own migration.
     */
    public function up(): void
    {
        foreach (['orders', 'expenses'] as $table) {
            if (Schema::hasColumn($table, 'currency')) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn('currency'));
            }
        }
    }

    public function down(): void
    {
        foreach (['orders', 'expenses'] as $table) {
            if (! Schema::hasColumn($table, 'currency')) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->char('currency', 3)->nullable());
            }
        }
    }
};
