<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Models\Welcome;
use Illuminate\Database\Eloquent\Builder;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;

/**
 * @extends ModelResource<Welcome>
 */
class WelcomeResource extends ModelResource
{
    protected string $model = Welcome::class;

    protected string $column = 'title';

    public function getTitle(): string
    {
        return __('moonshine::welcome.resource.title');
    }

    public function canCreate(): bool
    {
        return false;
    }

    public function canDelete(): bool
    {
        return false;
    }

    protected function indexQuery(): Builder
    {
        return $this->getModel()->newQuery()->limit(1);
    }

    protected function indexFields(): iterable
    {
        return $this->formFields();
    }

    protected function detailFields(): iterable
    {
        return $this->formFields();
    }

    protected function formFields(): iterable
    {
        return [
            ID::make()->sortable(),

            Text::make(__('moonshine::welcome.fields.title'), 'title')
                ->required()
                ->hint(__('moonshine::welcome.hints.title')),

            Textarea::make(__('moonshine::welcome.fields.body'), 'body')
                ->required()
                ->hint(__('moonshine::welcome.hints.body')),
        ];
    }

    protected function rules(mixed $item): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ];
    }
}
