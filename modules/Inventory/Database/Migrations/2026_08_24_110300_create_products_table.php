<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            // Public identifier for a product, separate from the primary key.
            // The key is an integer because every join and foreign key in the
            // schema carries it; the uuid is what goes out into the world —
            // catalogue links, exports, integrations — where a sequential
            // number would leak how many products the store has and invite
            // walking the range.
            $table->uuid('uuid')->unique();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('primary_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku')->nullable()->unique();
            $table->text('description')->nullable();
            $table->string('type')->default('simple')->index();
            $table->string('status')->default('active')->index();
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->decimal('selling_price', 12, 2)->nullable();
            $table->unsignedInteger('low_stock_threshold')->default(0);
            $table->string('image_path')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
