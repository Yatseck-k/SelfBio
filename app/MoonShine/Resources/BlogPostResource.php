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
        return __('moonshine::blog.resource.title');
    }

    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),

            Text::make(__('moonshine::blog.fields.title'), 'title')
                ->sortable(),

            Text::make(__('moonshine::blog.fields.slug'), 'slug')
                ->sortable(),

            Image::make(__('moonshine::blog.fields.image'), 'image')
                ->disk('public')
                ->dir('blog')
                ->removable(),

            Date::make(__('moonshine::blog.fields.published_at'), 'published_at')
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
            Text::make(__('moonshine::blog.fields.title'), 'title')
                ->required()
                ->hint(__('moonshine::blog.hints.title')),

            Text::make(__('moonshine::blog.fields.slug'), 'slug')
                ->required()
                ->hint(__('moonshine::blog.hints.slug')),

            Textarea::make(__('moonshine::blog.fields.preview'), 'preview')
                ->required()
                ->hint(__('moonshine::blog.hints.preview')),

            Textarea::make(__('moonshine::blog.fields.body'), 'body')
                ->required()
                ->hint(__('moonshine::blog.hints.body')),

            Image::make(__('moonshine::blog.fields.image'), 'image')
                ->disk('public')->dir('blog')
                ->removable()
                ->allowedExtensions(['jpg', 'jpeg', 'png', 'gif'])
                ->hint(__('moonshine::blog.hints.image')),

            Date::make(__('moonshine::blog.fields.published_at'), 'published_at')
                ->required()
                ->hint(__('moonshine::blog.hints.published_at')),
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
