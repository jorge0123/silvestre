<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubscriptionState;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\ModerationAction;
use App\Models\Plan;
use App\Support\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Ver negocios, asignarles un plan a mano y marcar fundadores. */
class AdminBusinessController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q'));
        $filter = $request->query('filtro');

        $businesses = Business::query()
            ->with(['owner:id,name,email', 'subscription.plan:id,name,code'])
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%")
                ->orWhereHas('owner', fn ($o) => $o->where('email', 'like', "%{$search}%"))))
            ->when($filter === 'fundadores', fn ($q) => $q->whereNotNull('founder_at'))
            ->when($filter === 'nuevos', fn ($q) => $q->whereNull('founder_at'))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(function (Business $b) {
                $sub = $b->subscription;
                $current = $sub?->isCurrent();

                return [
                    'id' => $b->id,
                    'name' => $b->name,
                    'slug' => $b->slug,
                    'avatar' => Media::url($b->avatar_path),
                    'owner' => $b->owner?->only(['name', 'email']),
                    'finished' => $b->onboarding_completed_at !== null,
                    'founder' => $b->isFounder(),
                    'founderSince' => $b->founder_at?->translatedFormat('j M Y'),
                    'plan' => $current ? $sub->plan->name : 'Gratis',
                    'state' => match (true) {
                        ! $sub => null,
                        $current => $sub->state->label(),
                        $sub->trial_ends_at !== null => "Prueba de {$sub->plan->name} terminada",
                        default => "{$sub->plan->name} terminado",
                    },
                    'endsAt' => $current ? ($sub->current_period_ends_at ?? $sub->trial_ends_at)?->translatedFormat('j M Y') : null,
                    'lockedPrice' => $current && ($sub->terms['price_cents'] ?? null) !== null
                        ? '$'.rtrim(rtrim(number_format($sub->terms['price_cents'] / 100, 2), '0'), '.')
                            .' / '.($sub->terms['interval_months'] === 1 ? 'mes' : "{$sub->terms['interval_months']} meses")
                        : null,
                ];
            });

        return Inertia::render('admin/Businesses', [
            'businesses' => $businesses,
            'plans' => Plan::where('is_active', true)->orderBy('position')->get(['id', 'name', 'code']),
            'filters' => ['q' => $search, 'filtro' => in_array($filter, ['fundadores', 'nuevos'], true) ? $filter : null],
            'counts' => [
                'all' => Business::count(),
                'founders' => Business::whereNotNull('founder_at')->count(),
            ],
        ]);
    }

    public function assignPlan(Request $request, Business $business): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', Rule::exists('plans', 'id')->where('is_active', true)],
            'months' => ['nullable', 'integer', Rule::in([1, 3, 6, 12])],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        $plan = Plan::findOrFail($data['plan_id']);
        $months = $data['months'] ?? null;

        // Se cierra lo vigente y se abre la nueva suscripción: queda historial.
        // La nueva copia las condiciones de HOY del plan asignado.
        $business->subscriptions()
            ->whereIn('state', ['trial', 'active', 'past_due'])
            ->update(['state' => SubscriptionState::Cancelled, 'cancelled_at' => now(), 'ended_at' => now()]);

        $business->subscriptions()->create([
            'plan_id' => $plan->id,
            'state' => SubscriptionState::Active,
            'current_period_ends_at' => $months ? now()->addMonths($months) : null,
        ]);

        ModerationAction::log(
            $request->user(),
            $business,
            'business.plan',
            "Asignó {$plan->name} a {$business->name}".($months ? " por {$months} ".($months === 1 ? 'mes' : 'meses') : ' sin vencimiento'),
            $data['note'] ?? null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$business->name} ahora tiene {$plan->name}."]);

        return back();
    }

    public function toggleFounder(Request $request, Business $business): RedirectResponse
    {
        $grant = ! $business->isFounder();
        $business->forceFill(['founder_at' => $grant ? now() : null])->save();

        ModerationAction::log(
            $request->user(),
            $business,
            $grant ? 'business.founder.grant' : 'business.founder.revoke',
            $grant ? "Marcó a {$business->name} como fundador" : "Quitó a {$business->name} de fundadores",
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => $grant
            ? "{$business->name} es fundador: sin límites para siempre."
            : "{$business->name} ya no es fundador: se le aplican los límites de su plan."]);

        return back();
    }
}
