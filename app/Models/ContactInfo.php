<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Interfaces\BaseModelInterface;
use Illuminate\Database\Eloquent\Attributes\Connection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
    'phone',
    'email',
    'telegram',
    'github',
    'socials',
])]
#[Table(name: 'contact_info')]
#[Connection('pgsql')]
class ContactInfo extends Model implements BaseModelInterface
{
    public function getClassName(): string
    {
        return self::class;
    }

    public function getData(): ?BaseModelInterface
    {
        return self::query()->first();
    }

    protected function casts(): array
    {
        return [
            'socials' => 'array',
        ];
    }
}
