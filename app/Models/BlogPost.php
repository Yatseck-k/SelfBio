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

    protected array $dates = [
        'published_at',
        'created_at',
        'updated_at',
    ];
}
