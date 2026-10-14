<?php

declare(strict_types=1);

namespace App\Orchid\Layouts;

use App\Models\BlogPost;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class BlogPostListLayout extends Table
{
    #[\Override]
    public $target = 'posts';

    public function columns(): array
    {
        return [
            TD::make('title', 'Название')->render(fn (BlogPost $post) => Link::make($post->title)
                ->route('platform.selfbio.blog.edit', ['post' => $post->id])),
            TD::make('slug', 'Slug'),
            TD::make('published_at', 'Опубликовано')->render(fn (BlogPost $post) => $post->published_at?->format('d.m.Y H:i') ?? '—'),
            TD::make('actions', '')->render(fn (BlogPost $post) => Button::make('Удалить')
                ->icon('bs.trash3')
                ->confirm('Удалить запись «'.$post->title.'»?')
                ->method('remove', ['post' => $post->id])
                ->canSee(request()->user()?->hasAccess('platform.selfbio.blog.edit') ?? false)),
        ];
    }
}
