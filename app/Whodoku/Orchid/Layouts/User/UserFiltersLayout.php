<?php

declare(strict_types=1);

namespace Whodoku\Orchid\Layouts\User;

use Orchid\Filters\Filter;
use Orchid\Screen\Layouts\Selection;
use Whodoku\Orchid\Filters\RoleFilter;

class UserFiltersLayout extends Selection
{
    /**
     * @return string[]|Filter[]
     */
    public function filters(): array
    {
        return [
            RoleFilter::class,
        ];
    }
}
