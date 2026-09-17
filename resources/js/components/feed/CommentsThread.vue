<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { MessageCircle, SendHorizontal, X } from '@lucide/vue';
import { computed, nextTick, onMounted, ref, useTemplateRef } from 'vue';
import { toast } from 'vue-sonner';
import BusinessAvatar from '@/components/feed/BusinessAvatar.vue';
import CommentRow from '@/components/feed/CommentRow.vue';
import { Spinner } from '@/components/ui/spinner';
import { ApiError, api } from '@/lib/http';
import { login } from '@/routes';
import { index as commentsIndex, store as commentsStore } from '@/routes/posts/comments';
import type { CommentItem } from '@/types/feed';

/**
 * Hilo de comentarios de una publicación, con su caja para escribir.
 * La caja queda pegada abajo, como en cualquier app de mensajes.
 */
const props = defineProps<{ postId: number; autofocus?: boolean }>();
const emit = defineEmits<{ count: [count: number] }>();

const page = usePage();
const user = computed(() => page.props.auth?.user);

// "Ana P.", igual que se muestra en los comentarios, para que las iniciales coincidan.
const shortName = computed(() => {
    const parts = String(user.value?.name ?? '').trim().split(/\s+/);

    return parts.length > 1 ? `${parts[0]} ${parts[parts.length - 1][0]}.` : parts[0];
});

const comments = ref<CommentItem[]>([]);
const loading = ref(true);
const loadError = ref(false);
const body = ref('');
const sending = ref(false);
const replyingTo = ref<CommentItem | null>(null);
const composer = useTemplateRef<HTMLTextAreaElement>('composer');
const list = useTemplateRef<HTMLDivElement>('list');

const MAX = 1000;

async function load(): Promise<void> {
    loading.value = true;
    loadError.value = false;
    try {
        const res = await api<{ comments: CommentItem[]; count: number }>('GET', commentsIndex(props.postId).url);
        comments.value = res.comments;
        emit('count', res.count);
    } catch {
        loadError.value = true;
    } finally {
        loading.value = false;
    }
}

function autosize(): void {
    const el = composer.value;
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = `${Math.min(el.scrollHeight, 140)}px`;
}

async function startReply(comment: CommentItem): Promise<void> {
    replyingTo.value = comment;
    await nextTick();
    composer.value?.focus();
}

async function send(): Promise<void> {
    const text = body.value.trim();
    if (!text || sending.value) return;

    sending.value = true;
    try {
        const res = await api<{ comment: CommentItem; count: number }>('POST', commentsStore(props.postId).url, {
            body: text,
            parent_id: replyingTo.value?.id ?? null,
        });

        const created = res.comment;
        if (created.parentId) {
            comments.value.find((c) => c.id === created.parentId)?.replies.push(created);
        } else {
            comments.value.push(created);
        }

        body.value = '';
        replyingTo.value = null;
        emit('count', res.count);

        await nextTick();
        autosize();
        document.getElementById(`comment-${created.id}`)?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } catch (error) {
        toast.error(error instanceof ApiError ? error.firstError() : 'No se pudo publicar tu comentario.');
    } finally {
        sending.value = false;
    }
}

function onKey(event: KeyboardEvent): void {
    // Enter envía; Shift+Enter hace salto de línea. En el celular, el botón.
    if (event.key === 'Enter' && !event.shiftKey && window.matchMedia('(hover: hover)').matches) {
        event.preventDefault();
        void send();
    }
    if (event.key === 'Escape' && replyingTo.value) {
        replyingTo.value = null;
    }
}

function onUpdated(updated: CommentItem): void {
    for (const c of comments.value) {
        if (c.id === updated.id) {
            Object.assign(c, updated);
            return;
        }
        const reply = c.replies.find((r) => r.id === updated.id);
        if (reply) {
            Object.assign(reply, updated);
            return;
        }
    }
}

function onDeleted({ id, count }: { id: number; count: number }): void {
    comments.value = comments.value
        .filter((c) => c.id !== id)
        .map((c) => ({ ...c, replies: c.replies.filter((r) => r.id !== id) }));
    emit('count', count);
}

onMounted(async () => {
    await load();
    if (props.autofocus && user.value) {
        composer.value?.focus();
    }
});

defineExpose({ focus: () => composer.value?.focus() });
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col">
        <div ref="list" class="flex-1 px-4 py-3">
            <!-- Cargando -->
            <div v-if="loading" class="flex flex-col gap-4" aria-busy="true" aria-label="Cargando comentarios">
                <div v-for="n in 3" :key="n" class="flex gap-2.5">
                    <span class="bg-muted size-9 flex-none animate-pulse rounded-full" />
                    <span class="bg-muted h-14 animate-pulse rounded-2xl" :style="{ width: `${50 + n * 12}%` }" />
                </div>
            </div>

            <div v-else-if="loadError" class="text-muted-foreground py-8 text-center text-sm">
                No se pudieron cargar los comentarios.
                <button type="button" class="text-primary font-bold" @click="load">Reintentar</button>
            </div>

            <div v-else-if="!comments.length" class="text-muted-foreground flex flex-col items-center gap-2 py-8 text-center text-sm">
                <MessageCircle class="size-8 opacity-40" />
                <p>Todavía no hay comentarios.<br />{{ user ? '¡Sé la primera persona en comentar!' : '' }}</p>
            </div>

            <TransitionGroup
                v-else
                tag="ul"
                class="flex flex-col gap-4"
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="opacity-0 translate-y-2"
                leave-active-class="transition duration-200 ease-in"
                leave-to-class="opacity-0"
            >
                <li v-for="comment in comments" :id="`comment-${comment.id}`" :key="comment.id">
                    <CommentRow
                        :comment="comment"
                        :can-reply="!!user"
                        @reply="startReply"
                        @updated="onUpdated"
                        @deleted="onDeleted"
                    >
                        <TransitionGroup
                            v-if="comment.replies.length"
                            tag="ul"
                            class="mt-3 flex flex-col gap-3"
                            enter-active-class="transition duration-300 ease-out"
                            enter-from-class="opacity-0 translate-y-2"
                            leave-active-class="transition duration-200 ease-in"
                            leave-to-class="opacity-0"
                        >
                            <li v-for="reply in comment.replies" :id="`comment-${reply.id}`" :key="reply.id">
                                <CommentRow
                                    :comment="reply"
                                    is-reply
                                    :can-reply="!!user"
                                    @reply="() => startReply(comment)"
                                    @updated="onUpdated"
                                    @deleted="onDeleted"
                                />
                            </li>
                        </TransitionGroup>
                    </CommentRow>
                </li>
            </TransitionGroup>
        </div>

        <!-- Caja para escribir: pegada abajo -->
        <div class="bg-background border-border sticky bottom-0 border-t px-3 pt-2 pb-[calc(0.5rem+env(safe-area-inset-bottom))]">
            <template v-if="user">
                <div v-if="replyingTo" class="text-muted-foreground mb-1.5 flex items-center gap-2 px-1 text-xs">
                    Respondiendo a <strong class="text-foreground">{{ replyingTo.author.name }}</strong>
                    <button type="button" class="hover:bg-muted ml-auto grid size-6 place-items-center rounded-full" aria-label="Cancelar respuesta" @click="replyingTo = null">
                        <X class="size-3.5" />
                    </button>
                </div>
                <div class="flex items-end gap-2">
                    <BusinessAvatar :src="null" :name="shortName" size="sm" class="mb-0.5 hidden sm:inline-grid" />
                    <div class="bg-muted focus-within:ring-ring/40 flex flex-1 items-end rounded-3xl pr-1 focus-within:ring-2">
                        <textarea
                            ref="composer"
                            v-model="body"
                            rows="1"
                            :maxlength="MAX"
                            :placeholder="replyingTo ? 'Escribe tu respuesta…' : 'Escribe un comentario…'"
                            class="max-h-36 min-h-11 flex-1 resize-none bg-transparent px-4 py-3 text-[15px] leading-snug outline-none"
                            aria-label="Escribe un comentario"
                            enterkeyhint="send"
                            @input="autosize"
                            @keydown="onKey"
                        />
                        <button
                            type="submit"
                            class="bg-primary text-primary-foreground mb-1 grid size-9 flex-none place-items-center rounded-full transition-all disabled:scale-90 disabled:opacity-30"
                            :disabled="!body.trim() || sending"
                            aria-label="Enviar comentario"
                            @click="send"
                        >
                            <Spinner v-if="sending" class="size-4" />
                            <SendHorizontal v-else class="size-4" />
                        </button>
                    </div>
                </div>
                <p v-if="body.length > MAX - 100" class="text-muted-foreground mt-1 pr-2 text-right text-xs tabular-nums">
                    {{ body.length }}/{{ MAX }}
                </p>
            </template>
            <p v-else class="text-muted-foreground py-2 text-center text-sm">
                <Link :href="login()" class="text-primary font-bold">Inicia sesión</Link> para comentar y reaccionar.
            </p>
        </div>
    </div>
</template>
