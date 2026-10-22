<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_sign_in_and_out(): void
    {
        $user = User::factory()->create(['password' => 'correct-horse-battery-staple']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'correct-horse-battery-staple',
        ])->assertRedirect('/account');

        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_invalid_password_does_not_authenticate(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
