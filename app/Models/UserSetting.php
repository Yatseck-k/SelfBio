<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'key',
        'value',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function get(int $userId, string $key, $default = null)
    {
        $setting = static::where('user_id', $userId)
            ->where('key', $key)
            ->first();

        return $setting ? $setting->value : $default;
    }

    public static function set(int $userId, string $key, $value): void
    {
        static::updateOrCreate(
            ['user_id' => $userId, 'key' => $key],
            ['value' => $value]
        );
    }

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }
}
