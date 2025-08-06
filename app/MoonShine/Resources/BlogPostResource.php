<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Models\BlogPost;
use Illuminate\Validation\Rule;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Image;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;

/**
 * @extends ModelResource<BlogPost>
 */
class BlogPostResource extends ModelResource
{
    protected string $model = BlogPost::class;

    protected string $column = 'title';

    public function getTitle(): string
    {
        return 'Блог';
    }

    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),

            Text::make('Заголовок', 'title')
                ->sortable(),

            Text::make('Slug', 'slug')
                ->sortable(),

            Image::make('Обложка', 'image')
                ->disk('public')->dir('blog')
                ->removable(),

            Date::make('Дата публикации', 'published_at')
                ->format('Y-m-d H:i')
                ->sortable(),
        ];
    }

    protected function detailFields(): iterable
    {
        return $this->formFields();
    }

    protected function formFields(): iterable
    {
        return [
            Text::make('Заголовок', 'title')
                ->required()
                ->hint('Главный заголовок поста'),

            Text::make('Slug', 'slug')
                ->required()
                ->hint('Для красивых URL, латиница и дефисы (заполнять вручную или автогенерировать на фронте админки)'),

            Textarea::make('Анонс', 'preview')
                ->required()
                ->hint('Краткое описание/превью в ленте'),

            Textarea::make('Текст поста', 'body')
                ->required()
                ->hint('Полный текст c разметкой (поддерживается Markdown/HTML)'),

            Image::make('Обложка', 'image')
                ->disk('public')->dir('blog')
                ->removable()
                ->allowedExtensions(['jpg', 'jpeg', 'png', 'gif'])
                ->hint('Основная картинка для поста'),

            Date::make('Дата публикации', 'published_at')
                ->required()
                ->hint('Когда показать пост в блоге (Y-m-d H:i)'),
        ];
    }

    /**
     * @param  BlogPost  $item
     */
    protected function rules($item): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('blog_posts', 'slug')->ignore($item->id ?? null),
            ],
            'preview' => ['required', 'string'],
            'body' => ['required', 'string'],
            'image' => ['nullable', 'string'],
            'published_at' => ['required', 'date'],
        ];
    }

    protected function search(): array
    {
        return [
            'title',
            'slug',
            'preview',
        ];
    }
}
