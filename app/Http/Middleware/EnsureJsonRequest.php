<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureJsonRequest
{
    /**
     * Reject non-JSON API mutations to reduce CSRF-style form posts.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/v1/webhooks/*') || $request->is('api/oauth/*')) {
            return $next($request);
        }

        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            && ! $request->isJson()
            && ! $request->ajax()
            && ! $this->isMultipart($request)
        ) {
            return response()->json([
                'message' => 'Requests must send JSON with an Accept: application/json header.',
            ], 415);
        }

        return $next($request);
    }

    private function isMultipart(Request $request): bool
    {
        $contentType = (string) $request->header('Content-Type', '');

        return str_starts_with($contentType, 'multipart/form-data')
            || $request->files->count() > 0;
    }
}
