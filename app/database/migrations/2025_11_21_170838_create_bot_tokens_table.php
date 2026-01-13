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
        Schema::create('bot_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_installation_id')->constrained()->onDelete('cascade');
            $table->string('token', 64)->unique(); // Hashed token
            $table->string('name')->default('Default Token');
            $table->json('scopes')->nullable(); // ['messages:write', 'messages:read', etc.]
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('token');
            $table->index('bot_installation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_tokens');
    }
};
