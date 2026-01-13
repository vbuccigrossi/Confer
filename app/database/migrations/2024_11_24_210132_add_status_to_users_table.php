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
        Schema::table('users', function (Blueprint $table) {
            $table->string('status')->default('active')->after('email'); // active, away, dnd, invisible
            $table->string('status_message')->nullable()->after('status'); // Custom status text
            $table->string('status_emoji')->nullable()->after('status_message'); // Emoji for status
            $table->timestamp('status_expires_at')->nullable()->after('status_emoji'); // When to auto-clear status
            $table->boolean('is_dnd')->default(false)->after('status_expires_at'); // Do Not Disturb mode
            $table->timestamp('dnd_until')->nullable()->after('is_dnd'); // When DND expires
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'status_message',
                'status_emoji',
                'status_expires_at',
                'is_dnd',
                'dnd_until',
            ]);
        });
    }
};
