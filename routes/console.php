<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('user:create {email} {name}', function (): void {
    $email = $this->argument('email');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('Enter a valid email address.');

        return;
    }
    if (User::query()->where('email', $email)->exists()) {
        $this->error('A user with this email already exists.');

        return;
    }

    $password = $this->secret('Password (at least 12 characters)');
    if (!is_string($password) || mb_strlen($password) < 12) {
        $this->error('Password must contain at least 12 characters.');

        return;
    }
    if ($password !== $this->secret('Repeat password')) {
        $this->error('Passwords do not match.');

        return;
    }

    User::query()->create([
        'name' => $this->argument('name'),
        'email' => $email,
        'password' => Hash::make($password),
    ]);
    $this->info('User created.');
})->purpose('Create a SelfBio account for the private repair catalog');
