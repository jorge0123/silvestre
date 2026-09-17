<?php

namespace App\Http\Middleware;

use App\Enums\AccountType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Manda al onboarding a quien se registró para vender y todavía no tiene un
 * negocio terminado. A una cuenta personal nunca la obliga, aunque haya
 * dejado un negocio a medias: ve un aviso para continuar, no un muro.
 */
class EnsureOnboarded
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $hasFinishedBusiness = $user->ownedBusinesses()
            ->whereNotNull('onboarding_completed_at')
            ->exists();

        $cameToSell = $user->account_type === AccountType::Business;

        if (! $hasFinishedBusiness && $cameToSell) {
            return redirect()->route('onboarding.show');
        }

        return $next($request);
    }
}
