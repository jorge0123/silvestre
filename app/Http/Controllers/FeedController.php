<?php

namespace App\Http\Controllers;

use App\Services\FeedBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Inicio: historias, publicaciones de lo que sigues, sugeridos y publicidad Pro. */
class FeedController extends Controller
{
    public function __invoke(Request $request, FeedBuilder $feed): Response
    {
        $user = $request->user();

        return Inertia::render('Feed', [
            ...$feed->for($user),
            'unfinished' => $user->unfinishedBusiness()?->only(['name', 'slug']),
        ]);
    }

    /** Scroll infinito: la siguiente página, sin recargar la pantalla. */
    public function more(Request $request, FeedBuilder $feed): JsonResponse
    {
        $page = (int) $request->validate(['page' => ['required', 'integer', 'min:2', 'max:200']])['page'];

        return response()->json($feed->page($request->user(), $page));
    }
}
