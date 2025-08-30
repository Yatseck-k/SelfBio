<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Interfaces\BaseModelInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class BlogPost extends Model implements BaseModelInterface
{
    protected $fillable = [
        'title',
        'slug',
        'preview',
        'body',
        'image',
        'published_at',
    ];

    public function getClassName(): string
    {
        return self::class;
    }

    public function getPosts(): LengthAwarePaginator
    {
        return self::query()->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at')
            ->paginate(10);
    }

    public function getPost(string $slug): ?BaseModelInterface
    {
        return self::query()->where('slug', $slug)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->first();
    }

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
