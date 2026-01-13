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
        Schema::create('bot_installations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained()->onDelete('cascade');
            $table->foreignId('workspace_id')->constrained()->onDelete('cascade');
            $table->foreignId('installed_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->json('config')->nullable(); // Bot-specific configuration
            $table->boolean('is_active')->default(true);
            $table->timestamp('installed_at')->useCurrent();
            $table->timestamps();

            $table->unique(['bot_id', 'workspace_id']);
            $table->index('workspace_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_installations');
    }
};
