<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogPost extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'preview',
        'body',
        'image',
        'published_at',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'created_at' => 'datetime', 'updated_at' => 'datetime'];
    }
}
