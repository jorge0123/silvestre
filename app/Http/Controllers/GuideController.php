<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Recuerda qué guías ya cerró cada persona, para no repetírselas. */
class GuideController extends Controller
{
    public function dismiss(Request $request, string $key): RedirectResponse
    {
        abort_unless(preg_match('/^[a-z0-9][a-z0-9.\-]{1,60}$/', $key) === 1, 404);

        $request->user()->dismissGuide($key);

        return back();
    }

    /** "Volver a ver las guías" desde el panel. */
    public function reset(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['dismissed_guides' => []])->save();

        return back();
    }
}
