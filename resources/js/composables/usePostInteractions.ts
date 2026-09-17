import { usePage } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { toast } from 'vue-sonner';
import { ApiError, api } from '@/lib/http';
import { react as reactRoute } from '@/routes/posts';
import type { PostItem } from '@/types/feed';

/**
 * Estado compartido de reacciones y conteos por publicación.
 *
 * La misma publicación puede estar a la vez en el Inicio y abierta en el
 * diálogo: al reaccionar en uno, el otro se actualiza solo.
 */
type Counters = { reacted: boolean; reactions: number; comments: number };

const store = reactive<Record<number, Counters>>({});

export function usePostInteractions() {
    const page = usePage();

    function track(post: PostItem): Counters {
        if (!store[post.id]) {
            store[post.id] = {
                reacted: post.reacted,
                reactions: post.reactions,
                comments: post.comments,
            };
        }

        return store[post.id];
    }

    async function toggleReaction(post: PostItem): Promise<void> {
        if (!page.props.auth?.user) {
            toast.info('Inicia sesión para reaccionar.');

            return;
        }

        const state = track(post);
        const before = { ...state };

        // Respuesta inmediata en pantalla; el servidor confirma por detrás.
        state.reacted = !state.reacted;
        state.reactions += state.reacted ? 1 : -1;

        try {
            const res = await api<{ reacted: boolean; reactions: number }>(
                'POST',
                reactRoute(post.id).url,
            );
            state.reacted = res.reacted;
            state.reactions = res.reactions;
        } catch (error) {
            Object.assign(state, before);
            toast.error(
                error instanceof ApiError
                    ? error.message
                    : 'No se pudo guardar tu reacción.',
            );
        }
    }

    function setComments(postId: number, count: number): void {
        if (store[postId]) {
            store[postId].comments = count;
        }
    }

    return { track, toggleReaction, setComments };
}
