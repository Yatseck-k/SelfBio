<?php

declare(strict_types=1);

namespace Whodoku\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Platform\Models\Role;
use Orchid\Screen\Fields\Select;

class RoleFilter extends Filter
{
    /**
     * The displayable name of the filter.
     */
    public function name(): string
    {
        return __('Roles');
    }

    /**
     * The array of matched parameters.
     */
    public function parameters(): array
    {
        return ['role'];
    }

    /**
     * Apply to a given Eloquent query builder.
     */
    public function run(Builder $builder): Builder
    {
        return $builder->whereHas('roles', function (Builder $query): void {
            $query->where('slug', $this->request->input('role'));
        });
    }

    /**
     * Get the display fields.
     */
    public function display(): array
    {
        return [
            Select::make('role')
                ->fromModel(Role::class, 'name', 'slug')
                ->empty()
                ->value($this->request->input('role'))
                ->title(__('Roles')),
        ];
    }

    /**
     * Value to be displayed
     */
    public function value(): string
    {
        return $this->name().': '.Role::query()->where('slug', $this->request->input('role'))->first()->name;
    }
}
