<?php

namespace App\Http\Middleware;

use App\Enums\SupportedLocale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->query('locale')
            ?? $request->header('X-Locale')
            ?? $request->user()?->preferred_locale
            ?? $this->sessionLocale($request)
            ?? $this->fromAcceptLanguage($request)
            ?? config('app.locale', 'en');

        $locale = is_string($locale) ? strtolower(substr($locale, 0, 5)) : 'en';
        if (! in_array($locale, SupportedLocale::values(), true)) {
            $locale = 'en';
        }

        app()->setLocale($locale);

        if ($request->hasSession()) {
            $request->session()->put('locale', $locale);
        }

        return $next($request);
    }

    private function sessionLocale(Request $request): ?string
    {
        if (! $request->hasSession()) {
            return null;
        }

        $locale = $request->session()->get('locale');

        return is_string($locale) && $locale !== '' ? $locale : null;
    }

    private function fromAcceptLanguage(Request $request): ?string
    {
        $header = $request->header('Accept-Language');
        if ($header === null || $header === '') {
            return null;
        }

        $primary = strtolower(substr(trim(explode(',', $header)[0] ?? ''), 0, 2));

        return in_array($primary, SupportedLocale::values(), true) ? $primary : null;
    }
}
