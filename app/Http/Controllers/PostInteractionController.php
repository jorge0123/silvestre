<?php

namespace App\Http\Controllers;

use App\Enums\ReportReason;
use App\Models\ContentFlag;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostReaction;
use App\Models\Report;
use App\Services\ContentScanner;
use App\Support\Present;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Reacciones y comentarios, en el mismo lugar donde se ve la publicación.
 * Responde JSON: la pantalla se actualiza sin cambiar de página, así el
 * Inicio no pierde la posición del scroll.
 */
class PostInteractionController extends Controller
{
    public function __construct(private ContentScanner $scanner) {}

    // ------------------------------------------------------------ reacciones

    public function react(Request $request, Post $post): JsonResponse
    {
        $user = $request->user();

        $reacted = DB::transaction(function () use ($post, $user) {
            $existing = PostReaction::where('post_id', $post->id)->where('user_id', $user->id)->lockForUpdate()->first();

            // Suma o resta uno, sin recontar toda la tabla en cada toque.
            if ($existing) {
                $existing->delete();
                $post->where('id', $post->id)->where('reactions_count', '>', 0)->decrement('reactions_count');
            } else {
                PostReaction::create(['post_id' => $post->id, 'user_id' => $user->id]);
                $post->where('id', $post->id)->increment('reactions_count');
            }

            return ! $existing;
        });

        return response()->json(['reacted' => $reacted, 'reactions' => $post->refresh()->reactions_count]);
    }

    // ----------------------------------------------------------- comentarios

    /** Se pueden leer sin cuenta: la publicación también se ve sin cuenta. */
    public function index(Request $request, Post $post): JsonResponse
    {
        $comments = $post->comments()
            ->whereNull('parent_id')
            ->with(['user', 'asBusiness', 'post', 'replies.user', 'replies.asBusiness', 'replies.post'])
            ->oldest()
            ->get();

        return response()->json([
            'comments' => $comments->map(fn (PostComment $c) => Present::comment($c, $request->user()))->values(),
            'count' => $post->comments_count,
            'canComment' => $request->user() !== null,
        ]);
    }

    public function store(Request $request, Post $post): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.PostComment::MAX_LENGTH],
            'parent_id' => ['nullable', 'integer', Rule::exists('post_comments', 'id')->where('post_id', $post->id)],
        ], [
            'body.required' => 'Escribe algo antes de enviar.',
        ]);

        $body = trim($data['body']);
        $result = $this->guard($body);
        $user = $request->user();

        // Responder a una respuesta la cuelga del comentario principal (un nivel).
        $parentId = $data['parent_id'] ?? null;
        if ($parentId) {
            $parent = PostComment::find($parentId);
            $parentId = $parent->parent_id ?? $parent->id;
        }

        // Si administra el negocio de la publicación y está en modo negocio,
        // comenta como el negocio.
        $asBusiness = $user->currentBusiness()?->id === $post->business_id ? $post->business_id : null;

        $comment = DB::transaction(function () use ($post, $user, $body, $parentId, $asBusiness) {
            $comment = $post->comments()->create([
                'user_id' => $user->id,
                'parent_id' => $parentId,
                'as_business_id' => $asBusiness,
                'body' => $body,
            ]);
            $post->increment('comments_count');

            return $comment;
        });

        if ($result->needsReview()) {
            ContentFlag::fromScan($comment, $post->business, $result);
        }

        $comment->load(['user', 'asBusiness', 'post', 'replies']);

        return response()->json([
            'comment' => Present::comment($comment, $user),
            'count' => $post->refresh()->comments_count,
        ], 201);
    }

    public function update(Request $request, PostComment $comment): JsonResponse
    {
        abort_unless($comment->isEditableBy($request->user()), 403, 'Solo puedes editar tus comentarios.');

        $data = $request->validate(['body' => ['required', 'string', 'max:'.PostComment::MAX_LENGTH]]);
        $body = trim($data['body']);
        $this->guard($body);

        if ($body !== $comment->body) {
            $comment->update(['body' => $body, 'edited_at' => now()]);
        }

        $comment->load(['user', 'asBusiness', 'post', 'replies.user', 'replies.asBusiness', 'replies.post']);

        return response()->json(['comment' => Present::comment($comment, $request->user())]);
    }

    public function destroy(Request $request, PostComment $comment): JsonResponse
    {
        $comment->load('post.business');
        abort_unless($comment->isDeletableBy($request->user()), 403, 'No puedes borrar este comentario.');

        $post = $comment->post;

        DB::transaction(function () use ($comment, $post) {
            // Borrar un comentario se lleva sus respuestas.
            $removed = 1 + $comment->replies()->count();
            $comment->replies()->delete();
            $comment->delete();
            $post->forceFill(['comments_count' => max(0, $post->comments_count - $removed)])->save();
        });

        return response()->json(['count' => $post->comments_count]);
    }

    public function report(Request $request, PostComment $comment): JsonResponse
    {
        abort_if($comment->user_id === $request->user()->id, 422, 'No puedes reportar tu propio comentario.');

        $data = $request->validate([
            'reason' => ['required', Rule::enum(ReportReason::class)],
            'detail' => ['nullable', 'string', 'max:500'],
        ]);

        Report::firstOrCreate(
            ['reporter_id' => $request->user()->id, 'reportable_type' => PostComment::class, 'reportable_id' => $comment->id],
            ['reason' => $data['reason'], 'detail' => $data['detail'] ?? null],
        );

        return response()->json(['message' => 'Gracias. Lo vamos a revisar.']);
    }

    /** Lo prohibido no se publica; el resto pasa y, si es dudoso, se revisa. */
    private function guard(string $body): \App\Services\ScanResult
    {
        $result = $this->scanner->scan($body);

        if ($result->blocks()) {
            throw ValidationException::withMessages(['body' => $result->message()]);
        }

        return $result;
    }
}
