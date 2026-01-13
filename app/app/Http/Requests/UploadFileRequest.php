<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadFileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by policy
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxSizeMb = config('files.max_upload_size_mb', 64);
        $maxSizeKb = $maxSizeMb * 1024;

        // Get allowed MIME types from config and convert to extensions
        $allowedMimes = config('files.allowed_mimes', []);
        $extensions = array_map(function ($mime) {
            $parts = explode('/', $mime);
            return end($parts);
        }, $allowedMimes);

        return [
            'file' => [
                'required',
                'file',
                'max:' . $maxSizeKb,
                'mimes:' . implode(',', $extensions),
            ],
            'message_id' => [
                'nullable',
                'integer',
                'exists:messages,id',
            ],
        ];
    }

    /**
     * Custom validation messages
     */
    public function messages(): array
    {
        $maxSizeMb = config('files.max_upload_size_mb', 64);
        $allowedMimes = config('files.allowed_mimes', []);
        $extensions = implode(', ', array_map(function ($mime) {
            $parts = explode('/', $mime);
            return end($parts);
        }, $allowedMimes));

        return [
            'file.required' => 'A file is required for upload.',
            'file.file' => 'The uploaded item must be a valid file.',
            'file.max' => "File size must not exceed {$maxSizeMb}MB.",
            'file.mimes' => "File type not allowed. Allowed types: {$extensions}.",
            'message_id.exists' => 'The specified message does not exist.',
        ];
    }
}
