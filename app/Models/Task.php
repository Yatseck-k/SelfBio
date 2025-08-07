<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'priority',
        'completed',
        'due_date',
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
    protected function pending($query)
    {
        return $query->where('completed', false);
    }

    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function overdue($query)
    {
        return $query->where('completed', false)
            ->whereNotNull('due_date')
            ->where('due_date', '<', now());
    }

    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function dueToday($query)
    {
        return $query->where('completed', false)
            ->whereDate('due_date', today());
    }

    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function highPriority($query)
    {
        return $query->where('priority', 'high');
    }

    protected function casts(): array
    {
        return [
            'completed' => 'boolean',
            'due_date' => 'datetime',
        ];
    }
}
