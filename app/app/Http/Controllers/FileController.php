<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadFileRequest;
use App\Models\Attachment;
use App\Services\FileStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FileController extends Controller
{
    protected FileStorageService $fileStorageService;

    public function __construct(FileStorageService $fileStorageService)
    {
        $this->fileStorageService = $fileStorageService;
    }

    /**
     * Upload a file
     *
     * POST /api/files
     */
    public function store(UploadFileRequest $request): JsonResponse
    {
        Gate::authorize('create', Attachment::class);

        try {
            $attachment = $this->fileStorageService->store(
                $request->file('file'),
                $request->user(),
                $request->input('message_id')
            );

            // Load relationships for response
            $attachment->load('uploader');

            return response()->json([
                'id' => $attachment->id,
                'message_id' => $attachment->message_id,
                'file_name' => $attachment->file_name,
                'mime_type' => $attachment->mime_type,
                'file_type' => $attachment->file_type,
                'size_bytes' => $attachment->size_bytes,
                'size_human' => $attachment->size_human,
                'url' => $attachment->getSignedUrl(),
                'thumbnail_url' => $attachment->getThumbnailUrl(),
                'has_preview' => $attachment->hasPreview(),
                'image_width' => $attachment->image_width,
                'image_height' => $attachment->image_height,
                'created_at' => $attachment->created_at,
                'uploader' => [
                    'id' => $attachment->uploader->id,
                    'name' => $attachment->uploader->name,
                ],
            ], 201);
        } catch (\Exception $e) {
            \Log::error('File upload failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Upload failed',
                'message' => 'An error occurred during file upload. Please try again.',
            ], 422);
        }
    }

    /**
     * Download a file (requires signed URL)
     *
     * GET /api/files/{attachment}
     */
    public function show(Request $request, Attachment $attachment): BinaryFileResponse
    {
        // Validate signature
        if (!$request->hasValidSignature()) {
            abort(401, 'Invalid or expired signature');
        }

        // Check if user can view (if authenticated)
        if ($request->user()) {
            Gate::authorize('view', $attachment);
        }

        // Validate path to prevent traversal attacks
        $this->validateFilePath($attachment->disk, $attachment->storage_path);

        // Stream the file
        if (!Storage::disk($attachment->disk)->exists($attachment->storage_path)) {
            abort(404, 'File not found');
        }

        return response()->file(
            Storage::disk($attachment->disk)->path($attachment->storage_path),
            [
                'Content-Type' => $attachment->mime_type,
                'Content-Disposition' => 'inline; filename="' . $attachment->file_name . '"',
            ]
        );
    }

    /**
     * Download thumbnail (requires signed URL)
     *
     * GET /api/files/{attachment}/thumbnail
     */
    public function thumbnail(Request $request, Attachment $attachment): BinaryFileResponse
    {
        // Validate signature
        if (!$request->hasValidSignature()) {
            abort(401, 'Invalid or expired signature');
        }

        // Check if thumbnail exists
        if (!$attachment->thumbnail_path) {
            abort(404, 'Thumbnail not available');
        }

        // Check if user can view (if authenticated)
        if ($request->user()) {
            Gate::authorize('view', $attachment);
        }

        // Validate path to prevent traversal attacks
        $this->validateFilePath($attachment->disk, $attachment->thumbnail_path);

        // Stream the thumbnail
        if (!Storage::disk($attachment->disk)->exists($attachment->thumbnail_path)) {
            abort(404, 'Thumbnail not found');
        }

        return response()->file(
            Storage::disk($attachment->disk)->path($attachment->thumbnail_path),
            [
                'Content-Type' => 'image/jpeg',
                'Content-Disposition' => 'inline; filename="thumb_' . $attachment->file_name . '"',
            ]
        );
    }

    /**
     * Delete an attachment
     *
     * DELETE /api/files/{attachment}
     */
    public function destroy(Request $request, Attachment $attachment): Response
    {
        Gate::authorize('delete', $attachment);

        try {
            $this->fileStorageService->delete($attachment);

            return response()->noContent();
        } catch (\Exception $e) {
            \Log::error('File delete failed', [
                'attachment_id' => $attachment->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Delete failed',
                'message' => 'An error occurred while deleting the file. Please try again.',
            ], 422);
        }
    }

    /**
     * Validate file path to prevent path traversal attacks
     */
    protected function validateFilePath(string $disk, string $path): void
    {
        // Check for path traversal patterns
        if (str_contains($path, '..') || str_contains($path, '//')) {
            abort(403, 'Invalid file path');
        }

        // Get the real path and verify it's within the storage directory
        $fullPath = Storage::disk($disk)->path($path);
        $basePath = Storage::disk($disk)->path('');

        $realPath = realpath(dirname($fullPath));
        $realBase = realpath($basePath);

        // Verify the real path starts with the base path
        if (!$realPath || !$realBase || !str_starts_with($realPath, $realBase)) {
            abort(403, 'Invalid file path - path traversal detected');
        }
    }
}
