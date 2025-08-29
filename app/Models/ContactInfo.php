<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactInfo extends Model
{
    protected $table = 'contact_info';

    protected $fillable = [
        'name',
        'phone',
        'email',
        'telegram',
        'github',
        'socials',
    ];

    public function getContactInfo(): ?ContactInfo
    {
        return self::first();
    }

    protected function casts(): array
    {
        return [
            'socials' => 'array',
        ];
    }
}
