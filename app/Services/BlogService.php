<?php

declare(strict_types=1);

namespace App\Services;

use App\Dto\BlogPostDto;
use App\Dto\Interfaces\DtoInterface;
use App\Helpers\CacheHelper;
use App\Models\BlogPost;
use App\Services\Interfaces\BaseServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class BlogService implements BaseServiceInterface
{
    public function __construct(
        private readonly CacheHelper $cacheHelper,
        private readonly BlogPost $blogPost,
        private readonly BlogPostDto $dto,
    ) {}

    public function getClassName(): string
    {
        return self::class;
    }

    public function getPosts(Request $request): LengthAwarePaginator
    {
        return Cache::remember(
            $this->cacheHelper->getCacheKey('blog.index.page', $request->get('page', 1)),
            CacheHelper::TTL_60,
            fn () => $this->blogPost->getPosts()
        );
    }

    public function getPost(Request $request): ?DtoInterface
    {
        $slug = $request->route('slug');

        return Cache::remember(
            $this->cacheHelper->getCacheKey('blog.show', $slug),
            CacheHelper::TTL_60,
            fn () => $this->dto->getDto($this->blogPost->getPost($slug))
        );
    }
}
