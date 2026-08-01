<?php

namespace App\Http\Requests;

use App\Enums\LoginLayout;
use App\Enums\LoginTheme;
use App\Enums\SocialProvider;
use App\Enums\SupportedLocale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOAuthClientRequest extends FormRequest
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
        $socialRules = [];
        foreach (SocialProvider::values() as $provider) {
            $socialRules['login_methods.'.$provider] = ['sometimes', 'boolean'];
        }

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'redirect_uris' => ['sometimes', 'array', 'max:20'],
            'redirect_uris.*' => ['required', 'string', 'url', 'max:2048'],
            'logo_url' => ['nullable', 'string', 'url', 'max:2048'],
            'primary_color' => ['sometimes', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'background_color' => ['sometimes', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'login_headline' => ['nullable', 'string', 'max:120'],
            'login_description' => ['nullable', 'string', 'max:500'],
            'login_button_label' => ['sometimes', 'string', 'max:60'],
            'show_signup_link' => ['sometimes', 'boolean'],
            'show_forgot_password_link' => ['sometimes', 'boolean'],
            'default_locale' => ['sometimes', 'string', 'max:5', Rule::in(SupportedLocale::values())],
            'allow_locale_switch' => ['sometimes', 'boolean'],
            'login_layout' => ['sometimes', 'string', Rule::in(LoginLayout::values())],
            'login_theme' => ['sometimes', 'string', Rule::in(LoginTheme::values())],
            'password_policy' => ['sometimes', 'array'],
            'password_policy.min_length' => ['sometimes', 'integer', 'min:8', 'max:128'],
            'password_policy.require_mixed_case' => ['sometimes', 'boolean'],
            'password_policy.require_numbers' => ['sometimes', 'boolean'],
            'password_policy.require_symbols' => ['sometimes', 'boolean'],
            'security_policy' => ['sometimes', 'array'],
            'security_policy.mfa_required' => ['sometimes', 'boolean'],
            'security_policy.session_lifetime_minutes' => ['sometimes', 'integer', 'min:5', 'max:10080'],
            'security_policy.single_device' => ['sometimes', 'boolean'],
            'login_methods' => ['sometimes', 'array'],
            'login_methods.password' => ['sometimes', 'boolean'],
            'login_methods.passkey' => ['sometimes', 'boolean'],
            'login_methods.email_otp' => ['sometimes', 'boolean'],
            'login_methods.sync_profile' => ['sometimes', 'boolean'],
            'login_methods.require_verified_email' => ['sometimes', 'boolean'],
            'login_methods.allow_unverified_email_with_otp' => ['sometimes', 'boolean'],
            ...$socialRules,
            'terms_url' => ['nullable', 'string', 'url', 'max:2048'],
            'privacy_url' => ['nullable', 'string', 'url', 'max:2048'],
            'require_legal_accept' => ['sometimes', 'boolean'],
        ];
    }
}
