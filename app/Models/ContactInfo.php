<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Interfaces\BaseModelInterface;
use Illuminate\Database\Eloquent\Model;

class ContactInfo extends Model implements BaseModelInterface
{
    protected $table = 'contact_info';

    public function getClassName(): string
    {
        return self::class;
    }

    public function getData(): ?BaseModelInterface
    {
        return self::query()->first();
    }

    protected $fillable = [
        'name',
        'phone',
        'email',
        'telegram',
        'github',
        'socials',
    ];

    protected function casts(): array
    {
        return [
            'socials' => 'array',
        ];
    }
}
