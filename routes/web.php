<?php

use App\Http\Controllers\Admin\AdminBusinessController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\BusinessContentController;
use App\Http\Controllers\BusinessProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\GuideController;
use App\Http\Controllers\ModeController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PostInteractionController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureOnboarded;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::middleware(EnsureOnboarded::class)->group(function () {
        // Inicio al estilo Facebook: lo primero que ve todo el mundo al entrar.
        Route::get('inicio', FeedController::class)->name('feed');
        Route::get('inicio/mas', [FeedController::class, 'more'])->name('feed.more');

        // Panel del negocio (solo tiene sentido en modo negocio).
        Route::get('dashboard', DashboardController::class)->name('dashboard');
    });

    // Abrir un negocio, paso a paso.
    Route::get('onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('onboarding/{step}', [OnboardingController::class, 'update'])
        ->whereIn('step', OnboardingController::STEPS)
        ->name('onboarding.update');

    // Una cuenta, dos modos.
    Route::post('modo/personal', [ModeController::class, 'personal'])->name('mode.personal');
    Route::post('modo/negocio/{business}', [ModeController::class, 'business'])->name('mode.business');

    // Guías dentro de la app.
    Route::post('guias/{key}/cerrar', [GuideController::class, 'dismiss'])->name('guides.dismiss');
    Route::post('guias/reiniciar', [GuideController::class, 'reset'])->name('guides.reset');

    Route::post('@{business}/seguir', FollowController::class)->name('business.follow');

    // Reacciones y comentarios, sin salir de la publicación.
    Route::post('publicaciones/{post}/reaccion', [PostInteractionController::class, 'react'])
        ->middleware('throttle:60,1')->name('posts.react');
    Route::post('publicaciones/{post}/comentarios', [PostInteractionController::class, 'store'])
        ->middleware('throttle:20,1')->name('posts.comments.store');
    Route::patch('comentarios/{comment}', [PostInteractionController::class, 'update'])->name('comments.update');
    Route::delete('comentarios/{comment}', [PostInteractionController::class, 'destroy'])->name('comments.destroy');
    Route::post('comentarios/{comment}/reportar', [PostInteractionController::class, 'report'])
        ->middleware('throttle:10,1')->name('comments.report');

    // Lo que publica el negocio (en modo negocio).
    Route::prefix('negocio')->name('business.')->group(function () {
        Route::get('publicar', [BusinessContentController::class, 'create'])->name('publish');
        Route::post('publicaciones', [BusinessContentController::class, 'storePost'])->name('posts.store');
        Route::delete('publicaciones/{post}', [BusinessContentController::class, 'destroyPost'])->name('posts.destroy');
        Route::post('historias', [BusinessContentController::class, 'storeStory'])->name('stories.store');
        Route::delete('historias/{story}', [BusinessContentController::class, 'destroyStory'])->name('stories.destroy');
        Route::post('perfil/foto', [BusinessContentController::class, 'updateAvatar'])->name('avatar.update');
        Route::post('perfil/portada', [BusinessContentController::class, 'updateCover'])->name('cover.update');
        Route::patch('perfil', [BusinessContentController::class, 'updateProfile'])->name('profile.update');
    });

    // Panel del equipo de Silvestre: planes, precios, permisos y "Todo libre".
    Route::prefix('admin')->name('admin.')->middleware(EnsureAdmin::class)->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('index');
        Route::patch('todo-libre', [AdminController::class, 'updateFreeMode'])->name('free-mode.update');

        Route::get('planes', [PlanController::class, 'index'])->name('plans.index');
        Route::get('planes/nuevo', [PlanController::class, 'create'])->name('plans.create');
        Route::post('planes', [PlanController::class, 'store'])->name('plans.store');
        Route::get('planes/{plan}/editar', [PlanController::class, 'edit'])->name('plans.edit');
        Route::put('planes/{plan}', [PlanController::class, 'update'])->name('plans.update');
        Route::delete('planes/{plan}', [PlanController::class, 'destroy'])->name('plans.destroy');

        Route::get('negocios', [AdminBusinessController::class, 'index'])->name('businesses.index');
        Route::post('negocios/{business:id}/plan', [AdminBusinessController::class, 'assignPlan'])->name('businesses.plan');
        Route::post('negocios/{business:id}/fundador', [AdminBusinessController::class, 'toggleFounder'])->name('businesses.founder');

        Route::get('personas', [AdminUserController::class, 'index'])->name('users.index');
        Route::post('personas/{user}/admin', [AdminUserController::class, 'toggleAdmin'])->name('users.admin');
    });
});

require __DIR__.'/settings.php';

// Los comentarios se pueden leer sin cuenta, igual que la publicación.
Route::get('publicaciones/{post}/comentarios', [PostInteractionController::class, 'index'])->name('posts.comments.index');

// Perfil público y publicaciones: se pueden ver sin cuenta, porque es lo que
// el negocio comparte por WhatsApp. Van al final para no tapar otras rutas.
Route::get('@{business}', [BusinessProfileController::class, 'show'])->name('business.show');
Route::get('@{business}/p/{post}', [PostController::class, 'show'])
    ->scopeBindings()
    ->name('posts.show');
