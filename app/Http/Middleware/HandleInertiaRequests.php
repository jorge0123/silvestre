<?php

namespace App\Http\Middleware;

use App\Models\Plan;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Datos disponibles en todas las páginas.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,

                // Modo actual y negocios entre los que puede cambiar.
                'mode' => fn () => $user ? [
                    'type' => $user->isInBusinessMode() ? 'business' : 'personal',
                    'business' => $user->currentBusiness()?->only(['id', 'name', 'slug']),
                ] : null,

                'businesses' => fn () => $user
                    ? $user->ownedBusinesses()
                        ->orderBy('name')
                        ->get(['id', 'name', 'slug', 'onboarding_completed_at'])
                        ->map(fn ($b) => [
                            'id' => $b->id,
                            'name' => $b->name,
                            'slug' => $b->slug,
                            'finished' => $b->onboarding_completed_at !== null,
                        ])
                    : [],
            ],
            'guides' => fn () => [
                'dismissed' => $user?->dismissed_guides ?? [],
            ],
            'pricing' => fn () => [
                'pro' => Plan::proPricingLabel(),
            ],
            'region' => [
                'currency' => config('silvestre.currency.symbol'),
                'city' => config('silvestre.city'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
