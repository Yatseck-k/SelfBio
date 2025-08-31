<?php

declare(strict_types=1);

namespace App\Services;

use App\Dto\Interfaces\DtoInterface;
use App\Dto\WelcomeDto;
use App\Helpers\CacheHelper;
use App\Models\Welcome;
use App\Services\Interfaces\BaseServiceInterface;
use Illuminate\Support\Facades\Cache;

class WelcomeService implements BaseServiceInterface
{
    public function __construct(
        private readonly Welcome $model,
        private readonly WelcomeDto $dto,
        private readonly CacheHelper $cacheHelper,
    ) {}

    public function getClassName(): string
    {
        return self::class;
    }

    public function getWelcomeInfo(): ?DtoInterface
    {
        return Cache::remember(
            $this->cacheHelper->getCacheKey('welcome.index', 'data'),
            CacheHelper::TTL_60,
            fn () => $this->dto->getDto($this->model->getData())
        );
    }
}
