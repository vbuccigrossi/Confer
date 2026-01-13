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
        Schema::table('bots', function (Blueprint $table) {
            // JSON schema defining configuration fields the bot accepts
            // Structure: { "fields": [ { "name", "type", "label", "required", ... } ] }
            $table->json('config_schema')->nullable()->after('webhook_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bots', function (Blueprint $table) {
            $table->dropColumn('config_schema');
        });
    }
};
