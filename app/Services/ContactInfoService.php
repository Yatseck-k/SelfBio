<?php

declare(strict_types=1);

namespace App\Services;

use App\Dto\ContactInfoDto;
use App\Models\ContactInfo;

readonly class ContactInfoService
{
    public function __construct(
        private ContactInfoDto $dto,
        private ContactInfo $contactInfo
    ) {}

    public function getContactInfo(): array
    {
        return $this->dto->getData($this->contactInfo->getContactInfo());
    }
}
