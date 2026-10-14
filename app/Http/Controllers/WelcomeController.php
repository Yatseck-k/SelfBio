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
        $response = $this->welcomeService->getWelcomeInfo()?->toArray();

        return response()->json($response ?? [
            'title' => __('Welcome!'),
            'body' => __('Explore my projects, play a detective game, or sign in to view renovation materials.'),
        ]);
    }
}
