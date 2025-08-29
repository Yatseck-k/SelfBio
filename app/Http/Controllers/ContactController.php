<?php

namespace App\Http\Controllers;

use App\Models\ContactInfo;
use App\Services\ContactInfoService;
use Illuminate\Http\JsonResponse;

class ContactController extends Controller {
    private ContactInfoService $contactInfoService;

    public function __construct(ContactInfoService $contactInfoService) {
        $this->contactInfoService = $contactInfoService;
    }

    public function show(): JsonResponse {
        $contact = $this->contactInfoService->getContactInfo();

        return $contact ? response()->json($contact) : response()->json(['message' => 'Not found'], 404);
    }
}
