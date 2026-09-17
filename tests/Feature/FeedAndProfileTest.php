<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Enums\PlanCode;
use App\Enums\SubscriptionState;
use App\Models\Business;
use App\Models\Plan;
use App\Models\Post;
use App\Models\User;
use App\Services\FeedBuilder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FeedAndProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
    }

    private function business(string $slug, bool $pro = false, bool $finished = true, ?User $owner = null): Business
    {
        $owner ??= User::factory()->create();

        $business = $owner->ownedBusinesses()->create([
            'name' => ucfirst($slug), 'slug' => $slug, 'kind' => 'negocio', 'offering' => 'productos',
            'intro' => 'Negocio de prueba.',
        ]);
        $business->forceFill([
            'onboarding_completed_at' => $finished ? now() : null,
            'published_at' => $finished ? now() : null,
            'activation_score' => $finished ? 100 : 20,
        ])->save();
        $business->members()->attach($owner->id, ['role' => BusinessRole::Owner->value, 'accepted_at' => now()]);

        if ($pro) {
            $business->subscription()->create([
                'plan_id' => Plan::byCode(PlanCode::Pro)->id,
                'state' => SubscriptionState::Active,
            ]);
        }

        return $business;
    }

    private function publish(Business $business, string $title, ?\DateTimeInterface $at = null): Post
    {
        $at ??= now()->subHour();

        return $business->posts()->create([
            'title' => $title, 'body' => 'Texto.', 'week_key' => Post::weekKeyFor($at), 'published_at' => $at,
        ]);
    }

    private function feedTitles(User $user): array
    {
        return collect(app(FeedBuilder::class)->for($user)['items'])
            ->where('kind', 'post')
            ->map(fn ($item) => [$item['post']['title'], $item['variant'] === 'sponsored'])
            ->values()
            ->all();
    }

    // ------------------------------------------------------------------ perfil

    public function test_anyone_can_see_a_business_profile_without_an_account()
    {
        $business = $this->business('galletas');
        $post = $this->publish($business, 'Horneada del jueves');

        $this->get(route('business.show', $business))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/BusinessProfile')
                ->where('business.slug', 'galletas')
                ->where('viewer.isGuest', true)
                ->has('posts', 1));

        $this->get(route('posts.show', ['business' => $business, 'post' => $post]))->assertOk();
    }

    public function test_a_services_business_profile_renders_its_services()
    {
        $business = $this->business('barberia');
        $business->update(['offering' => 'servicios']);
        $business->serviceModes()->create(['mode' => 'at_business']);
        $business->services()->create([
            'name' => 'Corte clásico', 'slug' => 'corte', 'price_cents' => 5000,
            'duration_min' => 30, 'modes' => ['at_business'],
        ]);

        $this->get(route('business.show', $business))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('services', 1)
                ->where('services.0.modes', ['En mi local']));
    }

    public function test_an_unfinished_business_is_hidden_from_everyone_but_its_owner()
    {
        $owner = User::factory()->create();
        $business = $this->business('amedias', finished: false, owner: $owner);

        $this->get(route('business.show', $business))->assertNotFound();
        $this->actingAs($owner)->get(route('business.show', $business))->assertOk();
    }

    public function test_a_post_cannot_be_opened_under_another_business()
    {
        $a = $this->business('uno');
        $b = $this->business('dos');
        $post = $this->publish($a, 'De uno');

        $this->get(route('posts.show', ['business' => $b, 'post' => $post]))->assertNotFound();
    }

    public function test_the_average_is_hidden_with_fewer_than_five_reviews()
    {
        $business = $this->business('nuevo');
        $business->forceFill(['rating_count' => 3, 'rating_sum' => 15])->save();

        $this->assertNull($business->publicRating());

        $business->forceFill(['rating_count' => 6, 'rating_sum' => 27])->save();
        $this->assertSame(4.5, $business->publicRating());
    }

    // ----------------------------------------------------------------- seguir

    public function test_following_toggles_and_keeps_the_count_honest()
    {
        $business = $this->business('cafe');
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('business.follow', $business));
        $this->assertTrue($user->isFollowing($business));
        $this->assertSame(1, $business->refresh()->followers_count);

        $this->post(route('business.follow', $business));
        $this->assertFalse($user->isFollowing($business));
        $this->assertSame(0, $business->refresh()->followers_count);
    }

    public function test_you_cannot_follow_your_own_business()
    {
        $owner = User::factory()->create();
        $business = $this->business('propio', owner: $owner);

        $this->actingAs($owner)->post(route('business.follow', $business))->assertStatus(422);
    }

    // ------------------------------------------------------------------ inicio

    public function test_feed_shows_posts_from_followed_businesses()
    {
        $followed = $this->business('seguido');
        $other = $this->business('otro');
        $this->publish($followed, 'Del que sigo');
        $this->publish($other, 'Del que no sigo');

        $user = User::factory()->create();
        $user->follows()->attach($followed->id);

        $variants = collect(app(FeedBuilder::class)->for($user)['items'])
            ->where('kind', 'post')
            ->mapWithKeys(fn ($item) => [$item['post']['title'] => $item['variant']]);

        // Primero lo que sigues; después, marcado como sugerencia, lo demás.
        $this->assertSame(['Del que sigo' => 'following', 'Del que no sigo' => 'discovery'], $variants->all());
    }

    public function test_a_business_gets_at_most_two_posts_per_day_in_the_feed()
    {
        // A las 8 de la noche: las cinco publicaciones de la mañana ya salieron.
        $this->travelTo(today()->setTime(20, 0));

        $business = $this->business('hiperactivo');
        foreach (range(1, 5) as $n) {
            $this->publish($business, "Publicación {$n}", now()->startOfDay()->addHours(8 + $n));
        }

        $user = User::factory()->create();
        $user->follows()->attach($business->id);

        $this->assertCount(FeedBuilder::POSTS_PER_BUSINESS_PER_DAY, $this->feedTitles($user));
    }

    public function test_feed_scrolls_in_pages_with_suggestions_between_posts()
    {
        $followed = $this->business('seguido');
        foreach (range(1, 20) as $n) {
            $this->publish($followed, "Post {$n}", now()->subDays($n));
        }
        foreach (range(1, 6) as $n) {
            $this->business("sugerido{$n}");
        }

        $user = User::factory()->create();
        $user->follows()->attach($followed->id);

        $first = app(FeedBuilder::class)->for($user);
        $kinds = collect($first['items'])->pluck('kind');

        $this->assertTrue($first['hasMore']);
        $this->assertCount(FeedBuilder::PAGE_SIZE, $kinds->filter(fn ($k) => $k === 'post'));
        // La primera fila de sugeridos aparece justo después de la 2.ª publicación.
        $this->assertSame('suggested', $kinds[FeedBuilder::SUGGESTIONS_FIRST_AT]);

        $second = $this->actingAs($user)
            ->getJson(route('feed.more', ['page' => 2]))
            ->assertOk()
            ->assertJsonPath('page', 2)
            ->assertJsonPath('items.0.post.title', 'Post 9')
            ->json('items');

        $secondKinds = collect($second)->pluck('kind');
        $this->assertCount(FeedBuilder::PAGE_SIZE, $secondKinds->filter(fn ($k) => $k === 'post'));
        $this->assertContains('suggested', $secondKinds, 'Las sugerencias siguen apareciendo al seguir bajando.');
    }

    public function test_pro_businesses_appear_as_sponsored_and_labeled()
    {
        $followed = $this->business('seguido');
        foreach (range(1, 3) as $n) {
            $this->publish($followed, "Normal {$n}", now()->subDays($n));
        }

        $pro = $this->business('pagando', pro: true);
        $this->publish($pro, 'Anuncio Pro');

        $free = $this->business('gratis');
        $this->publish($free, 'No debe anunciarse');

        $user = User::factory()->create();
        $user->follows()->attach($followed->id);

        $items = $this->feedTitles($user);

        $this->assertContains(['Anuncio Pro', true], $items, 'El negocio Pro sale marcado como promocionado.');
        // Un negocio Gratis puede salir como sugerencia, pero nunca como anuncio.
        $this->assertNotContains(['No debe anunciarse', true], $items);
    }

    public function test_you_never_see_your_own_business_as_an_ad()
    {
        $owner = User::factory()->create();
        $mine = $this->business('mio', pro: true, owner: $owner);
        $this->publish($mine, 'Mi anuncio');

        $followed = $this->business('seguido');
        $this->publish($followed, 'Algo');
        $owner->follows()->attach($followed->id);

        $this->assertNotContains('Mi anuncio', collect($this->feedTitles($owner))->pluck(0));
    }

    public function test_pro_pricing_is_four_a_month_or_eight_for_three()
    {
        $this->assertSame('$4 al mes o $8 por 3 meses', Plan::proPricingLabel());
    }
}
