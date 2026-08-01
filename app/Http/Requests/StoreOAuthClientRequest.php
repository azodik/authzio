<?php

namespace App\Http\Requests;

use App\Enums\ApplicationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOAuthClientRequest extends FormRequest
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
        $type = ApplicationType::tryFrom((string) $this->input('application_type', 'web'));
        $requiresRedirects = $type !== ApplicationType::Machine;

        return [
            'organization_id' => ['sometimes', 'uuid', 'exists:organizations,id'],
            'name' => ['required', 'string', 'max:255'],
            'application_type' => ['required', 'string', Rule::enum(ApplicationType::class)],
            'description' => ['nullable', 'string', 'max:1000'],
            'redirect_uris' => [$requiresRedirects ? 'required' : 'nullable', 'array', $requiresRedirects ? 'min:1' : 'max:20'],
            'redirect_uris.*' => ['required', 'string', 'url', 'max:2048'],
            'grant_types' => ['sometimes', 'array', 'min:1'],
            'grant_types.*' => ['required', 'string', Rule::in([
                'authorization_code',
                'client_credentials',
                'refresh_token',
            ])],
            'is_confidential' => ['sometimes', 'boolean'],
            'is_first_party' => ['sometimes', 'boolean'],
        ];
    }
}
