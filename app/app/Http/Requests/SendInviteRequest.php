<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('invite', $this->route('workspace'));
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'role' => ['sometimes', Rule::in(['admin', 'member'])],
        ];
    }
}
