<?php

declare(strict_types=1);

namespace App\Services;

use App\Dto\Interfaces\DtoInterface;
use App\Dto\WelcomeDto;
use App\Models\Welcome;
use App\Services\Interfaces\BaseServiceInterface;

class WelcomeService implements BaseServiceInterface
{
    public function __construct(
        protected WelcomeDto $dto,
        protected Welcome $welcome
    ) {}

    public function getClassName(): string
    {
        return self::class;
    }

    public function getWelcomeInfo(): ?DtoInterface
    {
        return $this->dto->getDto($this->welcome->getData());
    }
}
