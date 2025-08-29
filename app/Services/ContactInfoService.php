<?php

namespace App\Services;

use App\Models\ContactInfo;

class ContactInfoService
{
    public function __construct(private readonly ContactInfo $contactInfo)
    {
    }

    public function getContactInfo(): ?ContactInfo
    {
        return $this->contactInfo->getContactInfo();
    }
}
