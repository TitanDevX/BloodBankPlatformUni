<?php

namespace Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignUserRoleRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', 'regex:/^roles\/[a-z_]+$/'],
            'bank' => ['nullable', 'string', 'regex:/^banks\/[a-z0-9][a-z0-9-]{0,63}$/'],
            'hospital' => ['nullable', 'string', 'regex:/^hospitals\/[a-z0-9][a-z0-9-]{0,63}$/'],
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
