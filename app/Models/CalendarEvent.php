<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'start_date',
        'end_date',
        'color',
        'all_day',
        'user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function upcoming($query, $days = 7)
    {
        return $query->where('start_date', '>=', now())
            ->where('start_date', '<=', now()->addDays($days))
            ->orderBy('start_date');
    }

    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function today($query)
    {
        return $query->whereDate('start_date', today());
    }

    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'all_day' => 'boolean',
        ];
    }
}
