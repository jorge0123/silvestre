<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Plan;
use App\Support\Platform;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Panel del negocio: la guía de primeros pasos con lo que le falta al perfil.
 * En modo personal no hay panel que mostrar, así que se va al Inicio.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $business = $request->user()->currentBusiness();

        if (! $business) {
            return redirect()->route('feed');
        }

        return Inertia::render('Dashboard', [
            'business' => $this->businessSummary($business),
        ]);
    }

    private function businessSummary(Business $business): array
    {
        $business->loadMissing(['category', 'subscription.plan']);
        $subscription = $business->subscription;

        return [
            'name' => $business->name,
            'slug' => $business->slug,
            'offering' => $business->offering->value,
            'offeringLabel' => $business->offering->label(),
            'activation' => $business->activation_score,
            'published' => $business->published_at !== null,
            'minToPublish' => Business::MIN_ACTIVATION_TO_PUBLISH,
            'checklist' => collect($business->activationChecklist())
                ->map(fn (bool $done, string $label) => compact('label', 'done'))
                ->values(),
            'plan' => [
                'name' => $subscription?->effectivePlan()->name ?? 'Gratis',
                'onTrial' => (bool) $subscription?->isOnTrial(),
                'trialDaysLeft' => $subscription?->trialDaysLeft() ?? 0,
                'pricing' => Plan::proPricingLabel(),
                'founder' => $business->isFounder(),
                'freeMode' => Platform::freeMode(),
            ],
            'needsDocument' => $business->category?->requires_verification
                ? $business->category->required_document
                : null,
        ];
    }
}
