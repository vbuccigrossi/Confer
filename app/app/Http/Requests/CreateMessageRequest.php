<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CreateMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Get conversation from route parameter
        $conversation = $this->route('conversation');

        return Gate::allows('create', [\App\Models\Message::class, $conversation]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body_md' => 'required|string|min:1|max:4000',
            'parent_message_id' => 'nullable|exists:messages,id',
            'attachments' => 'nullable|array',
            'attachments.*' => 'integer|exists:attachments,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'body_md.required' => 'Message content is required.',
            'body_md.max' => 'Message cannot exceed 4000 characters.',
            'parent_message_id.exists' => 'The parent message does not exist.',
        ];
    }
}
