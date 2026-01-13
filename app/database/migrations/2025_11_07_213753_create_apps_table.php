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
        Schema::create('apps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->onDelete('cascade');
            $table->string('name');
            $table->enum('type', ['bot', 'webhook', 'slash']);
            $table->string('client_id')->unique();
            $table->string('client_secret')->nullable(); // hashed
            $table->string('token')->unique(); // hashed
            $table->json('scopes')->default('[]');
            $table->string('callback_url')->nullable();
            $table->foreignId('default_conversation_id')->nullable()->constrained('conversations')->onDelete('set null');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indices
            $table->index(['workspace_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apps');
    }
};
