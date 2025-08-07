<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PomodoroSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'duration',
        'completed',
        'type',
        'started_at',
        'completed_at',
        'user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function completed($query)
    {
        return $query->where('completed', true);
    }

    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function today($query)
    {
        return $query->whereDate('started_at', today());
    }

    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function thisWeek($query)
    {
        return $query->whereBetween('started_at', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ]);
    }

    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function workSessions($query)
    {
        return $query->where('type', 'work');
    }

    protected function totalDurationToday(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(get: fn () => static::where('user_id', $this->user_id)
            ->today()
            ->completed()
            ->sum('duration'));
    }

    protected function casts(): array
    {
        return [
            'completed' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
