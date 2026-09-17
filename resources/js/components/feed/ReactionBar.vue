<script setup lang="ts">
import { Heart, MessageCircle, Share2 } from '@lucide/vue';
import { ref } from 'vue';
import { usePostInteractions } from '@/composables/usePostInteractions';
import { shareLink } from '@/lib/share';
import { show as postShow } from '@/routes/posts';
import type { PostItem } from '@/types/feed';

/** Conteos y botones de Me gusta, Comentar y Compartir. */
const props = defineProps<{ post: PostItem }>();
const emit = defineEmits<{ comment: [] }>();

const { track, toggleReaction } = usePostInteractions();
const state = track(props.post);
const pop = ref(false);

function like(): void {
    if (!state.reacted) {
        pop.value = true;
        setTimeout(() => (pop.value = false), 400);
    }
    void toggleReaction(props.post);
}

function share(): void {
    void shareLink(
        postShow({ business: props.post.business.slug, post: props.post.id })
            .url,
        props.post.title,
    );
}
</script>

<template>
    <div>
        <div
            v-if="state.reactions || state.comments"
            class="text-muted-foreground flex items-center justify-between px-4 py-2 text-sm"
        >
            <span
                v-if="state.reactions"
                class="inline-flex items-center gap-1.5 tabular-nums"
            >
                <span
                    class="bg-primary grid size-[18px] place-items-center rounded-full text-white"
                >
                    <Heart class="size-2.5 fill-current" />
                </span>
                {{ state.reactions }}
            </span>
            <span v-else />
            <button
                v-if="state.comments"
                type="button"
                class="tabular-nums hover:underline"
                @click="emit('comment')"
            >
                {{ state.comments }}
                {{ state.comments === 1 ? 'comentario' : 'comentarios' }}
            </button>
        </div>

        <div class="border-border mx-3 grid grid-cols-3 border-t py-1">
            <button
                type="button"
                class="hover:bg-muted flex h-10 items-center justify-center gap-2 rounded-lg text-sm font-bold transition-colors"
                :class="
                    state.reacted ? 'text-primary' : 'text-muted-foreground'
                "
                :aria-pressed="state.reacted"
                @click="like"
            >
                <Heart
                    class="size-5 transition-transform"
                    :class="[
                        state.reacted ? 'fill-current' : '',
                        pop ? 'scale-125' : 'scale-100',
                    ]"
                />
                Me gusta
            </button>
            <button
                type="button"
                class="text-muted-foreground hover:bg-muted flex h-10 items-center justify-center gap-2 rounded-lg text-sm font-bold"
                @click="emit('comment')"
            >
                <MessageCircle class="size-5" /> Comentar
            </button>
            <button
                type="button"
                class="text-muted-foreground hover:bg-muted flex h-10 items-center justify-center gap-2 rounded-lg text-sm font-bold"
                @click="share"
            >
                <Share2 class="size-5" /> Compartir
            </button>
        </div>
    </div>
</template>
