<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::sole();
        $this->assertSame('Test User', $user->nombre);
        $this->assertSame('test@example.com', $user->email);
        $this->assertTrue(Hash::check('password', $user->password));
        $this->assertFalse($user->es_admin);
    }

    public function test_registration_does_not_accept_employee_privileges_from_the_request(): void
    {
        $this->post('/register', [
            'name' => 'QA Employee',
            'email' => 'qa-register@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
            'nombre' => 'Injected name',
            'es_admin' => true,
            'rol' => 'oficina',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        $user = User::sole();
        $this->assertSame('QA Employee', $user->nombre);
        $this->assertFalse($user->es_admin);
        $this->assertSame('repartidor', $user->rol);
    }

    #[DataProvider('invalidRegistrationData')]
    public function test_invalid_registration_does_not_create_or_authenticate_a_user(array $changes, string $error): void
    {
        $this->post('/register', array_replace([
            'name' => 'QA Employee',
            'email' => 'qa-invalid@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ], $changes))->assertSessionHasErrors($error);

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public static function invalidRegistrationData(): array
    {
        return [
            'missing name' => [['name' => ''], 'name'],
            'invalid email' => [['email' => 'invalid'], 'email'],
            'password mismatch' => [['password_confirmation' => 'different'], 'password'],
            'short password' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
        ];
    }

    public function test_an_existing_email_cannot_be_registered_again(): void
    {
        $existing = User::factory()->create(['email' => 'qa-existing@example.test']);

        $this->post('/register', [
            'name' => 'Duplicate Employee',
            'email' => $existing->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
        $this->assertSame($existing->nombre, $existing->fresh()->nombre);
    }
}
