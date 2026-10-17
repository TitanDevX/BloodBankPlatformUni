<?php

namespace Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'displayName' => ['required', 'string', 'min:1', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'initialPassword' => ['required', 'string', 'min:12', 'max:128'],
            'role' => ['required', 'string', 'regex:/^roles\/[a-z_]+$/'],
            'bank' => ['nullable', 'string', 'regex:/^banks\/[a-z0-9][a-z0-9-]{0,63}$/'],
            'hospital' => ['nullable', 'string', 'regex:/^hospitals\/[a-z0-9][a-z0-9-]{0,63}$/'],
            'active' => ['required', 'boolean'],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
