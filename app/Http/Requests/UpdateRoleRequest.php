<?php

namespace App\Http\Requests;

use App\Enums\OrgPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['sometimes', 'array', 'min:1'],
            'permissions.*' => ['string', Rule::in(OrgPermission::allSlugs())],
        ];
    }
}
