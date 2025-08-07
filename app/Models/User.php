<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * User's calendar events
     */
    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }

    /**
     * User's tasks
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * User's pomodoro sessions
     */
    public function pomodoroSessions(): HasMany
    {
        return $this->hasMany(PomodoroSession::class);
    }

    /**
     * User's quick links
     */
    public function quickLinks(): HasMany
    {
        return $this->hasMany(QuickLink::class);
    }

    /**
     * User's settings
     */
    public function settings(): HasMany
    {
        return $this->hasMany(UserSetting::class);
    }

    /**
     * Get a specific setting value
     */
    public function getSetting(string $key, $default = null)
    {
        return UserSetting::get($this->id, $key, $default);
    }

    /**
     * Set a specific setting value
     */
    public function setSetting(string $key, $value): void
    {
        UserSetting::set($this->id, $key, $value);
    }
}
