<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->nombre);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }

    public function test_profile_update_does_not_accept_employee_privileges_or_change_another_user(): void
    {
        $user = User::factory()->create(['es_admin' => false, 'rol' => 'auxiliar']);
        $other = User::factory()->create();
        $otherBefore = $other->refresh()->getRawOriginal();

        $this->actingAs($user)->patch('/profile', [
            'name' => 'Updated Employee',
            'email' => $user->email,
            'id' => $other->id,
            'nombre' => 'Injected name',
            'es_admin' => true,
            'rol' => 'oficina',
        ])->assertSessionHasNoErrors()->assertRedirect('/profile');

        $this->assertSame('Updated Employee', $user->fresh()->nombre);
        $this->assertFalse($user->fresh()->es_admin);
        $this->assertSame('auxiliar', $user->fresh()->rol);
        $this->assertSame($otherBefore, $other->fresh()->getRawOriginal());
    }

    public function test_profile_cannot_take_another_employees_email_and_does_not_partially_save(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $before = $user->refresh()->getRawOriginal();

        $this->actingAs($user)->patch('/profile', [
            'name' => 'Should not be saved',
            'email' => $other->email,
        ])->assertSessionHasErrors('email');

        $this->assertSame($before, $user->fresh()->getRawOriginal());
    }
}
