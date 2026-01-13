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
        Schema::create('slash_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_installation_id')->constrained()->onDelete('cascade');
            $table->string('command'); // e.g., "news", "jira", "ai"
            $table->text('description')->nullable();
            $table->string('usage_hint')->nullable(); // e.g., "/news [category]"
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['bot_installation_id', 'command']);
            $table->index('command');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slash_commands');
    }
};
