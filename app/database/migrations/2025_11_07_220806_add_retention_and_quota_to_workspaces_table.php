<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->integer('message_retention_days')->nullable()->comment('Message retention in days; null = unlimited');
            $table->integer('storage_quota_mb')->default(1024)->comment('Storage quota in megabytes');
            $table->integer('storage_used_mb')->default(0)->comment('Current storage usage in megabytes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn(['message_retention_days', 'storage_quota_mb', 'storage_used_mb']);
        });
    }
};
