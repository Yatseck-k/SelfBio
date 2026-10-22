<?php

declare(strict_types=1);

namespace App\Orchid\Screens;

use App\Models\BlogPost;
use App\Orchid\Layouts\BlogPostListLayout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Toast;

class BlogPostListScreen extends Screen
{
    public function query(): iterable
    {
        return ['posts' => BlogPost::query()->latest()->paginate(20)];
    }

    public function name(): ?string
    {
        return 'Блог '.config('app.name');
    }

    public function permission(): ?iterable
    {
        return ['platform.selfbio.blog.view'];
    }

    public function commandBar(): iterable
    {
        return [Link::make('Новая запись')->icon('bs.plus-circle')
            ->route('platform.selfbio.blog.create')
            ->canSee(request()->user()?->hasAccess('platform.selfbio.blog.edit') ?? false)];
    }

    public function layout(): iterable
    {
        return [BlogPostListLayout::class];
    }

    public function remove(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasAccess('platform.selfbio.blog.edit'), 403);

        $post = BlogPost::query()->findOrFail($request->integer('post'));
        $post->delete();
        Cache::forever('selfbio.blog.version', (string) microtime(true));
        Toast::success('Запись удалена.');

        return to_route('platform.selfbio.blog');
    }
}
