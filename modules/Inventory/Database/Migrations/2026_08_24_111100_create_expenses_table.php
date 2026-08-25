<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Money the store spends that is not the cost of the goods it sells —
     * rent, wages, utilities. Stock purchases are deliberately absent: their
     * cost reaches the profit report as cost of goods sold on the order that
     * sold them, and recording them here as well would subtract the same money
     * twice.
     *
     * "spent_on" is a date, not a timestamp: an expense belongs to a trading
     * day, and the report groups by that day regardless of when the row was
     * entered.
     */
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('spent_on')->index();
            $table->string('category')->index();
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('USD');
            $table->string('reference')->nullable();
            $table->foreignUuid('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // The report sums by day and by category, in that order.
            $table->index(['spent_on', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
