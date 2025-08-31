<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ContactInfoService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ContactController extends Controller
{
    public function __construct(
        private readonly ContactInfoService $contactInfoService
    ) {}

    public function show(): JsonResponse
    {
        $response = $this->contactInfoService->getContactInfo()->toArray();

        return !empty($response) ?
            response()->json($response) :
            response()->json(
                ['message' => Response::$statusTexts[Response::HTTP_NOT_FOUND]],
                Response::HTTP_NOT_FOUND
            );
    }
}
