<?php

namespace App\Services\Mail;

use App\Enums\EmailProviderDriver;
use App\Enums\EmailTemplateSlug;
use App\Enums\SupportedLocale;
use App\Jobs\SendRenderedMailJob;
use App\Models\EmailTemplate;
use App\Models\Organization;
use App\Models\OrganizationEmailProvider;
use App\Models\User;
use App\Services\Billing\PlanEntitlements;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class TransactionalMailer
{
    public function __construct(
        private readonly EmailUsageTracker $usage,
    ) {}

    private function entitlements(): PlanEntitlements
    {
        return app(PlanEntitlements::class);
    }

    /**
     * Authzio system mail (console account, billing, welcome, verification, etc.).
     * Always uses the platform mailer from .env — never an organization's BYO provider.
     *
     * @param  array<string, string>  $variables
     */
    public function sendPlatform(
        string $to,
        EmailTemplateSlug $slug,
        array $variables = [],
        ?string $locale = null,
        bool $queue = true,
    ): void {
        $locale = $this->resolveLocale($locale, $to);
        $variables = $this->withPlatformDefaults($variables, $locale);

        [$subject, $html] = $this->withLocale($locale, function () use ($slug, $variables, $locale): array {
            $variables = $this->localizeVariableLabels($variables);
            $subject = $this->render($this->localizedSubject($slug), $variables);
            $body = $this->render($slug->defaultHtml(), $variables);

            return [
                $subject,
                EmailHtml::wrap(
                    $body,
                    $variables['product_name'] ?? EmailHtml::productName(),
                    includeLogo: true,
                    locale: $locale,
                ),
            ];
        });

        $this->dispatchPlatform($to, $subject, $html, $queue);
    }

    /**
     * Organization end-user mail (hosted login OTP, password reset for OAuth users, etc.).
     * Uses the org BYO provider on paid plans; free plans use platform mail with usage caps.
     *
     * @param  array<string, string>  $variables
     */
    public function sendOrganization(
        Organization $organization,
        string $to,
        EmailTemplateSlug $slug,
        array $variables = [],
        ?string $locale = null,
        bool $queue = true,
    ): void {
        $locale = $this->resolveLocale($locale, $to);
        $entitlements = $this->entitlements()->forOrganization($organization);
        $provider = $organization->emailProvider;

        $template = EmailTemplate::query()
            ->where('organization_id', $organization->id)
            ->where('slug', $slug->value)
            ->where('is_active', true)
            ->first();

        $variables = $this->withPlatformDefaults($variables, $locale);
        $variables['organization_name'] ??= $organization->name;

        [$subject, $html] = $this->withLocale($locale, function () use ($template, $slug, $variables, $organization, $locale): array {
            $variables = $this->localizeVariableLabels($variables);
            $subjectSource = $template?->subject ?: $this->localizedSubject($slug);
            $htmlSource = $template?->body_html ?: $slug->defaultHtml();
            $subject = $this->render($subjectSource, $variables);
            $body = $this->render($htmlSource, $variables);

            // Client-facing org mail: brand as text only — never Authzio logo.
            return [
                $subject,
                EmailHtml::wrap(
                    $body,
                    $organization->name,
                    includeLogo: false,
                    locale: $locale,
                ),
            ];
        });

        if ($entitlements['allows_custom_email_provider']) {
            if ($provider === null || ! $provider->is_active) {
                throw ValidationException::withMessages([
                    'email' => [__('Configure an email provider before sending organization emails.')],
                ]);
            }

            $this->dispatchViaProvider($provider, $to, $subject, $html, $queue);

            return;
        }

        // Free plan: Authzio platform mail with caps (end-user / OAuth emails only).
        $this->usage->assertCanSend($organization);
        $this->dispatchPlatform($to, $subject, $html, $queue);
        $this->usage->increment($organization);
    }

    public function deliverPlatform(string $to, string $subject, string $html): void
    {
        Mail::html($html, function ($message) use ($to, $subject): void {
            $message->to($to)->subject($subject);
        });
    }

    public function deliverViaProvider(
        OrganizationEmailProvider $provider,
        string $to,
        string $subject,
        string $html,
    ): void {
        $mailerName = 'org_'.$provider->organization_id;
        $credentials = $provider->credentials;

        match ($provider->driver) {
            EmailProviderDriver::Smtp => Config::set("mail.mailers.{$mailerName}", [
                'transport' => 'smtp',
                'host' => $credentials['host'] ?? '',
                'port' => (int) ($credentials['port'] ?? 587),
                'encryption' => $credentials['encryption'] ?? 'tls',
                'username' => $credentials['username'] ?? null,
                'password' => $credentials['password'] ?? null,
                'timeout' => 30,
            ]),
            EmailProviderDriver::Resend => Config::set("mail.mailers.{$mailerName}", [
                'transport' => 'resend',
                'key' => $credentials['api_key'] ?? '',
            ]),
            EmailProviderDriver::Postmark => Config::set("mail.mailers.{$mailerName}", [
                'transport' => 'postmark',
                'token' => $credentials['api_key'] ?? '',
            ]),
            EmailProviderDriver::Ses => Config::set("mail.mailers.{$mailerName}", [
                'transport' => 'ses',
                'key' => $credentials['key'] ?? null,
                'secret' => $credentials['secret'] ?? null,
                'region' => $credentials['region'] ?? 'us-east-1',
            ]),
            EmailProviderDriver::Mailgun => Config::set("mail.mailers.{$mailerName}", [
                'transport' => 'mailgun',
                'domain' => $credentials['domain'] ?? '',
                'secret' => $credentials['api_key'] ?? '',
                'endpoint' => $credentials['endpoint'] ?? 'api.mailgun.net',
            ]),
        };

        try {
            Mail::mailer($mailerName)->html($html, function ($message) use ($to, $subject, $provider): void {
                $message->to($to)
                    ->subject($subject)
                    ->from($provider->from_address, $provider->from_name ?: $provider->from_address);
            });
            $provider->update(['last_error' => null, 'verified_at' => $provider->verified_at ?? now()]);
        } catch (\Throwable $exception) {
            $provider->update(['last_error' => $exception->getMessage()]);
            throw ValidationException::withMessages([
                'email' => [__('Failed to send email via your provider: :message', ['message' => $exception->getMessage()])],
            ]);
        }
    }

    /**
     * @param  array<string, string>  $variables
     * @return array<string, string>
     */
    private function withPlatformDefaults(array $variables, string $locale): array
    {
        return array_merge([
            'product_name' => (string) config('app.name', 'Authzio'),
            'console_url' => rtrim((string) config('app.url'), '/').'/console',
            'expires_minutes' => (string) config('auth.passwords.users.expire', 60),
            'user_name' => 'there',
            'locale' => $locale,
        ], $variables);
    }

    private function localizedSubject(EmailTemplateSlug $slug): string
    {
        $key = 'mail.'.$slug->value.'.subject';
        $translated = __($key);

        return $translated === $key ? $slug->defaultSubject() : $translated;
    }

    private function resolveLocale(?string $locale, string $email): string
    {
        if ($locale !== null && $locale !== '' && in_array($locale, SupportedLocale::values(), true)) {
            return $locale;
        }

        $preferred = User::query()->where('email', $email)->value('preferred_locale');

        if (is_string($preferred) && in_array($preferred, SupportedLocale::values(), true)) {
            return $preferred;
        }

        return SupportedLocale::En->value;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function withLocale(string $locale, callable $callback): mixed
    {
        $previous = app()->getLocale();
        app()->setLocale($locale);

        try {
            return $callback();
        } finally {
            app()->setLocale($previous);
        }
    }

    /**
     * Resolve deferred translation keys for labels (e.g. mail.metric.*) after locale is set.
     *
     * @param  array<string, string>  $variables
     * @return array<string, string>
     */
    private function localizeVariableLabels(array $variables): array
    {
        foreach (['metric_label', 'period_label'] as $key) {
            $value = $variables[$key] ?? null;
            if (is_string($value) && str_starts_with($value, 'mail.')) {
                $variables[$key] = __($value);
            }
        }

        return $variables;
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function render(string $template, array $variables): string
    {
        $replacements = [];
        foreach ($variables as $key => $value) {
            $replacements['{{'.$key.'}}'] = $value;
        }

        return strtr($template, $replacements);
    }

    private function dispatchPlatform(string $to, string $subject, string $html, bool $queue): void
    {
        if (! $queue) {
            $this->deliverPlatform($to, $subject, $html);

            return;
        }

        SendRenderedMailJob::dispatch($to, $subject, $html);
    }

    private function dispatchViaProvider(
        OrganizationEmailProvider $provider,
        string $to,
        string $subject,
        string $html,
        bool $queue,
    ): void {
        if (! $queue) {
            $this->deliverViaProvider($provider, $to, $subject, $html);

            return;
        }

        SendRenderedMailJob::dispatch($to, $subject, $html, $provider->id);
    }
}
