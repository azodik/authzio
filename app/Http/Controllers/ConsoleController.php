<?php

namespace App\Http\Controllers;

use App\Support\BuildInfo;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ConsoleController extends Controller
{
    public function show(Request $request): View
    {
        $theme = $request->user()?->theme;

        return view('console', [
            'themePreference' => is_string($theme) && $theme !== '' ? $theme : null,
            'buildInfo' => BuildInfo::toArray(),
        ]);
    }
}
