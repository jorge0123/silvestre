<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModerationAction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Dar o quitar acceso al panel de administración. */
class AdminUserController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q'));

        $users = User::query()
            ->withCount('ownedBusinesses')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderByDesc('is_admin')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'isAdmin' => $u->is_admin,
                'businesses' => $u->owned_businesses_count,
                'since' => $u->created_at?->translatedFormat('j M Y'),
                'isYou' => $u->id === $request->user()->id,
            ]);

        return Inertia::render('admin/Users', [
            'users' => $users,
            'filters' => ['q' => $search],
        ]);
    }

    public function toggleAdmin(Request $request, User $user): RedirectResponse
    {
        // Nunca te quitas tu propio acceso: el panel no puede quedar sin nadie.
        if ($user->is($request->user())) {
            throw ValidationException::withMessages(['user' => 'No puedes quitarte tu propio acceso. Pídeselo a otra persona administradora.']);
        }

        $grant = ! $user->is_admin;
        $user->forceFill(['is_admin' => $grant])->save();

        ModerationAction::log(
            $request->user(),
            $user,
            $grant ? 'admin.grant' : 'admin.revoke',
            $grant ? "Dio acceso de administración a {$user->name}" : "Quitó el acceso de administración a {$user->name}",
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $grant ? "{$user->name} ahora es administrador." : "{$user->name} ya no es administrador.",
        ]);

        return back();
    }
}
