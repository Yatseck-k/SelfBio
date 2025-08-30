<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Models\ContactInfo;
use Illuminate\Database\Eloquent\Builder;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Json;
use MoonShine\UI\Fields\Text;

class ContactInfoResource extends ModelResource
{
    protected string $model = ContactInfo::class;

    protected string $column = 'name';

    public function getTitle(): string
    {
        return __('moonshine::contacts.resource.title');
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
            Text::make(__('moonshine::contacts.fields.name'), 'name')->required(),
            Text::make(__('moonshine::contacts.fields.phone'), 'phone'),
            Text::make(__('moonshine::contacts.fields.email'), 'email')->required(),
            Text::make(__('moonshine::contacts.fields.telegram'), 'telegram'),
            Text::make(__('moonshine::contacts.fields.github'), 'github'),
            Json::make(__('moonshine::contacts.fields.socials'), 'socials')
                ->hint(__('moonshine::contacts.hints.socials')),
        ];
    }

    protected function rules($item): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'telegram' => ['nullable', 'string', 'max:100'],
            'github' => ['nullable', 'string', 'max:100'],
            'socials' => ['nullable', 'array'],
        ];
    }
}
