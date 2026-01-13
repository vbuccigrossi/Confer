<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('addMembers', $this->route('conversation'));
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'role' => ['sometimes', Rule::in(['owner', 'admin', 'member'])],
        ];
    }
}
