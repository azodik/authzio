<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');

        // COOP only applies on trustworthy origins (HTTPS / localhost).
        if ($request->secure()) {
            $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy());
        }

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        $scriptSrc = ["'self'"];
        $styleSrc = ["'self'", "'unsafe-inline'"];
        $connectSrc = ["'self'"];
        $fontSrc = ["'self'", 'data:'];
        $imgSrc = ["'self'", 'data:', 'blob:', 'https:', 'http:'];

        if (app()->environment('local')) {
            $viteHttp = 'http://authzio.test:5173';
            $viteHttps = 'https://authzio.test:5173';
            $viteLocal = 'http://127.0.0.1:5173';

            foreach ([$viteHttps, $viteHttp, $viteLocal] as $origin) {
                $scriptSrc[] = $origin;
                $styleSrc[] = $origin;
                $connectSrc[] = $origin;
                $fontSrc[] = $origin;
            }

            // Vite React Fast Refresh injects an inline module preamble.
            $scriptSrc[] = "'unsafe-inline'";
            $scriptSrc[] = "'unsafe-eval'";
            $connectSrc[] = 'wss://authzio.test:5173';
            $connectSrc[] = 'ws://authzio.test:5173';
            $connectSrc[] = 'ws://127.0.0.1:5173';
        }

        $frameSrc = ["'none'"];

        if ($this->marketingTrackingEnabled()) {
            // Inline bootstraps for GTM / Meta / Reddit (IDs come from env, not user input).
            $scriptSrc[] = "'unsafe-inline'";
            $scriptSrc = array_merge($scriptSrc, [
                'https://www.googletagmanager.com',
                'https://www.google-analytics.com',
                'https://connect.facebook.net',
                'https://snap.licdn.com',
                'https://www.redditstatic.com',
            ]);
            $connectSrc = array_merge($connectSrc, [
                'https://www.googletagmanager.com',
                'https://www.google-analytics.com',
                'https://region1.google-analytics.com',
                'https://www.facebook.com',
                'https://connect.facebook.net',
                'https://px.ads.linkedin.com',
                'https://www.linkedin.com',
                'https://www.redditstatic.com',
                'https://alb.reddit.com',
            ]);
            $frameSrc = [
                'https://www.googletagmanager.com',
                'https://www.facebook.com',
            ];
        }

        $directives = [
            "default-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "object-src 'none'",
            'script-src '.implode(' ', array_unique($scriptSrc)),
            'style-src '.implode(' ', $styleSrc),
            'img-src '.implode(' ', $imgSrc),
            'font-src '.implode(' ', $fontSrc),
            'connect-src '.implode(' ', array_unique($connectSrc)),
            'frame-src '.implode(' ', $frameSrc),
        ];

        if (app()->environment('production')) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives);
    }

    private function marketingTrackingEnabled(): bool
    {
        foreach ([
            'marketing.gtm_id',
            'marketing.ga4_id',
            'marketing.meta_pixel_id',
            'marketing.linkedin_partner_id',
            'marketing.reddit_pixel_id',
        ] as $key) {
            if (filled(config($key))) {
                return true;
            }
        }

        return false;
    }
}
