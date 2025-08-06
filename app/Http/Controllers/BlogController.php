<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BlogController extends Controller
{
    public function index(Request $request)
    {
        $page = $request->get('page', 1);
        $cacheKey = "blog.index.page.{$page}";

        $posts = Cache::remember($cacheKey, 60, fn () => BlogPost::whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at')
            ->paginate(10));

        return response()->json($posts);
    }

    public function show($slug)
    {
        $cacheKey = "blog.show.{$slug}";

        $post = Cache::remember($cacheKey, 60, fn () => BlogPost::where('slug', $slug)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->first());

        if (! $post) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json($post);
    }
}
