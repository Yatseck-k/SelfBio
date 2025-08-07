<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuickLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'url',
        'icon',
        'category',
        'order',
        'color',
        'user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function ordered($query)
    {
        return $query->orderBy('order');
    }

    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function byCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    protected function formattedUrl(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(get: function () {
            if (! str_starts_with($this->url, 'http')) {
                return 'https://'.$this->url;
            }

            return $this->url;
        });
    }
}
