<?php

namespace App\Http\Controllers;

use App\Models\Business;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Seguir o dejar de seguir un negocio. */
class FollowController extends Controller
{
    public function __invoke(Request $request, Business $business): RedirectResponse
    {
        $user = $request->user();

        // Seguir tu propio negocio no tiene sentido y inflaría sus seguidores.
        abort_if($user->canManage($business), 422, 'No puedes seguir tu propio negocio.');

        $changes = $user->follows()->toggle($business->id);
        $delta = count($changes['attached']) - count($changes['detached']);

        if ($delta !== 0) {
            $business->increment('followers_count', $delta);
        }

        return back();
    }
}
