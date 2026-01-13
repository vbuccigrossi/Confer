<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Only run for PostgreSQL (production database)
        // SQLite doesn't support DROP CONSTRAINT and doesn't need this
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE conversations DROP CONSTRAINT conversations_type_check");
            DB::statement("ALTER TABLE conversations ADD CONSTRAINT conversations_type_check CHECK (type IN ('public_channel', 'private_channel', 'dm', 'group_dm', 'bot_dm'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Only run for PostgreSQL
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE conversations DROP CONSTRAINT conversations_type_check");
            DB::statement("ALTER TABLE conversations ADD CONSTRAINT conversations_type_check CHECK (type IN ('public_channel', 'private_channel', 'dm', 'group_dm'))");
        }
    }
};
