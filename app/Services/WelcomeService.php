<?php

namespace App\Services;

use App\Dto\WelcomeDto;
use App\Models\Welcome;
use App\Services\Interfaces\BaseServiceInterface;

class WelcomeService implements BaseServiceInterface
{
    protected Welcome $dto;

    protected Welcome $welcome;

    public function __construct(
        WelcomeDto $dto,
        Welcome $welcome
    ) {
        $this->dto = $dto;
        $this->welcome = $welcome;
    }

    public function getClassName(): string
    {
        return self::class;
    }

    public function getWelcomeInfo(): array
    {
        return $this->dto->getData($this->welcome->getWelcomeData());
    }
}
