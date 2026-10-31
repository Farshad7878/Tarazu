<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBmsData;
use Tests\TestCase;

class LoginThrottleTest extends TestCase
{
    use BuildsBmsData, RefreshDatabase;

    public function test_sixth_failed_attempt_is_locked_with_retry_after(): void
    {
        $email = 'lock.'.uniqid().'@test.local';

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => $email, 'password' => 'wrong'])->assertStatus(422);
        }

        $response = $this->postJson('/api/login', ['email' => $email, 'password' => 'wrong']);

        $response->assertStatus(429);
        $this->assertNotEmpty($response->headers->get('Retry-After'));
    }

    public function test_lockout_does_not_affect_another_email(): void
    {
        $user = $this->makeUser('admin', ['password' => bcrypt('secret123')]);

        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/login', ['email' => 'other@test.local', 'password' => 'wrong']);
        }

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'role']]);
    }

    public function test_logout_revokes_the_token(): void
    {
        $user = $this->makeUser('reception');
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/logout')->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_tokens_expire_after_twelve_hours_by_default(): void
    {
        $this->assertSame(720, (int) config('sanctum.expiration'));
    }
}
