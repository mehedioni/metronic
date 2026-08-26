<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Images belonging to a product, or to one of its variants.
     *
     * A table rather than a column on products, because a product has many
     * images, they have an order, and one of them is the primary. None of that
     * fits in a single string.
     *
     * "path" is always relative — never a full URL. The URL is generated on
     * read by App\Core\Services\FileStorageService, so switching storage
     * provider needs no migration.
     *
     * "disk" records where the bytes actually went. After FILES_DISK changes
     * from public to s3, rows written before the switch still resolve against
     * the disk that holds them.
     */
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->string('disk')->nullable();
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            // The gallery reads a product's images in order.
            $table->index(['product_id', 'sort_order']);
            // Finding the primary is a single-row lookup, not a scan.
            $table->index(['product_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
