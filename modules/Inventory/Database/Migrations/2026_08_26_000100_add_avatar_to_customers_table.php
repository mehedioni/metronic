<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A customer's photo. Only the relative path is stored, alongside the disk
     * it was written to, so the URL is generated on read and switching storage
     * provider leaves these rows resolving. See docs/file-storage.md.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('avatar_disk', 40)->nullable()->after('notes');
            $table->string('avatar_path')->nullable()->after('avatar_disk');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['avatar_disk', 'avatar_path']);
        });
    }
};
