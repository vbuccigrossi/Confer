<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class FileStorageService
{
    /**
     * Store an uploaded file
     */
    public function store(UploadedFile $file, User $uploader, ?int $messageId = null): Attachment
    {
        // Validate MIME type
        $this->validateMimeType($file);

        // Check quota (basic implementation - checks user's first workspace)
        $workspace = $uploader->workspaces()->first();
        if ($workspace) {
            $this->checkQuota($workspace->id, $file->getSize());
        }

        // Compute SHA256 hash
        $hash = $this->computeHash($file);

        // Sanitize filename
        $sanitizedName = $this->sanitizeFilename($file->getClientOriginalName());

        // Generate unique storage path
        $disk = config('files.disk', 'local');
        $storagePath = 'uploads/' . date('Y/m/d') . '/'. Str::uuid() . '.' . $file->getClientOriginalExtension();

        // Store the file
        Storage::disk($disk)->put($storagePath, file_get_contents($file->getRealPath()));

        // Create attachment record
        $attachment = Attachment::create([
            'message_id' => $messageId,
            'uploader_id' => $uploader->id,
            'storage_path' => $storagePath,
            'disk' => $disk,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'file_name' => $sanitizedName,
            'sha256' => $hash,
        ]);

        // If image, extract dimensions and generate thumbnail
        if ($this->isImageMime($file->getMimeType())) {
            $this->processImage($attachment, $file);
        }

        // If PDF, generate thumbnail from first page
        if ($this->isPdfMime($file->getMimeType())) {
            $this->processPdf($attachment, $file);
        }

        // If video, extract thumbnail from first frame
        if ($this->isVideoMime($file->getMimeType())) {
            $this->processVideo($attachment, $file);
        }

        return $attachment->fresh();
    }

    /**
     * Process image: extract dimensions and generate thumbnail
     */
    protected function processImage(Attachment $attachment, UploadedFile $file): void
    {
        try {
            $image = Image::read($file->getRealPath());

            // Update dimensions
            $attachment->update([
                'image_width' => $image->width(),
                'image_height' => $image->height(),
            ]);

            // Generate thumbnail
            $this->generateThumbnail($attachment, $image);
        } catch (\Exception $e) {
            // Log error but don't fail the upload
            \Log::warning('Failed to process image', [
                'attachment_id' => $attachment->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Generate thumbnail for an image
     */
    public function generateThumbnail(Attachment $attachment, $image = null): ?string
    {
        if (!$attachment->isImage()) {
            return null;
        }

        try {
            // Load image if not provided
            if ($image === null) {
                $filePath = Storage::disk($attachment->disk)->path($attachment->storage_path);
                $image = Image::read($filePath);
            }

            $maxWidth = config('files.thumbnail.max_width', 512);
            $maxHeight = config('files.thumbnail.max_height', 512);
            $quality = config('files.thumbnail.quality', 80);

            // Resize maintaining aspect ratio
            $image->scaleDown($maxWidth, $maxHeight);

            // Generate thumbnail path
            $thumbnailPath = 'thumbnails/' . basename($attachment->storage_path, '.' . pathinfo($attachment->storage_path, PATHINFO_EXTENSION)) . '.jpg';

            // Save thumbnail
            $thumbnailData = $image->toJpeg($quality);
            Storage::disk($attachment->disk)->put($thumbnailPath, $thumbnailData);

            // Update attachment
            $attachment->update(['thumbnail_path' => $thumbnailPath]);

            return $thumbnailPath;
        } catch (\Exception $e) {
            \Log::warning('Failed to generate thumbnail', [
                'attachment_id' => $attachment->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Delete an attachment (soft delete + optionally remove file)
     */
    public function delete(Attachment $attachment, bool $removeFile = false): bool
    {
        if ($removeFile) {
            // Delete physical files
            Storage::disk($attachment->disk)->delete($attachment->storage_path);
            if ($attachment->thumbnail_path) {
                Storage::disk($attachment->disk)->delete($attachment->thumbnail_path);
            }
        }

        // Soft delete the record
        return $attachment->delete();
    }

    /**
     * Check if workspace has enough quota
     */
    public function checkQuota(int $workspaceId, int $sizeBytes): bool
    {
        $quotaMb = config('files.workspace_quota_mb', 1024);
        $quotaBytes = $quotaMb * 1024 * 1024;

        // Calculate current usage
        $currentUsage = Attachment::whereHas('message.conversation', function ($query) use ($workspaceId) {
            $query->where('workspace_id', $workspaceId);
        })->sum('size_bytes');

        $availableSpace = $quotaBytes - $currentUsage;

        if ($sizeBytes > $availableSpace) {
            throw new \Exception(sprintf(
                'Workspace quota exceeded. Available: %s, Required: %s',
                $this->formatBytes($availableSpace),
                $this->formatBytes($sizeBytes)
            ));
        }

        return true;
    }

    /**
     * Validate MIME type against whitelist
     */
    protected function validateMimeType(UploadedFile $file): void
    {
        $allowedMimes = config('files.allowed_mimes', []);
        $fileMime = $file->getMimeType();

        if (!in_array($fileMime, $allowedMimes, true)) {
            throw new \Exception(sprintf(
                'File type not allowed: %s',
                $fileMime
            ));
        }
    }

    /**
     * Compute SHA256 hash of file
     */
    protected function computeHash(UploadedFile $file): string
    {
        return hash_file('sha256', $file->getRealPath());
    }

    /**
     * Sanitize filename to prevent path traversal and other attacks
     */
    protected function sanitizeFilename(string $filename): string
    {
        // Remove directory separators
        $filename = str_replace(['/', '\\', "\0"], '_', $filename);

        // Remove special characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/u', '_', $filename);

        // Limit length
        if (strlen($filename) > 255) {
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $name = substr(pathinfo($filename, PATHINFO_FILENAME), 0, 250);
            $filename = $name . '.' . $ext;
        }

        return $filename;
    }

    /**
     * Process PDF: generate thumbnail from first page
     */
    protected function processPdf(Attachment $attachment, UploadedFile $file): void
    {
        try {
            $filePath = Storage::disk($attachment->disk)->path($attachment->storage_path);

            // Generate thumbnail path
            $thumbnailPath = 'thumbnails/' . basename($attachment->storage_path, '.pdf') . '.jpg';
            $thumbnailFullPath = Storage::disk($attachment->disk)->path($thumbnailPath);

            // Ensure thumbnail directory exists
            $thumbnailDir = dirname($thumbnailFullPath);
            if (!file_exists($thumbnailDir)) {
                mkdir($thumbnailDir, 0755, true);
            }

            // Use Imagick to convert first page to JPG
            if (class_exists('Imagick')) {
                $imagick = new \Imagick();
                $imagick->setResolution(150, 150);
                $imagick->readImage($filePath . '[0]'); // First page only
                $imagick->setImageFormat('jpeg');
                $imagick->setImageCompressionQuality(80);

                // Resize to thumbnail size
                $maxWidth = config('files.thumbnail.max_width', 512);
                $maxHeight = config('files.thumbnail.max_height', 512);
                $imagick->thumbnailImage($maxWidth, $maxHeight, true);

                // Save thumbnail
                $imagick->writeImage($thumbnailFullPath);
                $imagick->clear();
                $imagick->destroy();

                // Update attachment
                $attachment->update(['thumbnail_path' => $thumbnailPath]);
            } else {
                \Log::warning('Imagick not available for PDF thumbnail generation', [
                    'attachment_id' => $attachment->id,
                ]);
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to process PDF', [
                'attachment_id' => $attachment->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Process video: extract thumbnail from first frame
     */
    protected function processVideo(Attachment $attachment, UploadedFile $file): void
    {
        try {
            $filePath = Storage::disk($attachment->disk)->path($attachment->storage_path);

            // Generate thumbnail path
            $thumbnailPath = 'thumbnails/' . basename($attachment->storage_path, '.' . pathinfo($attachment->storage_path, PATHINFO_EXTENSION)) . '.jpg';
            $thumbnailFullPath = Storage::disk($attachment->disk)->path($thumbnailPath);

            // Ensure thumbnail directory exists
            $thumbnailDir = dirname($thumbnailFullPath);
            if (!file_exists($thumbnailDir)) {
                mkdir($thumbnailDir, 0755, true);
            }

            // Use FFmpeg to extract frame at 1 second
            $ffmpegPath = config('files.ffmpeg_path', 'ffmpeg');

            // Check if ffmpeg is available
            exec("which $ffmpegPath 2>&1", $output, $returnCode);
            if ($returnCode !== 0) {
                \Log::warning('FFmpeg not available for video thumbnail generation', [
                    'attachment_id' => $attachment->id,
                ]);
                return;
            }

            // Extract frame using ffmpeg
            $command = sprintf(
                '%s -i %s -ss 00:00:01.000 -vframes 1 -vf "scale=%d:%d:force_original_aspect_ratio=decrease" -q:v 2 %s 2>&1',
                $ffmpegPath,
                escapeshellarg($filePath),
                config('files.thumbnail.max_width', 512),
                config('files.thumbnail.max_height', 512),
                escapeshellarg($thumbnailFullPath)
            );

            exec($command, $output, $returnCode);

            if ($returnCode === 0 && file_exists($thumbnailFullPath)) {
                // Update attachment
                $attachment->update(['thumbnail_path' => $thumbnailPath]);
            } else {
                \Log::warning('FFmpeg failed to generate video thumbnail', [
                    'attachment_id' => $attachment->id,
                    'command' => $command,
                    'output' => implode("\n", $output),
                    'return_code' => $returnCode,
                ]);
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to process video', [
                'attachment_id' => $attachment->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Check if MIME type is an image
     */
    protected function isImageMime(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'image/');
    }

    /**
     * Check if MIME type is a PDF
     */
    protected function isPdfMime(string $mimeType): bool
    {
        return $mimeType === 'application/pdf';
    }

    /**
     * Check if MIME type is a video
     */
    protected function isVideoMime(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'video/');
    }

    /**
     * Format bytes to human-readable string
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 1) . ' ' . $units[$i];
    }
}
