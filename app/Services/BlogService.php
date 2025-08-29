<?php

namespace App\Services;

use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class BlogService
{
    private CacheService $cacheService;

    private BlogPost $blogPost;

    public function __construct(CacheService $cacheService, BlogPost $blogPost)
    {
        $this->cacheService = $cacheService;
        $this->blogPost = $blogPost;
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
