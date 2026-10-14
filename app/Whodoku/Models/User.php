<?php

declare(strict_types=1);

namespace Whodoku\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Orchid\Filters\Types\Like;
use Orchid\Filters\Types\Where;
use Orchid\Filters\Types\WhereDateStartEnd;
use Orchid\Platform\Models\User as Authenticatable;
use Whodoku\Orchid\Presenters\UserPresenter;

#[Fillable([
    'name',
    'email',
    'password',
])]
#[Hidden([
    'password',
    'remember_token',
    'permissions',
])]
class User extends Authenticatable
{
    public function presenter(): UserPresenter
    {
        return new UserPresenter($this);
    }

    /**
     * The attributes for which you can use filters in url.
     *
     * @var array
     */
    #[\Override]
    protected $allowedFilters = [
        'id' => Where::class,
        'name' => Like::class,
        'email' => Like::class,
        'updated_at' => WhereDateStartEnd::class,
        'created_at' => WhereDateStartEnd::class,
    ];

    /**
     * The attributes for which can use sort in url.
     *
     * @var array
     */
    #[\Override]
    protected $allowedSorts = [
        'id',
        'name',
        'email',
        'updated_at',
        'created_at',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'email_verified_at' => 'datetime',
        ];
    }
}
