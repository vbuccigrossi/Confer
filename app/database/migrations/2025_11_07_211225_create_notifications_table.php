<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('type'); // 'mention', 'thread_reply'
            $table->foreignId('actor_user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('conversation_id')->constrained('conversations')->onDelete('cascade');
            $table->foreignId('message_id')->constrained('messages')->onDelete('cascade');
            $table->json('payload')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            // Indexes for common queries
            $table->index(['user_id', 'is_read', 'created_at']);
            $table->index('message_id');
            $table->index('conversation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
