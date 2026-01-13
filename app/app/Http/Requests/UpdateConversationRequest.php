<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('conversation'));
    }

    public function rules(): array
    {
        $conversation = $this->route('conversation');
        
        return [
            'name' => ['sometimes', 'max:80'],
            'slug' => [
                'sometimes',
                'max:80',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('conversations')->where(function ($query) use ($conversation) {
                    return $query->where('workspace_id', $conversation->workspace_id);
                })->ignore($conversation->id),
            ],
            'topic' => ['sometimes', 'nullable', 'max:250'],
            'description' => ['sometimes', 'nullable', 'max:1000'],
            'is_archived' => ['sometimes', 'boolean'],
        ];
    }
}
