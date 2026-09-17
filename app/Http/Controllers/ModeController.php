<?php

namespace App\Http\Controllers;

use App\Models\Business;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Cambio entre modo personal y modo negocio. Una sola cuenta, sin cerrar
 * sesión: la misma persona compra galletas por la mañana y administra su
 * tienda por la tarde.
 */
class ModeController extends Controller
{
    public function personal(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->switchToPersonal();

        // "Prefiero solo comprar por ahora": si se registró para vender pero
        // aún no terminó ningún negocio, deja de insistirle con el onboarding.
        // Puede abrir su negocio cuando quiera desde el selector de modo.
        if (! $user->ownedBusinesses()->whereNotNull('onboarding_completed_at')->exists()) {
            $user->forceFill(['account_type' => \App\Enums\AccountType::Personal])->save();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Estás en modo personal: compra y sigue negocios.',
        ]);

        return redirect()->route('feed');
    }

    public function business(Request $request, Business $business): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->canManage($business), 403, 'No administras este negocio.');

        // Un negocio a medio configurar no se puede administrar todavía.
        if (! $business->hasFinishedOnboarding()) {
            return redirect()->route('onboarding.show');
        }

        $user->switchToBusiness($business);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Estás administrando {$business->name}.",
        ]);

        return redirect()->route('dashboard');
    }
}
