<?php

namespace App\Support;

use App\Models\Business;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostMedia;
use App\Models\User;
use App\Models\Product;
use App\Models\Review;
use App\Models\Service;
use App\Models\Story;

/**
 * Convierte modelos en lo que ve la interfaz. Un solo lugar para decidir qué
 * datos salen al navegador: nada de modelos completos con campos internos
 * (costos, notas de moderación, datos de pago) viajando por accidente.
 */
class Present
{
    public static function businessCard(Business $business): array
    {
        return [
            'id' => $business->id,
            'name' => $business->name,
            'slug' => $business->slug,
            'intro' => $business->intro,
            'avatar' => Media::url($business->avatar_path),
            'cover' => Media::url($business->cover_path),
            'category' => $business->category?->name,
            'zone' => $business->zone?->name,
            'offering' => $business->offering->value,
            'rating' => $business->publicRating(),
            'ratingCount' => $business->rating_count,
            'followers' => $business->followers_count,
            'verified' => $business->is_verified,
        ];
    }

    public static function story(Story $story): array
    {
        return [
            'id' => $story->id,
            'type' => $story->media_type ?? 'image',
            'url' => Media::url($story->media_path),
            'poster' => Media::url($story->poster_path),
            'caption' => $story->caption,
            'ago' => $story->created_at?->diffForHumans(),
        ];
    }

    /** @param iterable<Story> $stories */
    public static function storyGroup(Business $business, iterable $stories): array
    {
        return [
            'business' => [
                'name' => $business->name,
                'slug' => $business->slug,
                'avatar' => Media::url($business->avatar_path),
            ],
            'stories' => collect($stories)->map(fn (Story $s) => self::story($s))->values(),
        ];
    }

    public static function media(PostMedia $media): array
    {
        return [
            'type' => $media->type ?? 'image',
            'url' => Media::url($media->path),
            'poster' => Media::url($media->poster_path),
        ];
    }

    /**
     * Una publicación. El texto completo siempre viaja: la interfaz decide si
     * lo recorta con "Ver más", sin tener que ir a otra página.
     */
    public static function post(Post $post, bool $full = false, bool $reacted = false): array
    {
        $body = (string) $post->body;

        return [
            'id' => $post->id,
            'title' => $post->title,
            'body' => $body,
            'ago' => $post->published_at?->diffForHumans(),
            'date' => $post->published_at?->toIso8601String(),
            'media' => $post->media->map(fn (PostMedia $m) => self::media($m))->values(),
            'reactions' => $post->reactions_count,
            'comments' => $post->comments_count,
            'reacted' => $reacted,
            'business' => self::businessCard($post->business),
        ];
    }

    /** Un comentario con sus respuestas y lo que quien mira puede hacer con él. */
    public static function comment(PostComment $comment, ?User $viewer, bool $withReplies = true): array
    {
        $business = $comment->asBusiness;
        $user = $comment->user;
        $parts = preg_split('/\s+/', trim((string) $user?->name)) ?: [];
        $shortName = trim(($parts[0] ?? 'Usuario').' '.(count($parts) > 1 ? mb_substr(end($parts), 0, 1).'.' : ''));

        return [
            'id' => $comment->id,
            'parentId' => $comment->parent_id,
            'body' => $comment->body,
            'ago' => $comment->created_at?->diffForHumans(null, true, true),
            'edited' => $comment->edited_at !== null,
            'author' => $business ? [
                'name' => $business->name,
                'avatar' => Media::url($business->avatar_path),
                'slug' => $business->slug,
                'isBusiness' => true,
                // El dueño de la publicación respondiendo en su propio espacio.
                'isPostOwner' => $business->id === $comment->post?->business_id,
            ] : [
                'name' => $shortName,
                'avatar' => Media::url($user?->avatar_path),
                'slug' => null,
                'isBusiness' => false,
                'isPostOwner' => false,
            ],
            'can' => [
                'edit' => $comment->isEditableBy($viewer),
                'delete' => $comment->isDeletableBy($viewer),
                'report' => $viewer !== null && $viewer->id !== $comment->user_id,
            ],
            'replies' => $withReplies
                ? $comment->replies->map(fn (PostComment $r) => self::comment($r, $viewer, false))->values()
                : [],
        ];
    }

    public static function product(Product $product): array
    {
        $variant = $product->variants->where('is_active', true)->sortBy('price_cents')->first();
        $available = $product->isAvailable();

        return [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'price' => $product->formattedPrice(),
            'variant' => $variant?->name,
            'stockMode' => $product->stock_mode->value,
            'stockLabel' => $product->stock_mode->label(),
            'available' => $available,
            'lowStock' => $variant?->isLowStock() ?? false,
            'leadDays' => $product->lead_time_days,
            'image' => Media::url($product->media->first()?->path),
        ];
    }

    public static function service(Service $service): array
    {
        return [
            'id' => $service->id,
            'name' => $service->name,
            'description' => $service->description,
            'price' => $service->formattedPrice(),
            'duration' => $service->duration_min,
            'modes' => collect($service->availableModes())->map->label()->values(),
            'image' => Media::url($service->media->first()?->path),
        ];
    }

    public static function review(Review $review): array
    {
        $name = (string) $review->user?->name;
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return [
            'id' => $review->id,
            // "Ana Lucía P." — nombre y la inicial del apellido, nunca completo.
            'author' => trim(($parts[0] ?? 'Cliente').' '.(isset($parts[1]) ? mb_substr($parts[count($parts) - 1], 0, 1).'.' : '')),
            'initials' => mb_strtoupper(mb_substr($parts[0] ?? 'C', 0, 1).mb_substr($parts[count($parts) - 1] ?? '', 0, 1)),
            'stars' => $review->stars,
            'body' => $review->body,
            'ago' => $review->created_at?->diffForHumans(),
            'verified' => $review->isVerifiedPurchase(),
            'reply' => $review->reply ? [
                'body' => $review->reply->body,
                'ago' => $review->reply->created_at?->diffForHumans(),
            ] : null,
        ];
    }
}
