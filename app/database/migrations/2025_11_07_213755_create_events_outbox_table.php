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
        Schema::create('events_outbox', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->onDelete('cascade');
            $table->foreignId('app_id')->constrained('apps')->onDelete('cascade');
            $table->string('event_type');
            $table->json('payload');
            $table->enum('delivery_status', ['pending', 'success', 'failed'])->default('pending');
            $table->integer('attempt_count')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            // Indices
            $table->index(['app_id', 'delivery_status']);
            $table->index(['delivery_status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events_outbox');
    }
};
