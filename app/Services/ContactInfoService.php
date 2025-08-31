<?php

declare(strict_types=1);

namespace App\Services;

use App\Dto\ContactInfoDto;
use App\Dto\Interfaces\DtoInterface;
use App\Models\ContactInfo;
use App\Services\Interfaces\BaseServiceInterface;

class ContactInfoService implements BaseServiceInterface
{
    public function __construct(
        private readonly ContactInfoDto $dto,
        private readonly ContactInfo $contactInfo
    ) {}

    public function getClassName(): string
    {
        return self::class;
    }

    public function getContactInfo(): ?DtoInterface
    {
        return $this->dto->getDto($this->contactInfo->getData());
    }
}
