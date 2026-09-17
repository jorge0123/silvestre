<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\PlanCode;
use App\Models\Business;
use App\Models\Category;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\CategorySeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\ZoneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PlanSeeder::class, CategorySeeder::class, ZoneSeeder::class]);
    }

    private function seller(): User
    {
        return User::factory()->create(['account_type' => AccountType::Business]);
    }

    private function zone(string $slug = 'zona-10'): int
    {
        return Zone::where('slug', $slug)->value('id');
    }

    /** Recorre los pasos hasta dejar un negocio listo para aceptar las reglas. */
    private function walkUntilRules(User $user, string $offering = 'productos'): Business
    {
        $category = $offering === 'servicios' ? 'servicios-del-hogar' : 'reposteria-y-panaderia';

        $this->actingAs($user)->post(route('onboarding.update', 'negocio'), [
            'name' => 'Galletas de la Abuela Chela', 'slug' => 'galletasdechela', 'kind' => 'negocio',
        ])->assertSessionHasNoErrors();

        $this->post(route('onboarding.update', 'oferta'), [
            'offering' => $offering, 'category_id' => Category::where('slug', $category)->value('id'),
        ])->assertSessionHasNoErrors();

        $this->post(route('onboarding.update', 'ubicacion'), [
            'zone_id' => $this->zone(), 'whatsapp' => '+502 5555-1234',
            'hours' => [['weekday' => 1, 'opens_at' => '09:00', 'closes_at' => '18:00']],
        ])->assertSessionHasNoErrors();

        $delivery = $offering === 'servicios'
            ? ['service_modes' => ['remote']]
            : ['fulfillment' => ['local_delivery'], 'delivery_zones' => [['zone_id' => $this->zone(), 'fee' => 25, 'min_order' => 50]]];

        $this->post(route('onboarding.update', 'entrega'), $delivery)->assertSessionHasNoErrors();

        $this->post(route('onboarding.update', 'cobros'), [
            'payment_methods' => [['method' => 'cash_on_delivery']],
        ])->assertSessionHasNoErrors();

        $this->post(route('onboarding.update', 'presentacion'), [
            'intro' => 'Galletas horneadas en casa cada jueves.',
        ])->assertSessionHasNoErrors();

        return $user->ownedBusinesses()->firstOrFail();
    }

    // ------------------------------------------------------------ redirección

    public function test_someone_who_came_to_sell_is_sent_to_onboarding()
    {
        $this->actingAs($this->seller())
            ->get(route('dashboard'))
            ->assertRedirect(route('onboarding.show'));
    }

    public function test_a_personal_account_is_never_forced_into_onboarding()
    {
        $user = User::factory()->create(['account_type' => AccountType::Personal]);

        $this->actingAs($user)->get(route('feed'))->assertOk();
    }

    public function test_a_personal_account_with_a_half_done_business_is_not_trapped()
    {
        $user = User::factory()->create(['account_type' => AccountType::Personal]);

        $this->actingAs($user)->post(route('onboarding.update', 'negocio'), [
            'name' => 'Mi tienda', 'slug' => 'mitienda', 'kind' => 'negocio',
        ]);

        $this->get(route('feed'))->assertOk();
    }

    // ------------------------------------------------------------ flujo feliz

    public function test_full_onboarding_opens_the_business_and_switches_to_business_mode()
    {
        $user = $this->seller();
        $business = $this->walkUntilRules($user);

        $this->post(route('onboarding.update', 'reglas'), ['accept_policy' => '1'])
            ->assertRedirect(route('dashboard'));

        $business->refresh();
        $user->refresh();

        $this->assertNotNull($business->onboarding_completed_at);
        $this->assertNotNull($business->policy_accepted_at);
        $this->assertSame($business->id, $user->active_business_id);
        $this->assertSame('55551234', $business->whatsapp, 'El +502 y los guiones se limpian.');
        $this->assertSame(2500, $business->deliveryZones()->first()->fee_cents, 'Q25 se guarda en centavos.');
        $this->assertSame(PlanCode::Free->value, $business->subscription->plan->code, 'Arranca en Gratis; la prueba viene al 100 %.');

        $this->get(route('dashboard'))->assertOk();
    }

    public function test_a_step_cannot_be_skipped()
    {
        $this->actingAs($this->seller())
            ->post(route('onboarding.update', 'cobros'), ['payment_methods' => [['method' => 'cash_on_delivery']]])
            ->assertRedirect(route('onboarding.show'));

        $this->assertDatabaseCount('businesses', 0);
    }

    // ----------------------------------------------------------------- reglas

    public function test_prohibited_business_names_are_blocked()
    {
        $this->actingAs($this->seller())
            ->post(route('onboarding.update', 'negocio'), [
                'name' => 'Venta de c0ca1na', 'slug' => 'ventas', 'kind' => 'negocio',
            ])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('businesses', 0);
    }

    public function test_an_empresa_needs_nit_and_legal_name()
    {
        $this->actingAs($this->seller())
            ->post(route('onboarding.update', 'negocio'), [
                'name' => 'Abarrotes del Valle', 'slug' => 'abarrotesvalle', 'kind' => 'empresa',
            ])
            ->assertSessionHasErrors(['legal_name', 'tax_id']);
    }

    public function test_category_must_match_what_they_offer()
    {
        $user = $this->seller();
        $this->actingAs($user)->post(route('onboarding.update', 'negocio'), [
            'name' => 'Galletas', 'slug' => 'galletas', 'kind' => 'negocio',
        ]);

        $this->post(route('onboarding.update', 'oferta'), [
            'offering' => 'productos',
            'category_id' => Category::where('slug', 'reparaciones-y-oficios')->value('id'),
        ])->assertSessionHasErrors('category_id');
    }

    public function test_pickup_requires_an_address_and_delivery_requires_zones()
    {
        $user = $this->seller();
        $this->walkUntilRules($user);

        $this->post(route('onboarding.update', 'entrega'), ['fulfillment' => ['pickup']])
            ->assertSessionHasErrors('address');

        $this->post(route('onboarding.update', 'entrega'), ['fulfillment' => ['local_delivery']])
            ->assertSessionHasErrors('delivery_zones');
    }

    public function test_bank_transfer_requires_the_account_details()
    {
        $user = $this->seller();
        $this->walkUntilRules($user);

        $this->post(route('onboarding.update', 'cobros'), [
            'payment_methods' => [['method' => 'bank_transfer', 'details' => ['bank' => 'Banrural']]],
        ])->assertSessionHasErrors([
            'payment_methods.0.details.account_type',
            'payment_methods.0.details.account_number',
            'payment_methods.0.details.holder',
        ]);
    }

    public function test_services_only_business_does_not_ask_for_product_delivery()
    {
        $user = $this->seller();
        $business = $this->walkUntilRules($user, 'servicios');

        $this->assertSame(0, $business->fulfillment()->count());
        $this->assertSame(1, $business->serviceModes()->count());
    }

    public function test_rules_must_be_accepted()
    {
        $user = $this->seller();
        $this->walkUntilRules($user);

        $this->post(route('onboarding.update', 'reglas'), [])
            ->assertSessionHasErrors('accept_policy');
    }
}
