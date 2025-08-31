<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\WelcomeService;
use Illuminate\Http\JsonResponse;

class WelcomeController extends Controller
{
    public function __construct(
        private readonly WelcomeService $welcomeService
    ) {}

    public function index(): JsonResponse
    {
        $response = $this->welcomeService->getWelcomeInfo()->toArray();

        return $response ? response()->json($response) : response()->json([
            'title' => 'Заглушка вместо заголовка',
            'body' => 'Loading....',
        ]);
    }
}
