<?php

namespace App\Http\Controllers;

use App\Services\ContactInfoService;
use Illuminate\Http\JsonResponse;

class ContactController extends Controller
{
    public function __construct(private readonly ContactInfoService $contactInfoService)
    {
    }

    public function show(): JsonResponse
    {
        $contact = $this->contactInfoService->getContactInfo();

        return $contact ? response()->json($contact) : response()->json(['message' => 'Not found'], 404);
    }
}
