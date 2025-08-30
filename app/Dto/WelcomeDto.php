<?php

declare(strict_types=1);

namespace App\Dto;

use App\Models\Interfaces\BaseModelInterface;

class WelcomeDto
{
    public function getData(?BaseModelInterface $model): array
    {
        if (!$model) {
            return [];
        }

        return [
            'title' => $model->title,
            'body' => $model->body,
        ];
    }
}
