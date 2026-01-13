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
        Schema::table('invites', function (Blueprint $table) {
            // Make email nullable for invite codes (not tied to specific email)
            $table->string('email')->nullable()->change();

            // Add invite_code field for simple code-based invites
            $table->string('invite_code', 20)->nullable()->unique()->after('token');

            // Add max_uses for reusable invite codes (null = unlimited within expiry)
            $table->integer('max_uses')->nullable()->after('invite_code');

            // Add use_count to track how many times code has been used
            $table->integer('use_count')->default(0)->after('max_uses');

            // Add is_single_use flag (true = email-specific, false = reusable code)
            $table->boolean('is_single_use')->default(true)->after('use_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invites', function (Blueprint $table) {
            $table->dropColumn(['invite_code', 'max_uses', 'use_count', 'is_single_use']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
