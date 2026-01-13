<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the existing check constraint and add a new one with 'self' included
        DB::statement('ALTER TABLE conversations DROP CONSTRAINT IF EXISTS conversations_type_check');
        DB::statement("ALTER TABLE conversations ADD CONSTRAINT conversations_type_check CHECK (type IN ('public_channel', 'private_channel', 'dm', 'group_dm', 'bot_dm', 'self'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to the original constraint without 'self'
        DB::statement('ALTER TABLE conversations DROP CONSTRAINT IF EXISTS conversations_type_check');
        DB::statement("ALTER TABLE conversations ADD CONSTRAINT conversations_type_check CHECK (type IN ('public_channel', 'private_channel', 'dm', 'group_dm', 'bot_dm'))");
    }
};
