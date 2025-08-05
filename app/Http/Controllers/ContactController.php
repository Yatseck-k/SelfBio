<?php

namespace App\Http\Controllers;

use App\Models\ContactInfo;

class ContactController extends Controller
{
    public function show()
    {
        $contact = ContactInfo::first();
        if (! $contact) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json($contact);
    }
}
