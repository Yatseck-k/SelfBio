<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Welcome;
use Illuminate\Http\JsonResponse;

class WelcomeController extends Controller
{
    public function __construct(private readonly Welcome $welcome) {}

    public function index(): JsonResponse
    {
        $response = $this->welcome->getWelcomeData();

        return $response ? response()->json($response) : response()->json([
            'title' => 'Заглушка вместо заголовка',
            'body' => 'Loading....',
        ]);
    }
}
