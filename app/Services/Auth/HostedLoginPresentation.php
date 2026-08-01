<?php

namespace App\Services\Auth;

use App\Enums\LoginLayout;
use App\Enums\LoginTheme;
use App\Enums\SupportedLocale;
use App\Models\OAuthClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;

final class HostedLoginPresentation
{
    public const LOCALE_COOKIE = 'authzio_hosted_locale';

    /**
     * Resolve locale/layout/theme for hosted login views and set the app locale.
     *
     * @return array{
     *     login_layout: string,
     *     login_theme: string,
     *     locale: string,
     *     allow_locale_switch: bool,
     *     available_locales: list<string>,
     *     layout_class: string,
     *     theme_class: string
     * }
     */
    public function apply(Request $request, OAuthClient $client): array
    {
        $locale = $this->resolveLocale($request, $client);
        App::setLocale($locale);

        if ($request->filled('ui_locales') || $request->filled('locale')) {
            Cookie::queue(cookie(
                self::LOCALE_COOKIE,
                $locale,
                120,
                '/',
                null,
                $request->isSecure(),
                true,
                false,
                'Lax',
            ));
        }

        $layout = LoginLayout::tryFrom((string) ($client->login_layout ?? LoginLayout::FormRight->value))
            ?? LoginLayout::FormRight;
        $theme = LoginTheme::tryFrom((string) ($client->login_theme ?? LoginTheme::Light->value))
            ?? LoginTheme::Light;

        return [
            'login_layout' => $layout->value,
            'login_theme' => $theme->value,
            'locale' => $locale,
            'allow_locale_switch' => (bool) $client->allow_locale_switch,
            'available_locales' => SupportedLocale::values(),
            'layout_class' => 'layout layout--'.$layout->cssModifier(),
            'theme_class' => 'theme-'.$theme->value,
        ];
    }

    public function resolveLocale(Request $request, OAuthClient $client): string
    {
        $default = $this->normalizeLocale($client->default_locale) ?? SupportedLocale::En->value;

        if (! $client->allow_locale_switch) {
            return $default;
        }

        foreach ([
            $request->query('ui_locales'),
            $request->query('locale'),
            $request->cookie(self::LOCALE_COOKIE),
        ] as $candidate) {
            $normalized = $this->normalizeLocale($candidate);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return $default;
    }

    private function normalizeLocale(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $first = explode(' ', trim($value))[0];
        $normalized = strtolower(substr(str_replace('_', '-', $first), 0, 2));

        return in_array($normalized, SupportedLocale::values(), true) ? $normalized : null;
    }
}
