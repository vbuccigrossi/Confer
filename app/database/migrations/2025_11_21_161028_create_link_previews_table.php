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
        Schema::create('link_previews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->onDelete('cascade');
            $table->string('url', 2048);
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('image_url', 2048)->nullable();
            $table->string('site_name')->nullable();
            $table->string('screenshot_path')->nullable();
            $table->timestamps();

            $table->index('message_id');
            $table->index('url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('link_previews');
    }
};
