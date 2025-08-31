<?php

declare(strict_types=1);

namespace App\Services;

use App\Dto\ContactInfoDto;
use App\Dto\Interfaces\DtoInterface;
use App\Helpers\CacheHelper;
use App\Models\ContactInfo;
use App\Services\Interfaces\BaseServiceInterface;
use Illuminate\Support\Facades\Cache;

class ContactInfoService implements BaseServiceInterface
{
    public function __construct(
        private readonly ContactInfo $model,
        private readonly ContactInfoDto $dto,
        private readonly CacheHelper $cacheHelper,
    ) {}

    public function getClassName(): string
    {
        return self::class;
    }

    public function getContactInfo(): ?DtoInterface
    {
        return Cache::remember(
            $this->cacheHelper->getCacheKey('contact.show', 'data'),
            CacheHelper::TTL_60,
            fn () => $this->dto->getDto($this->model->getData())
        );
    }
}
