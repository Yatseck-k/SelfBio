<?php

namespace App\Dto;

use App\Dto\Interfaces\DtoInterface;
use App\Models\Interfaces\BaseModelInterface;

class BlogPostDto implements DtoInterface
{
    public function getData(?BaseModelInterface $model): array
    {
        if (! $model) {
            return [];
        }

        return [
            'title' => $model->title,
            'slug' => $model->slug,
            'preview' => $model->preview,
            'body' => $model->body,
            'image' => $model->image,
            'published_at' => $model->published_at,
        ];
    }
}
