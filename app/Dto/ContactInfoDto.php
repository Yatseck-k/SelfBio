<?php

declare(strict_types=1);

namespace App\Dto;

use App\Dto\Interfaces\DtoInterface;
use App\Models\Interfaces\BaseModelInterface;

class ContactInfoDto implements DtoInterface
{
    public function getData(?BaseModelInterface $model): array
    {
        if (! $model) {
            return [];
        }

        return [
            'name' => $model->name,
            'phone' => $model->phone,
            'email' => $model->email,
            'telegram' => $model->telegram,
            'github' => $model->github,
            'socials' => $model->socials,
        ];
    }
}
