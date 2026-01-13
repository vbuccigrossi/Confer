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
        Schema::table('messages', function (Blueprint $table) {
            $table->unsignedInteger('reply_count')->default(0)->after('parent_message_id');
            $table->timestamp('last_reply_at')->nullable()->after('reply_count');
            $table->foreignId('last_reply_user_id')->nullable()->after('last_reply_at')->constrained('users')->nullOnDelete();

            // Add index for thread queries
            $table->index(['parent_message_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['parent_message_id', 'created_at']);
            $table->dropForeign(['last_reply_user_id']);
            $table->dropColumn(['reply_count', 'last_reply_at', 'last_reply_user_id']);
        });
    }
};
