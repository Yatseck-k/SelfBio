<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Interfaces\BaseModelInterface;
use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Connection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Orchid\Screen\AsSource;

#[Fillable([
    'name',
    'email',
    'password',
])]
#[Connection('pgsql')]
#[Hidden([
    'password',
    'remember_token',
])]
class User extends Authenticatable implements BaseModelInterface
{
    /** @use HasFactory<UserFactory> */
    use AsSource, HasFactory, Notifiable;

    #[\Override]
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function getClassName(): string
    {
        return self::class;
    }

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
}
