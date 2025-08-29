<?php

namespace App\Http\Controllers;

use App\Services\BlogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function __construct(private readonly BlogService $blogService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->blogService->getPosts($request));
    }

    public function show(Request $request): JsonResponse
    {
        $response = $this->blogService->getPost($request);

        return $response ? response()->json($response) : response()->json(['message' => 'Not found'], 404);
    }
}
