<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('polls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->onDelete('cascade');
            $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by_user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('message_id')->nullable()->constrained()->onDelete('set null');
            $table->string('question');
            $table->json('options'); // Array of option strings
            $table->boolean('is_anonymous')->default(false);
            $table->boolean('is_multi_select')->default(false);
            $table->boolean('is_closed')->default(false);
            $table->timestamp('closes_at')->nullable();
            $table->timestamps();
            
            $table->index('workspace_id');
            $table->index('conversation_id');
        });

        Schema::create('poll_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('option_index');
            $table->timestamps();
            
            $table->unique(['poll_id', 'user_id', 'option_index']);
            $table->index('poll_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_votes');
        Schema::dropIfExists('polls');
    }
};
