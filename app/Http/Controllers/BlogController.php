<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\BlogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlogController extends Controller
{
    public function __construct(private readonly BlogService $blogService) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->blogService->getPosts($request));
    }

    public function show(Request $request): JsonResponse
    {
        $response = $this->blogService->getPost($request)->toArray();

        return $response ?
            response()->json($response) :
            response()->json(
                ['message' => Response::$statusTexts[Response::HTTP_NOT_FOUND]],
                Response::HTTP_NOT_FOUND
            );
    }
}
