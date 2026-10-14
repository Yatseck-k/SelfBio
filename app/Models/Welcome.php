<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Interfaces\BaseModelInterface;
use Illuminate\Database\Eloquent\Attributes\Connection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'title',
    'body',
])]
#[Table(name: 'welcome')]
#[Connection('pgsql')]
class Welcome extends Model implements BaseModelInterface
{
    public function getClassName(): string
    {
        return self::class;
    }

    public function getData(): ?BaseModelInterface
    {
        return self::query()->first();
    }
}
