<?php

declare(strict_types=1);

namespace Whodoku\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Orchid\Screen\AsSource;

#[Fillable(['name', 'kinds', 'active'])]
class RoomTheme extends Model
{
    use AsSource;

    protected function casts(): array
    {
        return ['kinds' => 'array', 'active' => 'boolean'];
    }
}
