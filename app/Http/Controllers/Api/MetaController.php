<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\BuildInfo;
use Illuminate\Http\JsonResponse;

class MetaController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(BuildInfo::toArray());
    }
}
