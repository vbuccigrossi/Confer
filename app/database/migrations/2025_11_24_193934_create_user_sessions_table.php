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
        Schema::create('user_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('workspace_id')->constrained()->onDelete('cascade');
            $table->string('client_type'); // 'web', 'mobile', 'tui'
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('last_activity_at');
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration_seconds')->nullable(); // Calculated when session ends
            $table->timestamps();

            $table->index(['user_id', 'started_at']);
            $table->index(['workspace_id', 'started_at']);
            $table->index('client_type');
            $table->index('started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_sessions');
    }
};
