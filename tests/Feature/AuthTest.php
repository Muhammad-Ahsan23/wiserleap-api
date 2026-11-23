<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private array $spa = ['Origin' => 'http://localhost:3000', 'Referer' => 'http://localhost:3000/', 'Accept' => 'application/json'];

    public function test_first_account_can_sign_up_then_sign_up_closes(): void
    {
        $this->getJson('/api/v1/auth/registration-status')->assertExactJson(['open' => true]);

        $this->withHeaders($this->spa)->postJson('/api/v1/auth/register', [
            'name' => '  Muhammad   Ahsan ',
            'email' => 'Admin@Example.com',
            'password' => 'Wiser2026',
            'password_confirmation' => 'Wiser2026',
        ])->assertCreated()->assertJsonPath('data.email', 'admin@example.com')->assertJsonPath('data.name', 'Muhammad Ahsan')
            ->assertJsonMissingPath('data.password');

        $this->assertAuthenticated();
        $this->getJson('/api/v1/auth/registration-status')->assertExactJson(['open' => false]);

        auth()->guard('web')->logout();
        $this->withHeaders($this->spa)->postJson('/api/v1/auth/register', [
            'name' => 'Intruder', 'email' => 'x@example.com', 'password' => 'Wiser2026', 'password_confirmation' => 'Wiser2026',
        ])->assertForbidden();
    }

    public function test_sign_up_validates_password_rules(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'A', 'email' => 'not-an-email', 'password' => 'short', 'password_confirmation' => 'other',
        ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_login_logout_and_current_user(): void
    {
        User::factory()->create(['email' => 'admin@example.com', 'password' => 'Wiser2026']);

        $this->withHeaders($this->spa)->postJson('/api/v1/auth/login', ['email' => 'ADMIN@example.com', 'password' => 'Wiser2026', 'remember' => true])
            ->assertOk()->assertJsonPath('data.email', 'admin@example.com');

        $this->withHeaders($this->spa)->getJson('/api/v1/auth/user')->assertOk()->assertJsonPath('data.email', 'admin@example.com');

        $this->withHeaders($this->spa)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertGuest('web');
    }

    public function test_wrong_password_gives_generic_error_and_is_rate_limited(): void
    {
        User::factory()->create(['email' => 'admin@example.com']);

        $this->postJson('/api/v1/auth/login', ['email' => 'admin@example.com', 'password' => 'wrong-pass1'])
            ->assertUnprocessable()->assertJsonPath('errors.email.0', 'Incorrect email or password.');
        $this->postJson('/api/v1/auth/login', ['email' => 'nobody@example.com', 'password' => 'wrong-pass1'])
            ->assertJsonPath('errors.email.0', 'Incorrect email or password.');

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'admin@example.com', 'password' => 'wrong-pass1']);
        }
        $this->postJson('/api/v1/auth/login', ['email' => 'admin@example.com', 'password' => 'wrong-pass1'])->assertTooManyRequests();
    }

    public function test_guests_cannot_reach_admin_endpoints(): void
    {
        $this->getJson('/api/v1/auth/user')->assertUnauthorized();
        $this->getJson('/api/v1/admin/dashboard')->assertUnauthorized();
        $this->postJson('/api/v1/admin/categories', ['name' => 'Hack'])->assertUnauthorized();
    }

    public function test_password_reset_flow_and_no_enumeration(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'admin@example.com']);

        $message = 'If an account exists for this email, a reset link has been sent.';
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'admin@example.com'])->assertOk()->assertJsonPath('message', $message);
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])->assertOk()->assertJsonPath('message', $message);

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use ($user, &$token) {
            $token = $n->token;
            $url = $n->toMail($user)->actionUrl;

            return str_starts_with($url, 'http://localhost:3000/reset-password?token=');
        });

        $this->postJson('/api/v1/auth/reset-password', ['token' => 'bad', 'email' => 'admin@example.com', 'password' => 'NewPass2026', 'password_confirmation' => 'NewPass2026'])
            ->assertUnprocessable();
        $this->postJson('/api/v1/auth/reset-password', ['token' => $token, 'email' => 'admin@example.com', 'password' => 'NewPass2026', 'password_confirmation' => 'NewPass2026'])
            ->assertOk();

        $this->withHeaders($this->spa)->postJson('/api/v1/auth/login', ['email' => 'admin@example.com', 'password' => 'NewPass2026'])->assertOk();
    }

    public function test_security_headers_and_cors(): void
    {
        $this->getJson('/api/v1/blogs')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');

        $this->withHeaders(['Origin' => 'http://localhost:3000'])->getJson('/api/v1/blogs')
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');

        // Other sites never get their own origin back, so browsers block them.
        $evil = $this->withHeaders(['Origin' => 'https://evil.example'])->getJson('/api/v1/blogs');
        $this->assertNotSame('https://evil.example', $evil->headers->get('Access-Control-Allow-Origin'));
    }
}
