<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class LanguageController extends Controller
{
    public function changeLanguage(Request $request): JsonResponse
    {
        $request->validate([
            'locale' => 'required|string|in:en,ru',
        ]);

        $locale = $request->input('locale');

        App::setLocale($locale);

        Session::put('locale', $locale);

        cookie()->queue('locale', $locale, 525600);

        return response()->json([
            'success' => true,
            'locale' => $locale,
            'message' => 'Язык успешно изменен',
        ]);
    }
}
