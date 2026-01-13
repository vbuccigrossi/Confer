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
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->enum('notify_level', ['all', 'mentions', 'nothing'])->default('all');
            $table->boolean('mobile_push')->default(true);
            $table->boolean('desktop_push')->default(true);
            $table->boolean('email')->default(false);
            $table->timestamp('muted_until')->nullable();
            $table->timestamps();

            // One preference per user per conversation
            $table->unique(['user_id', 'conversation_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
