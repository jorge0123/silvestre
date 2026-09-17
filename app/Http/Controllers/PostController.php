<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Post;
use App\Support\Present;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Una publicación completa, con todas sus fotos y videos. */
class PostController extends Controller
{
    public function show(Request $request, Business $business, Post $post): Response
    {
        abort_unless($post->published_at && $post->published_at->isPast(), 404);

        $post->load(['media', 'business.category', 'business.zone', 'linkable']);

        $reacted = $request->user()
            && \App\Models\PostReaction::where('user_id', $request->user()->id)->where('post_id', $post->id)->exists();

        return Inertia::render('public/PostShow', [
            'post' => Present::post($post, full: true, reacted: $reacted),
            'more' => $business->posts()->published()
                ->whereKeyNot($post->id)
                ->with(['media', 'business.category', 'business.zone'])
                ->latest('published_at')->limit(6)->get()
                ->map(fn (Post $p) => Present::post($p)),
            'whatsapp' => $business->whatsappUrl("Hola, vi tu publicación \"{$post->title}\" en Silvestre."),
            'viewer' => [
                'isGuest' => $request->user() === null,
                'isFollowing' => $request->user()?->isFollowing($business) ?? false,
                'isOwner' => $request->user()?->canManage($business) ?? false,
            ],
        ]);
    }
}
