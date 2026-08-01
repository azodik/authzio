<?php

namespace App\Services;

use App\Enums\EmailTemplateSlug;
use App\Models\EmailTemplate;
use App\Models\OAuthClient;
use App\Models\Organization;
use App\Services\Mail\EmailHtml;

class EmailTemplateRenderer
{
    /**
     * @return array<string, string>
     */
    public function sampleVariables(Organization $organization, ?OAuthClient $client = null): array
    {
        $appName = $client?->name ?? $organization->name;
        $product = (string) config('app.name', 'Authzio');

        return [
            'organization_name' => $organization->name,
            'product_name' => $product,
            'user_name' => 'Alex Rivera',
            'inviter_name' => 'Alex Rivera',
            'role' => 'member',
            'invite_url' => url('/invite/preview-token'),
            'expires_at' => now()->addDays(7)->toDayDateTimeString(),
            'expires_minutes' => (string) config('auth.passwords.users.expire', 60),
            'magic_link_url' => url('/magic/preview-token'),
            'reset_url' => url('/console/reset-password?token=preview&email=alex@example.com'),
            'verify_url' => url('/verify/preview-token'),
            'console_url' => rtrim((string) config('app.url'), '/').'/console',
            'billing_url' => rtrim((string) config('app.url'), '/').'/console/'.$organization->id.'/billing',
            'previous_plan_name' => 'Free',
            'plan_name' => 'Starter',
            'mau_count' => '8,250',
            'mau_limit' => '5,000',
            'utilization_percent' => '82.5',
            'threshold_percent' => '80',
            'mfa_code' => '847291',
            'otp_code' => '847291',
            'application_name' => $appName,
        ];
    }

    /**
     * @param  array<string, string>  $variables
     */
    public function render(string $template, array $variables): string
    {
        $replacements = [];
        foreach ($variables as $key => $value) {
            $replacements['{{'.$key.'}}'] = $value;
        }

        return strtr($template, $replacements);
    }

    /**
     * @return array{subject: string, html: string, text: string|null, variables: array<string, string>}
     */
    public function preview(EmailTemplate $template, Organization $organization, ?OAuthClient $client = null): array
    {
        $variables = $this->sampleVariables($organization, $client);
        $slug = EmailTemplateSlug::tryFrom($template->slug);

        $subjectSource = $template->subject !== ''
            ? $template->subject
            : ($slug?->defaultSubject() ?? $template->name);
        $htmlSource = $template->body_html !== ''
            ? $template->body_html
            : ($slug?->defaultHtml() ?? '<p>Preview</p>');

        $body = $this->render($htmlSource, $variables);

        return [
            'subject' => $this->render($subjectSource, $variables),
            'html' => EmailHtml::wrap($body, $organization->name, includeLogo: false),
            'text' => $template->body_text !== null
                ? $this->render($template->body_text, $variables)
                : null,
            'variables' => $variables,
        ];
    }
}
