<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'workspace_id' => ['required', 'exists:workspaces,id'],
            'type' => ['required', Rule::in(['public_channel', 'private_channel', 'dm', 'group_dm', 'bot_dm'])],
            'name' => ['required_if:type,public_channel,private_channel', 'max:80', 'nullable'],
            'slug' => [
                'nullable',
                'max:80',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('conversations')->where(function ($query) {
                    return $query->where('workspace_id', $this->workspace_id);
                }),
            ],
            'topic' => ['nullable', 'max:250'],
            'description' => ['nullable', 'max:1000'],
            'member_ids' => ['required_if:type,dm,group_dm,private_channel,bot_dm', 'array'],
            'member_ids.*' => ['exists:users,id'],
        ];
    }
}
