<?php

namespace App\Http\Controllers\Api;

use App\Enums\SupportedLocale;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    public function show(string $locale): JsonResponse
    {
        $validated = validator(
            ['locale' => $locale],
            ['locale' => ['required', 'string', Rule::enum(SupportedLocale::class)]],
        )->validate();

        $path = lang_path($validated['locale'].'.json');

        if (! File::exists($path)) {
            abort(404, 'Locale file not found.');
        }

        /** @var array<string, string> $messages */
        $messages = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

        return response()->json([
            'locale' => $validated['locale'],
            'messages' => $messages,
        ]);
    }
}
