<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\URL;

/**
 * Attachment model - file uploads linked to messages
 *
 * @property int $id
 * @property int|null $message_id
 * @property int $uploader_id
 * @property string $storage_path
 * @property string $disk
 * @property string $mime_type
 * @property int $size_bytes
 * @property string $file_name
 * @property int|null $image_width
 * @property int|null $image_height
 * @property string|null $thumbnail_path
 * @property string $sha256
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Attachment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'message_id',
        'uploader_id',
        'storage_path',
        'disk',
        'mime_type',
        'size_bytes',
        'file_name',
        'image_width',
        'image_height',
        'thumbnail_path',
        'sha256',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'image_width' => 'integer',
        'image_height' => 'integer',
    ];

    protected $appends = [
        'size_human',
        'file_type',
    ];

    /**
     * Get the message this attachment belongs to
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * Get the user who uploaded this file
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    /**
     * Check if this attachment is an image
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Check if this attachment is a PDF
     */
    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    /**
     * Check if this attachment is a video
     */
    public function isVideo(): bool
    {
        return str_starts_with($this->mime_type, 'video/');
    }

    /**
     * Check if this attachment is an audio file
     */
    public function isAudio(): bool
    {
        return str_starts_with($this->mime_type, 'audio/');
    }

    /**
     * Check if this attachment has a preview available
     */
    public function hasPreview(): bool
    {
        return $this->thumbnail_path !== null;
    }

    /**
     * Get file type category (image, video, audio, pdf, document, other)
     */
    public function getFileTypeAttribute(): string
    {
        if ($this->isImage()) {
            return 'image';
        }

        if ($this->isVideo()) {
            return 'video';
        }

        if ($this->isAudio()) {
            return 'audio';
        }

        if ($this->isPdf()) {
            return 'pdf';
        }

        // Check for common document types
        $documentMimes = [
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            'application/rtf',
        ];

        if (in_array($this->mime_type, $documentMimes)) {
            return 'document';
        }

        return 'other';
    }

    /**
     * Get human-readable file size
     */
    public function getSizeHumanAttribute(): string
    {
        $bytes = $this->size_bytes;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 1) . ' ' . $units[$i];
    }

    /**
     * Generate a signed URL for downloading this file
     */
    public function getSignedUrl(int $ttlMinutes = null): string
    {
        $ttl = $ttlMinutes ?? config('files.sign_ttl_minutes', 15);

        return URL::temporarySignedRoute(
            'files.show',
            now()->addMinutes($ttl),
            ['attachment' => $this->id]
        );
    }

    /**
     * Generate a signed URL for the thumbnail
     */
    public function getThumbnailUrl(): ?string
    {
        if (!$this->thumbnail_path) {
            return null;
        }

        $ttl = config('files.sign_ttl_minutes', 15);

        return URL::temporarySignedRoute(
            'files.thumbnail',
            now()->addMinutes($ttl),
            ['attachment' => $this->id]
        );
    }

    /**
     * Scope: Filter by message
     */
    public function scopeForMessage($query, int $messageId)
    {
        return $query->where('message_id', $messageId);
    }

    /**
     * Scope: Filter by uploader
     */
    public function scopeByUploader($query, int $uploaderId)
    {
        return $query->where('uploader_id', $uploaderId);
    }

    /**
     * Scope: Only images
     */
    public function scopeImages($query)
    {
        return $query->where('mime_type', 'like', 'image/%');
    }

    /**
     * Scope: Recently uploaded (last 24 hours)
     */
    public function scopeRecent($query)
    {
        return $query->where('created_at', '>=', now()->subDay());
    }
}
