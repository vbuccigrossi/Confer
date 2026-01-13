<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LinkPreview extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_id',
        'url',
        'title',
        'description',
        'image_url',
        'site_name',
        'screenshot_path',
    ];

    /**
     * Get the message that owns the link preview
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * Get the full URL for the screenshot
     */
    public function getScreenshotUrlAttribute(): ?string
    {
        if (!$this->screenshot_path) {
            return null;
        }

        return asset('storage/' . $this->screenshot_path);
    }
}
