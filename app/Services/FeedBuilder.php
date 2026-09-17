<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Post;
use App\Models\PostReaction;
use App\Models\Story;
use App\Models\User;
use App\Support\Present;
use Illuminate\Support\Collection;

/**
 * El Inicio, al estilo Facebook, con scroll infinito.
 *
 * El flujo, página tras página:
 *  1. Publicaciones de los negocios que sigues (máximo 2 por negocio por día).
 *  2. Cuando se acaban, un aviso "Ya estás al día" y luego descubrimientos:
 *     publicaciones de negocios que no sigues, marcadas como sugerencia.
 *  3. Cada 3 publicaciones, un anuncio de un negocio Pro, siempre marcado
 *     "Promocionado", nunca repetido y nunca de un negocio tuyo o que sigues.
 *  4. Después de la 2.ª publicación y luego cada 8, una fila de negocios
 *     sugeridos (por reputación, nunca por pago), distinta cada vez.
 */
class FeedBuilder
{
    public const PAGE_SIZE = 8;

    public const POSTS_PER_BUSINESS_PER_DAY = 2;

    public const SPONSORED_EVERY = 3;

    public const SUGGESTIONS_FIRST_AT = 2;

    public const SUGGESTIONS_EVERY = 8;

    public const SUGGESTIONS_PER_ROW = 4;

    /** Primera carga: historias + primera página. */
    public function for(User $user): array
    {
        $ctx = $this->context($user);

        return [
            'exploring' => $ctx['followed']->isEmpty(),
            'followingCount' => $ctx['followed']->count(),
            'stories' => $this->stories($ctx),
            ...$this->page($user, 1, $ctx),
        ];
    }

    /** Una página del flujo. La interfaz pide la siguiente al llegar al final. */
    public function page(User $user, int $page, ?array $ctx = null): array
    {
        $ctx ??= $this->context($user);
        $page = max(1, $page);

        $following = $this->followedPosts($ctx);
        $sponsored = $this->sponsoredPosts($user, $ctx);
        $discovery = $this->discoveryPosts($ctx, $following->pluck('id')->merge($sponsored->pluck('id')));

        $stream = $following->map(fn (Post $p) => [$p, 'following'])
            ->concat($discovery->map(fn (Post $p) => [$p, 'discovery']))
            ->values();

        $start = ($page - 1) * self::PAGE_SIZE;
        $slice = $stream->slice($start, self::PAGE_SIZE)->values();

        $posts = $slice->map(fn ($row) => $row[0])->concat($sponsored);
        $reacted = $this->reactedIds($user, $posts->pluck('id'));
        $suggested = $this->suggestedBusinesses($ctx);

        $items = [];

        foreach ($slice as $offset => [$post, $variant]) {
            $position = $start + $offset; // posición global en el flujo

            // Frontera: se acabaron las novedades de lo que sigues.
            if ($position === $following->count() && $following->isNotEmpty()) {
                $items[] = ['kind' => 'caught_up', 'key' => 'caught-up'];
            }

            $items[] = $this->postItem($post, $variant, $reacted);

            if (($position + 1) % self::SPONSORED_EVERY === 0) {
                $ad = $sponsored->get(intdiv($position + 1, self::SPONSORED_EVERY) - 1);
                if ($ad) {
                    $items[] = $this->postItem($ad, 'sponsored', $reacted);
                }
            }

            if ($position + 1 === self::SUGGESTIONS_FIRST_AT
                || ($position + 1 > self::SUGGESTIONS_FIRST_AT && ($position + 1 - self::SUGGESTIONS_FIRST_AT) % self::SUGGESTIONS_EVERY === 0)) {
                $row = intdiv(max(0, $position + 1 - self::SUGGESTIONS_FIRST_AT), self::SUGGESTIONS_EVERY);
                $chunk = $suggested->slice($row * self::SUGGESTIONS_PER_ROW, self::SUGGESTIONS_PER_ROW)->values();

                if ($chunk->isNotEmpty()) {
                    $items[] = [
                        'kind' => 'suggested',
                        'key' => "suggested-{$row}",
                        'title' => $row === 0 ? 'Negocios que te pueden gustar' : 'Más negocios para descubrir',
                        'businesses' => $chunk->map(fn (Business $b) => Present::businessCard($b))->all(),
                    ];
                }
            }
        }

        // Pocas publicaciones en total: igual se muestran sugeridos y un anuncio.
        if ($page === 1 && $stream->count() < self::SUGGESTIONS_FIRST_AT && $suggested->isNotEmpty()) {
            $items[] = [
                'kind' => 'suggested', 'key' => 'suggested-0', 'title' => 'Negocios que te pueden gustar',
                'businesses' => $suggested->take(self::SUGGESTIONS_PER_ROW)->map(fn (Business $b) => Present::businessCard($b))->all(),
            ];
        }
        if ($page === 1 && $stream->count() < self::SPONSORED_EVERY && $sponsored->isNotEmpty()) {
            $items[] = $this->postItem($sponsored->first(), 'sponsored', $reacted);
        }

        return [
            'items' => $items,
            'page' => $page,
            'hasMore' => $stream->count() > $start + self::PAGE_SIZE,
        ];
    }

    // ------------------------------------------------------------- contexto

    private function context(User $user): array
    {
        $own = $user->ownedBusinesses()->pluck('id')
            ->merge($user->manageableBusinesses()->pluck('businesses.id'))
            ->unique()->values();

        return [
            'user' => $user,
            'own' => $own,
            'followed' => $user->follows()->pluck('businesses.id'),
        ];
    }

    private function postItem(Post $post, string $variant, Collection $reacted): array
    {
        return [
            'kind' => 'post',
            'key' => "{$variant}-{$post->id}",
            'variant' => $variant,
            'post' => Present::post($post, reacted: $reacted->contains($post->id)),
        ];
    }

    private function reactedIds(User $user, Collection $postIds): Collection
    {
        return PostReaction::where('user_id', $user->id)->whereIn('post_id', $postIds)->pluck('post_id');
    }

    private function basePostQuery()
    {
        return Post::published()->with(['media', 'business.category', 'business.zone']);
    }

    // ---------------------------------------------------------- publicaciones

    /** De los negocios que sigues, con el tope de 2 por negocio por día. */
    private function followedPosts(array $ctx): Collection
    {
        if ($ctx['followed']->isEmpty()) {
            return collect();
        }

        $perDay = [];

        return $this->basePostQuery()
            ->whereIn('business_id', $ctx['followed'])
            ->where('published_at', '>=', now()->subDays(60))
            ->latest('published_at')
            ->limit(300)
            ->get()
            ->filter(function (Post $post) use (&$perDay) {
                $key = $post->business_id.'|'.$post->published_at->toDateString();
                $perDay[$key] = ($perDay[$key] ?? 0) + 1;

                return $perDay[$key] <= self::POSTS_PER_BUSINESS_PER_DAY;
            })
            ->values();
    }

    /** Lo más reciente de negocios que no sigues, para seguir descubriendo. */
    private function discoveryPosts(array $ctx, Collection $alreadyShown): Collection
    {
        $excluded = $ctx['own']->merge($ctx['followed'])->unique();

        return $this->basePostQuery()
            ->whereHas('business', fn ($q) => $q->discoverable())
            ->whereNotIn('business_id', $excluded)
            ->whereNotIn('id', $alreadyShown)
            ->where('published_at', '>=', now()->subDays(90))
            ->latest('published_at')
            ->limit(200)
            ->get();
    }

    /** Un anuncio por negocio Pro elegible, rotando por día y por persona. */
    private function sponsoredPosts(User $user, array $ctx): Collection
    {
        $excluded = $ctx['own']->merge($ctx['followed'])->unique();

        $proIds = Business::discoverable()->pro()->whereNotIn('id', $excluded)->pluck('id');

        return $this->basePostQuery()
            ->whereIn('business_id', $proIds)
            ->latest('published_at')
            ->get()
            ->unique('business_id')
            ->sortBy(fn (Post $p) => crc32(now()->toDateString().'|'.$user->id.'|'.$p->business_id))
            ->values();
    }

    // ------------------------------------------------------ historias y sugeridos

    private function stories(array $ctx): array
    {
        $sources = $ctx['followed']->isEmpty()
            ? Business::discoverable()->whereNotIn('id', $ctx['own'])->pluck('id')
            : $ctx['followed'];

        return Story::live()
            ->whereIn('business_id', $sources)
            ->with('business')
            ->oldest()
            ->get()
            ->groupBy('business_id')
            ->map(fn (Collection $stories) => Present::storyGroup($stories->first()->business, $stories))
            ->values()
            ->all();
    }

    /** Por reputación y actividad. El pago no influye aquí. */
    private function suggestedBusinesses(array $ctx): Collection
    {
        return Business::discoverable()
            ->whereNotIn('id', $ctx['own']->merge($ctx['followed'])->unique())
            ->with(['category', 'zone'])
            ->orderByDesc('rating_bayes')
            ->orderByDesc('last_posted_at')
            ->limit(24)
            ->get();
    }
}
