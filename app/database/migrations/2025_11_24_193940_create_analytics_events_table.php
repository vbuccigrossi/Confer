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
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('workspace_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('event_type'); // 'message_sent', 'file_uploaded', 'search_performed', etc.
            $table->string('entity_type')->nullable(); // 'conversation', 'message', 'file', etc.
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('metadata')->nullable(); // Additional event data
            $table->string('client_type')->nullable(); // 'web', 'mobile', 'tui'
            $table->timestamps();

            $table->index(['event_type', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['workspace_id', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
