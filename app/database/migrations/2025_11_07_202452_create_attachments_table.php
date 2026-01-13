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
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->nullable()->constrained('messages')->onDelete('set null');
            $table->foreignId('uploader_id')->constrained('users')->onDelete('cascade');
            $table->string('storage_path', 512);
            $table->string('disk', 32)->default('local');
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size_bytes');
            $table->string('file_name', 255);
            $table->unsignedInteger('image_width')->nullable();
            $table->unsignedInteger('image_height')->nullable();
            $table->string('thumbnail_path', 512)->nullable();
            $table->char('sha256', 64);
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('message_id');
            $table->index(['uploader_id', 'created_at']);
            $table->index('sha256');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
