<?php

namespace App\Services;

use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class BlogService
{
    public function __construct(private readonly CacheService $cacheService, private readonly BlogPost $blogPost)
    {
    }

    public function getPosts(Request $request): LengthAwarePaginator
    {
        return Cache::remember(
            $this->cacheService->getCacheKey('blog.index.page', $request->get('page', 1)),
            CacheService::TTL_60,
            fn () => $this->blogPost->getPosts()
        );
    }

    public function getPost(Request $request): ?BlogPost
    {
        $slug = $request->route('slug');

        return Cache::remember(
            $this->cacheService->getCacheKey('blog.show', $slug),
            CacheService::TTL_60,
            fn () => $this->blogPost->getPost($slug)
        );
    }
}
