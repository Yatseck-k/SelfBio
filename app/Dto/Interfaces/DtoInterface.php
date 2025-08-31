<?php

declare(strict_types=1);

namespace App\Dto\Interfaces;

use App\Models\Interfaces\BaseModelInterface;

interface DtoInterface
{
    public function getDto(?BaseModelInterface $model): ?self;

    public function toArray(): array;
}
