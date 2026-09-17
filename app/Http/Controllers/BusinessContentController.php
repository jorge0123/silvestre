<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Post;
use App\Models\Story;
use App\Models\StoryHighlight;
use App\Services\ContentScanner;
use App\Services\MediaUploader;
use App\Support\Media;
use App\Support\Platform;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lo que publica un negocio: publicaciones con fotos y videos, historias,
 * destacadas, y su foto de perfil y portada.
 *
 * Todo exige estar en modo negocio y tener permiso sobre ese negocio.
 */
class BusinessContentController extends Controller
{
    public const STORY_MAX_SECONDS = 60;

    public const POST_VIDEO_MAX_SECONDS = 180;

    public function __construct(
        private MediaUploader $uploader,
        private ContentScanner $scanner,
    ) {}

    // ---------------------------------------------------------------- pantalla

    public function create(Request $request): Response|RedirectResponse
    {
        $business = $this->business($request, 'posts.manage');

        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $plan = $business->plan();

        return Inertia::render('business/Publish', [
            'business' => [
                'name' => $business->name,
                'slug' => $business->slug,
                'avatar' => Media::url($business->avatar_path),
                'cover' => Media::url($business->cover_path),
                'intro' => $business->intro,
                'about' => $business->about,
            ],
            'limits' => [
                'postsLeftThisWeek' => $business->remainingWeeklyPosts(),
                'storiesLeftToday' => $business->remainingStoriesToday(),
                'mediaPerPost' => $business->mediaPerPostLimit(),
                // Por qué no tiene topes: fundador (para siempre) o "Todo libre" (por ahora).
                'unlimited' => $business->isFounder() ? 'founder' : (Platform::freeMode() ? 'free' : null),
                'storySeconds' => self::STORY_MAX_SECONDS,
                'postVideoSeconds' => self::POST_VIDEO_MAX_SECONDS,
                'maxImageMb' => 10,
                'maxVideoMb' => (int) (MediaUploader::MAX_VIDEO_KB / 1024),
                'plan' => $plan->name,
            ],
            'highlights' => $business->highlights()->get(['id', 'title']),
            'stories' => $business->stories()->live()->latest()->get()->map(fn (Story $s) => [
                'id' => $s->id,
                'type' => $s->media_type,
                'url' => Media::url($s->media_type === 'video' ? ($s->poster_path ?? $s->media_path) : $s->media_path),
                'caption' => $s->caption,
                'ago' => $s->created_at?->diffForHumans(),
            ]),
            'tab' => $request->query('tab') === 'historia' ? 'historia' : ($request->query('tab') === 'perfil' ? 'perfil' : 'publicacion'),
        ]);
    }

    // ----------------------------------------------------------- publicaciones

    public function storePost(Request $request): RedirectResponse
    {
        $business = $this->businessOrFail($request, 'posts.manage');
        $mediaLimit = $business->mediaPerPostLimit();

        if (! $business->canPublishPost()) {
            throw ValidationException::withMessages([
                'title' => "Ya usaste tus {$business->limit('post_quota_weekly')} publicaciones de esta semana. Se reinician el lunes.",
            ]);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:3000'],
            'media' => ['required', 'array', 'min:1', 'max:'.$mediaLimit],
            'media.*' => ['required', 'file', 'max:'.MediaUploader::MAX_VIDEO_KB, 'mimetypes:image/jpeg,image/png,image/webp,'.implode(',', MediaUploader::VIDEO_MIMES)],
            'also_story' => ['boolean'],
        ], [
            'media.required' => 'Agrega al menos una foto o un video.',
            'media.max' => "Puedes subir hasta {$mediaLimit} fotos o videos por publicación.",
            'media.*.mimetypes' => 'Solo fotos (JPG, PNG, WEBP) o videos (MP4, MOV, WEBM).',
            'media.*.max' => 'Cada archivo puede pesar hasta 60 MB.',
        ]);

        $this->guard('title', $data['title'], $data['body'] ?? '');

        $dir = "businesses/{$business->id}/posts";
        $stored = [];

        foreach ($data['media'] as $i => $file) {
            if ($this->uploader->isVideo($file)) {
                $video = $this->uploader->storeVideo($file, $dir, self::POST_VIDEO_MAX_SECONDS);
                $stored[] = ['type' => 'video', 'path' => $video['path'], 'poster_path' => $video['poster'], 'position' => $i];
            } else {
                if ($file->getSize() > 10 * 1024 * 1024) {
                    throw ValidationException::withMessages(['media' => 'Cada foto puede pesar hasta 10 MB.']);
                }
                $stored[] = ['type' => 'image', 'path' => $this->uploader->storeImage($file, $dir, 1600), 'position' => $i];
            }
        }

        $post = DB::transaction(function () use ($business, $request, $data, $stored) {
            $post = $business->posts()->create([
                'user_id' => $request->user()->id,
                'title' => $data['title'],
                'body' => $data['body'] ?? null,
                'week_key' => Post::weekKeyFor(now()),
                'published_at' => now(),
            ]);
            $post->media()->createMany($stored);
            $business->forceFill(['last_posted_at' => now()])->save();

            return $post;
        });

        // "Compartir también como historia": la primera foto o video.
        if (($data['also_story'] ?? false) && ($business->remainingStoriesToday() ?? 1) > 0) {
            $first = $stored[0];
            $business->stories()->create([
                'media_path' => $first['path'],
                'media_type' => $first['type'],
                'poster_path' => $first['poster_path'] ?? null,
                'caption' => $data['title'],
                'linkable_type' => Post::class,
                'linkable_id' => $post->id,
                'expires_at' => now()->addHours(Story::LIFETIME_HOURS),
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Publicado. Ya aparece en el Inicio de tus seguidores.']);

        return redirect()->route('business.show', $business);
    }

    public function destroyPost(Request $request, Post $post): RedirectResponse
    {
        $business = $this->businessOrFail($request, 'posts.manage');
        abort_unless($post->business_id === $business->id, 403);

        $post->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Publicación eliminada.']);

        return back();
    }

    // --------------------------------------------------------------- historias

    public function storeStory(Request $request): RedirectResponse
    {
        $business = $this->businessOrFail($request, 'posts.manage');

        if ($business->remainingStoriesToday() === 0) {
            throw ValidationException::withMessages([
                'media' => 'Ya subiste las historias de hoy. Mañana puedes subir más.',
            ]);
        }

        $data = $request->validate([
            'media' => ['required', 'file', 'max:'.MediaUploader::MAX_VIDEO_KB, 'mimetypes:image/jpeg,image/png,image/webp,'.implode(',', MediaUploader::VIDEO_MIMES)],
            'caption' => ['nullable', 'string', 'max:120'],
            'highlight_id' => ['nullable', 'integer', Rule::exists('story_highlights', 'id')->where('business_id', $business->id)],
            'new_highlight' => ['nullable', 'string', 'max:40'],
        ], [
            'media.required' => 'Elige una foto o un video para tu historia.',
            'media.mimetypes' => 'Solo fotos (JPG, PNG, WEBP) o videos (MP4, MOV, WEBM).',
        ]);

        if (filled($data['caption'] ?? null)) {
            $this->guard('caption', $data['caption']);
        }

        $dir = "businesses/{$business->id}/stories";
        $file = $data['media'];

        if ($this->uploader->isVideo($file)) {
            $video = $this->uploader->storeVideo($file, $dir, self::STORY_MAX_SECONDS);
            [$type, $path, $poster] = ['video', $video['path'], $video['poster']];
        } else {
            [$type, $path, $poster] = ['image', $this->uploader->storeImage($file, $dir, 1920), null];
        }

        DB::transaction(function () use ($business, $data, $type, $path, $poster) {
            $story = $business->stories()->create([
                'media_path' => $path,
                'media_type' => $type,
                'poster_path' => $poster,
                'caption' => $data['caption'] ?? null,
                'expires_at' => now()->addHours(Story::LIFETIME_HOURS),
            ]);

            // Destacar: en una colección existente o en una nueva.
            $highlight = null;
            if (! empty($data['highlight_id'])) {
                $highlight = StoryHighlight::find($data['highlight_id']);
            } elseif (filled($data['new_highlight'] ?? null)) {
                if ($business->highlights()->count() >= StoryHighlight::MAX_PER_BUSINESS) {
                    throw ValidationException::withMessages(['new_highlight' => 'Ya tienes el máximo de '.StoryHighlight::MAX_PER_BUSINESS.' destacadas.']);
                }
                $highlight = $business->highlights()->create([
                    'title' => $data['new_highlight'],
                    'position' => $business->highlights()->count(),
                ]);
            }

            $highlight?->stories()->attach($story->id, ['position' => $highlight->stories()->count()]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Historia publicada. Se verá durante 24 horas.']);

        return redirect()->route('business.publish', ['tab' => 'historia']);
    }

    public function destroyStory(Request $request, Story $story): RedirectResponse
    {
        $business = $this->businessOrFail($request, 'posts.manage');
        abort_unless($story->business_id === $business->id, 403);

        $story->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Historia eliminada.']);

        return back();
    }

    // -------------------------------------------------------- perfil del negocio

    public function updateAvatar(Request $request): RedirectResponse
    {
        return $this->replaceImage($request, 'avatar_path', [640, 640], 'Foto de perfil actualizada.');
    }

    public function updateCover(Request $request): RedirectResponse
    {
        return $this->replaceImage($request, 'cover_path', [1600, 640], 'Portada actualizada.');
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $business = $this->businessOrFail($request, 'profile.manage');

        $data = $request->validate([
            'intro' => ['required', 'string', 'min:10', 'max:160'],
            'about' => ['nullable', 'string', 'max:1500'],
        ]);

        $this->guard('intro', $data['intro'], $data['about'] ?? '');
        $business->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Presentación actualizada.']);

        return back();
    }

    private function replaceImage(Request $request, string $column, array $crop, string $message): RedirectResponse
    {
        $business = $this->businessOrFail($request, 'profile.manage');

        $request->validate([
            'image' => ['required', ...MediaUploader::IMAGE_RULES],
        ], [
            'image.required' => 'Elige una foto.',
            'image.mimes' => 'Usa una foto JPG, PNG o WEBP.',
            'image.max' => 'La foto puede pesar hasta 10 MB.',
        ]);

        $old = $business->{$column};
        $business->forceFill([$column => $this->uploader->storeImage($request->file('image'), "businesses/{$business->id}/profile", 1600, $crop)])->save();
        $this->uploader->delete($old);

        // La foto de perfil cuenta para completar el perfil (y arrancar la prueba).
        $business->refreshActivation();

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    // -------------------------------------------------------------- utilidades

    private function business(Request $request, string $ability): Business|RedirectResponse
    {
        $business = $request->user()->currentBusiness();

        if (! $business) {
            Inertia::flash('toast', ['type' => 'info', 'message' => 'Cambia a modo negocio para publicar.']);

            return redirect()->route('feed');
        }

        abort_unless($request->user()->canIn($business, $ability), 403, 'Tu rol no permite hacer esto.');

        return $business;
    }

    private function businessOrFail(Request $request, string $ability): Business
    {
        $business = $request->user()->currentBusiness();
        abort_unless($business && $request->user()->canIn($business, $ability), 403, 'Cambia a modo negocio para hacer esto.');

        return $business;
    }

    private function guard(string $field, string ...$texts): void
    {
        $result = $this->scanner->scan(...$texts);

        if ($result->blocks()) {
            throw ValidationException::withMessages([$field => $result->message()]);
        }
    }
}
