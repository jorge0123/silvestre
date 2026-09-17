<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\ModerationAction;
use App\Models\Post;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Platform;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Portada del panel: números clave, "Todo libre" y registro de cambios. */
class AdminController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/Index', [
            'stats' => [
                'users' => User::count(),
                'businesses' => Business::whereNotNull('onboarding_completed_at')->count(),
                'postsThisWeek' => Post::where('published_at', '>=', now()->startOfWeek())->count(),
                'promoted' => Business::pro()->count(),
                'paidActive' => Subscription::where('state', 'active')
                    ->whereHas('plan', fn ($q) => $q->where('code', '!=', 'free'))->count(),
            ],
            'freeMode' => Platform::freeMode(),
            'founders' => Business::whereNotNull('founder_at')->count(),
            'nonFounders' => Business::whereNull('founder_at')->count(),
            'activity' => ModerationAction::with('moderator:id,name')->latest()->limit(15)->get()
                ->map(fn (ModerationAction $a) => [
                    'id' => $a->id,
                    'who' => $a->moderator?->name ?? 'Sistema',
                    'what' => $a->reason,
                    'note' => $a->note,
                    'ago' => $a->created_at?->diffForHumans(),
                ]),
        ]);
    }

    public function updateFreeMode(Request $request): RedirectResponse
    {
        $enabled = $request->validate(['enabled' => ['required', 'boolean']])['enabled'];

        Setting::set('free_mode', (bool) $enabled, $request->user());

        ModerationAction::log(
            $request->user(),
            Setting::where('key', 'free_mode')->firstOrFail(),
            'settings.free_mode',
            $enabled ? 'Encendió "Todo libre"' : 'Apagó "Todo libre"',
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $enabled
                ? 'Todo libre: nadie tiene límites y quien llegue desde ahora será fundador.'
                : 'Listo: los fundadores siguen sin límites; los negocios nuevos usarán los de su plan.',
        ]);

        return back();
    }
}
