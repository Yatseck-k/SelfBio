<?php

declare(strict_types=1);

namespace App\Orchid\Screens;

use App\Models\BlogPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class BlogPostEditScreen extends Screen
{
    public function query(?BlogPost $post = null): iterable
    {
        $post ??= new BlogPost;

        return [
            'post' => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'preview' => $post->preview,
                'body' => $post->body,
                'image' => $post->image,
                // The field shows the time of the site's own zone; a new post starts at «now».
                'published_at' => ($post->published_at ?? now())->copy()->timezone(config('app.display_timezone'))->format('Y-m-d\TH:i'),
            ],
        ];
    }

    public function name(): ?string
    {
        return request()->route('post') ? 'Редактировать запись' : 'Новая запись';
    }

    public function permission(): ?iterable
    {
        return ['platform.selfbio.blog.edit'];
    }

    public function commandBar(): iterable
    {
        return [Button::make('Сохранить')->icon('bs.check-circle')->method('save')];
    }

    public function layout(): iterable
    {
        return [Layout::rows([
            Input::make('post.title')->title('Название')->required(),
            Input::make('post.slug')->title('Slug')->required()->help('Строчные латинские буквы, цифры и дефис.'),
            TextArea::make('post.preview')->title('Анонс')->required()->rows(4),
            TextArea::make('post.body')->title('Текст')->required()->rows(16),
            Input::make('post.published_at')->title('Дата публикации')->type('datetime-local')->required(),
            Input::make('image_upload')->title('Изображение')->type('file')->accept('image/jpeg,image/png,image/gif')
                ->help('JPEG, PNG или GIF, не более 5 МБ.'),
        ]), Layout::view('orchid.selfbio.blog-image')];
    }

    public function save(Request $request, ?BlogPost $post = null): RedirectResponse
    {
        abort_unless($request->user()?->hasAccess('platform.selfbio.blog.edit'), 403);
        $post ??= new BlogPost;

        $data = $request->validate([
            'post.title' => ['required', 'string', 'max:255'],
            'post.slug' => ['required', 'regex:/^[a-z0-9-]+$/', Rule::unique('pgsql.blog_posts', 'slug')->ignore($post->id)],
            'post.preview' => ['required', 'string'],
            'post.body' => ['required', 'string'],
            'post.published_at' => ['required', 'date'],
            'image_upload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif', 'max:5120'],
        ]);

        // The editor types the time of the site's zone, the database keeps UTC (the public list compares with «now»).
        $data['post']['published_at'] = Date::parse($data['post']['published_at'], config('app.display_timezone'))->utc();
        $post->fill($data['post']);
        $old = $post->image;
        if ($request->hasFile('image_upload')) {
            $post->image = $request->file('image_upload')->store('blog', 'public');
        }
        $post->save();
        if ($old && $old !== $post->image) {
            Storage::disk('public')->delete($old);
        }
        Cache::forever('selfbio.blog.version', (string) microtime(true));
        Toast::success('Запись сохранена.');

        return to_route('platform.selfbio.blog.edit', ['post' => $post->id]);
    }
}
