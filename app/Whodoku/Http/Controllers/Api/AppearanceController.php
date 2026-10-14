<?php

declare(strict_types=1);

namespace Whodoku\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Whodoku\Application\Appearance\AppearanceService;
use Whodoku\Http\Controllers\Controller;

class AppearanceController extends Controller
{
    public function show(AppearanceService $appearance): JsonResponse
    {
        return response()->json($appearance->publicPayload());
    }
}
