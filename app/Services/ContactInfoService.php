<?php

namespace App\Services;

use App\Models\ContactInfo;

class ContactInfoService {

    private ContactInfo $contactInfo;

    public function __construct(ContactInfo $contactInfo) {
        $this->contactInfo = $contactInfo;
    }

    public function getContactInfo(): ?ContactInfo {
        return $this->contactInfo->getContactInfo();
    }
}
