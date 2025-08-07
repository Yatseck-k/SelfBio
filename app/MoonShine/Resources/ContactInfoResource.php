<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Models\ContactInfo;
use Illuminate\Database\Eloquent\Builder;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Json;
use MoonShine\UI\Fields\Text;

#[Icon('at-symbol')]
#[Group('Контент', 'contacts')]
#[Order(2)]
class ContactInfoResource extends ModelResource
{
    protected string $model = ContactInfo::class;

    protected string $column = 'name';

    public function getTitle(): string
    {
        return 'Контактная информация';
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
            Text::make('Название', 'name')->required(),
            Text::make('Телефон', 'phone'),
            Text::make('Email', 'email')->required(),
            Text::make('Telegram', 'telegram'),
            Text::make('Github', 'github'),
            Json::make('Соцсети', 'socials')
                ->hint('Например: [{"type": "vk", "url": "https://vk.com/username"}]'),
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
