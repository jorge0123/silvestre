<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\BusinessRole;
use App\Enums\PlanCode;
use App\Enums\SubscriptionState;
use App\Models\Business;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModeSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
    }

    private function businessFor(User $user, bool $finished = true): Business
    {
        $business = $user->ownedBusinesses()->create([
            'name' => 'Tienda '.$user->id, 'slug' => 'tienda'.$user->id, 'kind' => 'negocio', 'offering' => 'productos',
        ]);
        $business->forceFill(['onboarding_completed_at' => $finished ? now() : null])->save();
        $business->members()->attach($user->id, ['role' => BusinessRole::Owner->value, 'accepted_at' => now()]);

        return $business;
    }

    public function test_a_user_can_switch_between_personal_and_business_mode()
    {
        $user = User::factory()->create();
        $business = $this->businessFor($user);

        $this->actingAs($user)->post(route('mode.business', $business))->assertRedirect(route('dashboard'));
        $this->assertTrue($user->refresh()->isInBusinessMode());

        $this->post(route('mode.personal'))->assertRedirect(route('feed'));
        $this->assertFalse($user->refresh()->isInBusinessMode());
    }

    public function test_nobody_can_switch_into_someone_elses_business()
    {
        $owner = User::factory()->create();
        $business = $this->businessFor($owner);

        $this->actingAs(User::factory()->create())
            ->post(route('mode.business', $business))
            ->assertForbidden();
    }

    public function test_an_unfinished_business_sends_you_back_to_onboarding()
    {
        $user = User::factory()->create();
        $business = $this->businessFor($user, finished: false);

        $this->actingAs($user)
            ->post(route('mode.business', $business))
            ->assertRedirect(route('onboarding.show'));

        $this->assertFalse($user->refresh()->isInBusinessMode());
    }

    public function test_leaving_onboarding_to_just_shop_stops_the_redirect()
    {
        $user = User::factory()->create(['account_type' => AccountType::Business]);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('onboarding.show'));

        $this->post(route('mode.personal'));

        $this->assertSame(AccountType::Personal, $user->refresh()->account_type);
        $this->get(route('feed'))->assertOk();
    }

    public function test_dismissed_guides_are_remembered()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('guides.dismiss', 'dashboard.personal'));

        $this->assertTrue($user->refresh()->hasDismissedGuide('dashboard.personal'));
    }

    public function test_trial_starts_only_once()
    {
        $user = User::factory()->create();
        $business = $this->businessFor($user);

        $business->startTrial();
        $business->startTrial();

        $trials = $business->subscription()->getQuery()->where('state', SubscriptionState::Trial)->count();
        $this->assertSame(1, $trials);
        $this->assertSame(PlanCode::Pro->value, $business->refresh()->subscription->plan->code);
        $this->assertSame(90, $business->subscription->trialDaysLeft());
    }
}
