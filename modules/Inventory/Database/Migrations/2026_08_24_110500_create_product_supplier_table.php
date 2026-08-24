<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot linking products (optionally a specific variant) to suppliers.
     *
     * "variant_key" mirrors product_variant_id as a non-null string ('' when the
     * link is product-wide) so the composite unique index actually rejects
     * duplicates — MySQL treats NULLs in a unique index as distinct values.
     */
    public function up(): void
    {
        Schema::create('product_supplier', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('product_variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->string('variant_key', 36)->default('');
            $table->foreignUuid('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('supplier_sku')->nullable();
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->unsignedInteger('minimum_order_quantity')->nullable();
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->boolean('is_preferred')->default(false);
            $table->timestamps();

            $table->unique(['product_id', 'variant_key', 'supplier_id'], 'product_supplier_unique');
            $table->index('supplier_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_supplier');
    }
};
