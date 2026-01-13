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
        Schema::create('arxiv_papers', function (Blueprint $table) {
            $table->id();
            $table->string('arxiv_id')->unique();
            $table->string('title', 500);
            $table->text('summary')->nullable();
            $table->json('authors');
            $table->json('categories');
            $table->string('primary_category');
            $table->string('pdf_url');
            $table->string('abs_url');
            $table->timestamp('published_at');
            $table->timestamp('updated_at_arxiv')->nullable();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'posted_at']);
            $table->index(['arxiv_id', 'workspace_id']);
            $table->index('primary_category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arxiv_papers');
    }
};
