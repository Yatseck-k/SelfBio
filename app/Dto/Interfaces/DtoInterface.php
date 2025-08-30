<?php

namespace App\Dto\Interfaces;

use App\Models\Interfaces\BaseModelInterface;

interface DtoInterface
{
    public function getData(?BaseModelInterface $model): array;
}
