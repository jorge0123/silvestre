<?php

namespace App\Http\Controllers;

use App\Enums\ModerationState;
use App\Models\Business;
use App\Models\BusinessHour;
use App\Models\Post;
use App\Models\Product;
use App\Models\Review;
use App\Models\Service;
use App\Models\StoryHighlight;
use App\Support\Media;
use App\Support\Present;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Perfil público de un negocio: silvestre.app/@usuario
 *
 * Es la página que el negocio comparte por WhatsApp, así que se puede ver sin
 * cuenta. Solo desde aquí se pide: el Inicio lleva al perfil, no al carrito.
 */
class BusinessProfileController extends Controller
{
    public function show(Request $request, Business $business): Response
    {
        $viewer = $request->user();
        $isOwner = $viewer?->canManage($business) ?? false;

        // Un negocio sin terminar o suspendido solo lo ve su dueño.
        $hidden = ! $business->hasFinishedOnboarding()
            || $business->moderation_state === ModerationState::Suspended->value;
        abort_if($hidden && ! $isOwner, 404);

        $business->load([
            'category', 'zone', 'hours', 'fulfillment', 'serviceModes', 'deliveryZones',
            'paymentMethods', 'highlights.stories',
        ]);

        $liveStories = $business->stories()->live()->oldest()->get();

        return Inertia::render('public/BusinessProfile', [
            'business' => [
                ...Present::businessCard($business),
                'about' => $business->about,
                'kind' => $business->kind->label(),
                'openNow' => $business->isOpenNow(),
                'whatsapp' => $business->whatsappUrl(),
                'hours' => $business->hours->sortBy(fn ($h) => ($h->weekday + 6) % 7)->map(fn (BusinessHour $h) => [
                    'day' => $h->weekdayName(),
                    'range' => substr((string) $h->opens_at, 0, 5).' – '.substr((string) $h->closes_at, 0, 5),
                ])->values(),
                'fulfillment' => $business->fulfillment->where('is_active', true)->map(fn ($f) => $f->method->label())->values(),
                'serviceModes' => $business->serviceModes->where('is_active', true)->map(fn ($m) => $m->mode->label())->values(),
                'deliveryZones' => $business->deliveryZones->map(fn ($z) => [
                    'name' => $z->name,
                    'fee' => $z->fee_cents === 0 ? 'Gratis' : config('silvestre.currency.symbol').number_format($z->fee_cents / 100, 2),
                ])->values(),
                'paymentMethods' => $business->paymentMethods->where('is_active', true)->map(fn ($p) => $p->method->label())->values(),
                'distribution' => $this->distribution($business),
                'minReviewsToShowAverage' => Business::MIN_REVIEWS_TO_SHOW_AVG,
            ],
            'stories' => $liveStories->isEmpty() ? null : Present::storyGroup($business, $liveStories),
            'highlights' => $business->highlights->map(fn (StoryHighlight $h) => [
                'id' => $h->id,
                'title' => $h->title,
                'cover' => Media::url($h->cover_path ?? $h->stories->first()?->poster_path ?? $h->stories->first()?->media_path),
                'group' => Present::storyGroup($business, $h->stories),
            ])->values(),
            'products' => $business->sellsProducts()
                ? $business->products()->where('is_active', true)->with(['variants', 'media'])->orderBy('position')->get()
                    ->map(fn (Product $p) => [...Present::product($p), 'order' => $business->whatsappUrl("Hola, quiero pedir: {$p->name}. Lo vi en Silvestre.")])
                : [],
            'services' => $business->sellsServices()
                ? $business->services()->where('is_active', true)->with(['media', 'business.serviceModes'])->orderBy('position')->get()
                    ->map(fn (Service $s) => [...Present::service($s), 'book' => $business->whatsappUrl("Hola, quiero agendar: {$s->name}. Lo vi en Silvestre.")])
                : [],
            'posts' => $this->posts($business, $viewer),
            'reviews' => $business->reviews()->with(['user', 'reply'])->latest()->limit(20)->get()
                ->map(fn (Review $r) => Present::review($r)),
            'viewer' => [
                'isGuest' => $viewer === null,
                'isOwner' => $isOwner,
                'isFollowing' => $viewer?->isFollowing($business) ?? false,
            ],
        ]);
    }

    private function posts(Business $business, ?\App\Models\User $viewer)
    {
        $posts = $business->posts()->published()->with(['media', 'business.category', 'business.zone'])
            ->latest('published_at')->limit(30)->get();

        $reacted = $viewer
            ? \App\Models\PostReaction::where('user_id', $viewer->id)->whereIn('post_id', $posts->pluck('id'))->pluck('post_id')
            : collect();

        return $posts->map(fn (Post $p) => Present::post($p, reacted: $reacted->contains($p->id)));
    }

    /** Cuántas reseñas de 5, 4, 3, 2 y 1 estrellas. */
    private function distribution(Business $business): array
    {
        $counts = $business->reviews()->selectRaw('stars, count(*) as total')->groupBy('stars')->pluck('total', 'stars');

        return collect([5, 4, 3, 2, 1])->map(fn (int $stars) => [
            'stars' => $stars,
            'count' => (int) ($counts[$stars] ?? 0),
        ])->all();
    }
}
