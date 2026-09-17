<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\ModerationAction;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use App\Support\Platform;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
    }

    private function admin(): User
    {
        return tap(User::factory()->create(), fn (User $u) => $u->forceFill(['is_admin' => true])->save());
    }

    private function business(string $slug = 'tienda'): Business
    {
        $owner = User::factory()->create();
        $business = $owner->ownedBusinesses()->create([
            'name' => 'Tienda '.$slug, 'slug' => $slug, 'kind' => 'negocio', 'offering' => 'productos',
        ]);
        $business->subscription()->create(['plan_id' => Plan::free()->id, 'state' => 'active']);

        return $business;
    }

    private function planPayload(Plan $plan, array $overrides = []): array
    {
        $plan->load('prices');

        return [
            'name' => $plan->name,
            'description' => $plan->description,
            'position' => $plan->position,
            'is_active' => $plan->is_active,
            ...$plan->terms(),
            'prices' => $plan->prices->map(fn ($p) => [
                'id' => $p->id, 'interval_months' => $p->interval_months, 'price' => $p->price_cents / 100, 'is_active' => $p->is_active,
            ])->all(),
            ...$overrides,
        ];
    }

    // ------------------------------------------------------------------ acceso

    public function test_only_admins_can_open_the_panel()
    {
        $this->get(route('admin.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('admin.index'))->assertForbidden();
        $this->actingAs($this->admin())->get(route('admin.index'))->assertOk();
    }

    public function test_every_admin_page_renders()
    {
        $this->business();
        $this->actingAs($this->admin());

        foreach (['admin.plans.index', 'admin.plans.create', 'admin.businesses.index', 'admin.users.index'] as $name) {
            $this->get(route($name))->assertOk();
        }
        $this->get(route('admin.plans.edit', Plan::free()))->assertOk();
    }

    // ------------------------------------------------------ todo libre y fundadores

    public function test_businesses_created_during_free_mode_are_founders_and_keep_no_limits()
    {
        $this->assertTrue(Platform::freeMode(), 'Arranca con todo libre.');
        $founder = $this->business('fundador');

        $this->actingAs($this->admin())->patch(route('admin.free-mode.update'), ['enabled' => false])->assertRedirect();
        $this->assertFalse(Platform::freeMode());

        $newcomer = $this->business('nuevo');

        $this->assertTrue($founder->refresh()->isFounder());
        $this->assertNull($founder->limit('post_quota_weekly'), 'El fundador sigue sin límites.');
        $this->assertFalse($newcomer->isFounder());
        $this->assertSame(Plan::free()->post_quota_weekly, $newcomer->limit('post_quota_weekly'));
        $this->assertTrue(ModerationAction::where('action', 'settings.free_mode')->exists());
    }

    public function test_admin_can_grant_and_revoke_founder()
    {
        Setting::set('free_mode', false);
        $business = $this->business();
        $this->actingAs($this->admin());

        $this->post(route('admin.businesses.founder', $business->id))->assertRedirect();
        $this->assertTrue($business->refresh()->isFounder());

        $this->post(route('admin.businesses.founder', $business->id))->assertRedirect();
        $this->assertFalse($business->refresh()->isFounder());
    }

    // ------------------------------------------------------------------- planes

    public function test_plan_changes_only_apply_to_new_members_unless_asked()
    {
        Setting::set('free_mode', false);
        $before = $this->business('antes');
        $free = Plan::free();

        $this->actingAs($this->admin())
            ->put(route('admin.plans.update', $free), $this->planPayload($free, ['post_quota_weekly' => 3]))
            ->assertSessionHasNoErrors();

        $after = $this->business('despues');

        $this->assertSame(7, Business::find($before->id)->limit('post_quota_weekly'), 'Conserva lo que tenía al entrar.');
        $this->assertSame(3, Business::find($after->id)->limit('post_quota_weekly'));

        // Mejorar el plan para todos: también llega a los actuales.
        $this->put(route('admin.plans.update', $free), $this->planPayload($free->refresh(), ['post_quota_weekly' => 20, 'apply_to_current' => true]))
            ->assertSessionHasNoErrors();

        $this->assertSame(20, Business::find($before->id)->limit('post_quota_weekly'));
        $this->assertSame(20, Business::find($after->id)->limit('post_quota_weekly'));
    }

    public function test_price_changes_keep_the_price_current_members_pay()
    {
        $pro = Plan::byCode('pro');
        $monthly = $pro->prices()->where('interval_months', 1)->first();
        $business = $this->business();
        $sub = $business->subscriptions()->create(['plan_id' => $pro->id, 'plan_price_id' => $monthly->id, 'state' => 'active']);

        $payload = $this->planPayload($pro);
        $payload['prices'][0]['price'] = 5;

        $this->actingAs($this->admin())->put(route('admin.plans.update', $pro), $payload)->assertSessionHasNoErrors();

        $this->assertSame(500, $monthly->refresh()->price_cents);
        $this->assertSame(400, $sub->refresh()->terms['price_cents'], 'Quien ya paga, sigue pagando lo mismo.');
    }

    public function test_admin_creates_a_plan_with_prices_and_can_delete_it_while_unused()
    {
        $this->actingAs($this->admin())->post(route('admin.plans.store'), [
            'code' => 'emprende', 'name' => 'Emprende', 'description' => 'Para quien empieza', 'position' => 5, 'is_active' => true,
            'post_quota_weekly' => 14, 'story_quota_daily' => null, 'photo_limit' => 15, 'product_limit' => 60, 'service_limit' => 60,
            'can_checkout' => true, 'can_reserve_stock' => true, 'can_use_team' => false, 'can_invoice' => false, 'can_promote' => false,
            'prices' => [['id' => null, 'interval_months' => 1, 'price' => 2, 'is_active' => true]],
        ])->assertRedirect(route('admin.plans.index'));

        $plan = Plan::byCode('emprende');
        $this->assertNull($plan->story_quota_daily);
        $this->assertSame(200, $plan->prices()->first()->price_cents);

        $this->delete(route('admin.plans.destroy', $plan))->assertRedirect(route('admin.plans.index'));
        $this->assertNull(Plan::where('code', 'emprende')->first());
    }

    public function test_base_plans_cannot_be_deleted_and_free_cannot_be_deactivated()
    {
        $this->actingAs($this->admin());

        $this->delete(route('admin.plans.destroy', Plan::byCode('pro')))->assertSessionHasErrors('plan');
        $this->put(route('admin.plans.update', Plan::free()), $this->planPayload(Plan::free(), ['is_active' => false]))
            ->assertSessionHasErrors('is_active');

        $this->assertNotNull(Plan::where('code', 'pro')->first());
    }

    public function test_admin_assigns_a_plan_and_it_promotes_the_business()
    {
        $business = $this->business();

        $this->actingAs($this->admin())->post(route('admin.businesses.plan', $business->id), [
            'plan_id' => Plan::byCode('pro')->id, 'months' => 3, 'note' => 'Transferencia BI',
        ])->assertRedirect();

        $business->refresh();
        $this->assertSame('pro', $business->subscription->plan->code);
        $this->assertTrue(Business::pro()->whereKey($business->id)->exists());
        $this->assertSame(1, $business->subscriptions()->where('state', 'active')->count(), 'La anterior se cierra.');
    }

    // ----------------------------------------------------------------- permisos

    public function test_admin_grants_and_revokes_access_but_not_their_own()
    {
        $admin = $this->admin();
        $person = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.users.admin', $person))->assertRedirect();
        $this->assertTrue($person->refresh()->is_admin);

        $this->post(route('admin.users.admin', $person))->assertRedirect();
        $this->assertFalse($person->refresh()->is_admin);

        $this->post(route('admin.users.admin', $admin))->assertSessionHasErrors('user');
        $this->assertTrue($admin->refresh()->is_admin);
    }

    public function test_first_admin_is_named_from_the_console()
    {
        $person = User::factory()->create(['email' => 'equipo@silvestre.gt']);

        $this->artisan('silvestre:admin', ['email' => 'equipo@silvestre.gt'])->assertSuccessful();
        $this->assertTrue($person->refresh()->is_admin);

        $this->artisan('silvestre:admin', ['email' => 'equipo@silvestre.gt', '--revoke' => true])->assertSuccessful();
        $this->assertFalse($person->refresh()->is_admin);

        $this->artisan('silvestre:admin', ['email' => 'nadie@silvestre.gt'])->assertFailed();
    }
}
