<?php

namespace Tests\Feature\Auth;

use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'account_type' => 'personal',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('feed', absolute: false));
    }

    public function test_registration_remembers_if_they_came_to_sell()
    {
        $this->post(route('register.store'), [
            'name' => 'Luis Coc',
            'email' => 'luis@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'account_type' => 'business',
        ]);

        $this->assertSame(AccountType::Business, User::firstWhere('email', 'luis@example.com')->account_type);
    }

    public function test_account_type_is_required()
    {
        $this->post(route('register.store'), [
            'name' => 'Sin tipo',
            'email' => 'sintipo@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('account_type');

        $this->assertGuest();
    }
}
