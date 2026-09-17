<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\Plan;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\Report;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InteractionsAndUploadsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
        Storage::fake('public');
    }

    private function ownerWithBusiness(): array
    {
        $owner = User::factory()->create();
        $business = $owner->ownedBusinesses()->create([
            'name' => 'Galletas', 'slug' => 'galletas'.$owner->id, 'kind' => 'negocio', 'offering' => 'productos',
            'intro' => 'Galletas caseras.',
        ]);
        $business->forceFill(['onboarding_completed_at' => now(), 'published_at' => now(), 'activation_score' => 100])->save();
        $business->members()->attach($owner->id, ['role' => BusinessRole::Owner->value, 'accepted_at' => now()]);
        $business->subscription()->create(['plan_id' => Plan::where('code', 'free')->value('id'), 'state' => 'active']);
        $owner->switchToBusiness($business);

        return [$owner->refresh(), $business];
    }

    private function postFor(Business $business): Post
    {
        return $business->posts()->create([
            'title' => 'Horneada', 'body' => 'Texto', 'week_key' => Post::weekKeyFor(now()), 'published_at' => now()->subMinute(),
        ]);
    }

    // -------------------------------------------------------------- reacciones

    public function test_reacting_toggles_and_counts()
    {
        [, $business] = $this->ownerWithBusiness();
        $post = $this->postFor($business);
        $fan = User::factory()->create();

        $this->actingAs($fan)->postJson(route('posts.react', $post))
            ->assertOk()->assertJson(['reacted' => true, 'reactions' => 1]);

        $this->postJson(route('posts.react', $post))
            ->assertOk()->assertJson(['reacted' => false, 'reactions' => 0]);
    }

    public function test_guests_cannot_react()
    {
        [, $business] = $this->ownerWithBusiness();

        $this->postJson(route('posts.react', $this->postFor($business)))->assertUnauthorized();
    }

    // ------------------------------------------------------------- comentarios

    public function test_anyone_can_read_comments_but_only_members_can_write()
    {
        [, $business] = $this->ownerWithBusiness();
        $post = $this->postFor($business);

        $this->getJson(route('posts.comments.index', $post))->assertOk()->assertJson(['canComment' => false]);
        $this->postJson(route('posts.comments.store', $post), ['body' => 'Hola'])->assertUnauthorized();
    }

    public function test_replies_are_one_level_deep()
    {
        [, $business] = $this->ownerWithBusiness();
        $post = $this->postFor($business);
        $ana = User::factory()->create();

        $parent = $this->actingAs($ana)->postJson(route('posts.comments.store', $post), ['body' => '¿Hay de avena?'])
            ->assertCreated()->json('comment.id');

        $reply = $this->postJson(route('posts.comments.store', $post), ['body' => 'Sí', 'parent_id' => $parent])->json('comment.id');

        // Responder a una respuesta cuelga del comentario principal.
        $this->postJson(route('posts.comments.store', $post), ['body' => 'Gracias', 'parent_id' => $reply])
            ->assertCreated()
            ->assertJsonPath('comment.parentId', $parent);

        $this->assertSame(3, $post->refresh()->comments_count);
    }

    public function test_the_business_comments_as_itself_on_its_own_posts()
    {
        [$owner, $business] = $this->ownerWithBusiness();
        $post = $this->postFor($business);

        $this->actingAs($owner)->postJson(route('posts.comments.store', $post), ['body' => 'Gracias por su pedido'])
            ->assertCreated()
            ->assertJsonPath('comment.author.name', $business->name)
            ->assertJsonPath('comment.author.isPostOwner', true);
    }

    public function test_prohibited_comments_are_rejected()
    {
        [, $business] = $this->ownerWithBusiness();

        $this->actingAs(User::factory()->create())
            ->postJson(route('posts.comments.store', $this->postFor($business)), ['body' => 'vendo c0ca1na'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');
    }

    public function test_only_the_author_can_edit_a_comment()
    {
        [, $business] = $this->ownerWithBusiness();
        $post = $this->postFor($business);
        $author = User::factory()->create();
        $comment = $post->comments()->create(['user_id' => $author->id, 'body' => 'Original']);

        $this->actingAs(User::factory()->create())
            ->patchJson(route('comments.update', $comment), ['body' => 'Hackeado'])
            ->assertForbidden();

        $this->actingAs($author)
            ->patchJson(route('comments.update', $comment), ['body' => 'Corregido'])
            ->assertOk()
            ->assertJsonPath('comment.body', 'Corregido')
            ->assertJsonPath('comment.edited', true);
    }

    public function test_author_or_post_owner_can_delete_and_replies_go_too()
    {
        [$owner, $business] = $this->ownerWithBusiness();
        $post = $this->postFor($business);
        $author = User::factory()->create();

        $parent = $post->comments()->create(['user_id' => $author->id, 'body' => 'Spam']);
        $post->comments()->create(['user_id' => $author->id, 'parent_id' => $parent->id, 'body' => 'Más spam']);
        $post->forceFill(['comments_count' => 2])->save();

        $this->actingAs(User::factory()->create())->deleteJson(route('comments.destroy', $parent))->assertForbidden();

        // El dueño del negocio modera su propia publicación.
        $this->actingAs($owner)->deleteJson(route('comments.destroy', $parent))
            ->assertOk()->assertJson(['count' => 0]);

        $this->assertSame(0, PostComment::where('post_id', $post->id)->count());
    }

    public function test_comments_can_be_reported_once_but_not_your_own()
    {
        [, $business] = $this->ownerWithBusiness();
        $post = $this->postFor($business);
        $author = User::factory()->create();
        $comment = $post->comments()->create(['user_id' => $author->id, 'body' => 'Ofensa']);
        $reporter = User::factory()->create();

        $this->actingAs($reporter)->postJson(route('comments.report', $comment), ['reason' => 'harassment'])->assertOk();
        $this->postJson(route('comments.report', $comment), ['reason' => 'harassment'])->assertOk();
        $this->assertSame(1, Report::count());

        $this->actingAs($author)->postJson(route('comments.report', $comment), ['reason' => 'other'])->assertStatus(422);
    }

    // ----------------------------------------------------------------- subidas

    public function test_avatar_is_cropped_square_and_stored()
    {
        [$owner, $business] = $this->ownerWithBusiness();

        $this->actingAs($owner)
            ->post(route('business.avatar.update'), ['image' => UploadedFile::fake()->image('logo.jpg', 1200, 800)])
            ->assertRedirect();

        $path = $business->refresh()->avatar_path;
        Storage::disk('public')->assertExists($path);
        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame([640, 640], [$w, $h]);
    }

    public function test_a_post_with_photos_is_published()
    {
        [$owner, $business] = $this->ownerWithBusiness();

        $this->actingAs($owner)->post(route('business.posts.store'), [
            'title' => 'Tanda nueva',
            'body' => 'Recién horneadas',
            'media' => [UploadedFile::fake()->image('a.jpg', 2400, 2400), UploadedFile::fake()->image('b.png', 800, 600)],
            'also_story' => true,
        ])->assertRedirect(route('business.show', $business));

        $post = $business->posts()->first();
        $this->assertSame(2, $post->media()->count());
        $this->assertSame(1, $business->stories()->count(), 'También se compartió como historia.');

        [$w] = getimagesizefromstring(Storage::disk('public')->get($post->media()->first()->path));
        $this->assertSame(1600, $w, 'Las fotos grandes se reducen.');
    }

    public function test_weekly_post_quota_is_enforced()
    {
        // Sin "Todo libre" y sin ser fundador, rigen los topes con los que entró.
        Setting::set('free_mode', false);
        Plan::where('code', 'free')->update(['post_quota_weekly' => 1]);
        [$owner, $business] = $this->ownerWithBusiness();
        $this->assertFalse($business->isFounder());

        $payload = fn () => ['title' => 'Otra', 'media' => [UploadedFile::fake()->image('a.jpg')]];

        $this->actingAs($owner)->post(route('business.posts.store'), $payload())->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('business.posts.store'), $payload())->assertSessionHasErrors('title');

        $this->assertSame(1, $business->posts()->count());
    }

    public function test_a_story_can_be_saved_into_a_new_highlight()
    {
        [$owner, $business] = $this->ownerWithBusiness();

        $this->actingAs($owner)->post(route('business.stories.store'), [
            'media' => UploadedFile::fake()->image('story.jpg', 1080, 1920),
            'caption' => 'Saliendo del horno',
            'new_highlight' => 'Cómo trabajo',
        ])->assertSessionHasNoErrors();

        $highlight = $business->highlights()->first();
        $this->assertSame('Cómo trabajo', $highlight->title);
        $this->assertSame(1, $highlight->stories()->count());
        $this->assertTrue($business->stories()->first()->expires_at->isFuture());
    }

    public function test_publishing_requires_business_mode()
    {
        [, $business] = $this->ownerWithBusiness();
        $shopper = User::factory()->create();

        $this->actingAs($shopper)->post(route('business.posts.store'), [
            'title' => 'No', 'media' => [UploadedFile::fake()->image('a.jpg')],
        ])->assertForbidden();

        $this->actingAs($shopper)->get(route('business.publish'))->assertRedirect(route('feed'));
    }

    public function test_everything_is_free_pro_only_adds_reach_and_scale()
    {
        $free = Plan::where('code', 'free')->first();
        $pro = Plan::where('code', 'pro')->first();

        foreach (['can_checkout', 'can_reserve_stock', 'can_use_team'] as $feature) {
            $this->assertTrue($free->{$feature}, "Gratis incluye {$feature}.");
        }
        $this->assertNotNull($free->post_quota_weekly);
        $this->assertNull($pro->post_quota_weekly);
    }
}
