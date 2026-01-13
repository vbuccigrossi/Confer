<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            // PostgreSQL: Add tsvector column and FTS infrastructure
            Schema::table('messages', function (Blueprint $table) {
                // Add tsvector column for full-text search
                DB::statement('ALTER TABLE messages ADD COLUMN body_tsv tsvector');
            });

            // Create GIN index for fast full-text search
            DB::statement('CREATE INDEX messages_body_tsv_gin ON messages USING GIN(body_tsv)');

            // Create trigger function to auto-update body_tsv on INSERT/UPDATE
            DB::statement("
                CREATE OR REPLACE FUNCTION messages_body_tsv_update() RETURNS trigger AS $$
                BEGIN
                    NEW.body_tsv := to_tsvector('english', COALESCE(NEW.body_md, ''));
                    RETURN NEW;
                END
                $$ LANGUAGE plpgsql;
            ");

            // Create trigger
            DB::statement("
                CREATE TRIGGER messages_body_tsv_update_trigger
                BEFORE INSERT OR UPDATE ON messages
                FOR EACH ROW EXECUTE FUNCTION messages_body_tsv_update();
            ");

            // Backfill existing messages
            DB::statement("
                UPDATE messages
                SET body_tsv = to_tsvector('english', COALESCE(body_md, ''))
                WHERE body_tsv IS NULL;
            ");
        } else {
            // SQLite/MySQL: Add a simple text column for basic search
            // Full-text search will be simulated in the controller
            Schema::table('messages', function (Blueprint $table) {
                $table->text('body_tsv')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            // Drop trigger
            DB::statement('DROP TRIGGER IF EXISTS messages_body_tsv_update_trigger ON messages');

            // Drop trigger function
            DB::statement('DROP FUNCTION IF EXISTS messages_body_tsv_update');

            // Drop index
            DB::statement('DROP INDEX IF EXISTS messages_body_tsv_gin');

            // Drop column
            Schema::table('messages', function (Blueprint $table) {
                DB::statement('ALTER TABLE messages DROP COLUMN IF EXISTS body_tsv');
            });
        } else {
            // SQLite/MySQL: Just drop the column
            Schema::table('messages', function (Blueprint $table) {
                $table->dropColumn('body_tsv');
            });
        }
    }
};
