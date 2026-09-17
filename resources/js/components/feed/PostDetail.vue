<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { BadgeCheck, X } from '@lucide/vue';
import { useTemplateRef } from 'vue';
import BusinessAvatar from '@/components/feed/BusinessAvatar.vue';
import CommentsThread from '@/components/feed/CommentsThread.vue';
import MediaCarousel from '@/components/feed/MediaCarousel.vue';
import ReactionBar from '@/components/feed/ReactionBar.vue';
import { usePostInteractions } from '@/composables/usePostInteractions';
import { show as businessShow } from '@/routes/business';
import type { PostItem } from '@/types/feed';

/**
 * Una publicación completa con sus comentarios. Se usa dentro del diálogo del
 * Inicio y en la página propia de la publicación (el enlace que se comparte).
 */
const props = withDefaults(
    defineProps<{
        post: PostItem;
        focusComments?: boolean;
        closable?: boolean;
        contained?: boolean;
    }>(),
    { contained: true },
);
const emit = defineEmits<{ close: [] }>();

const { setComments } = usePostInteractions();
const thread = useTemplateRef<InstanceType<typeof CommentsThread>>('thread');
</script>

<template>
    <div
        class="bg-background flex flex-col md:grid md:h-full md:min-h-0 md:overflow-hidden"
        :class="[
            post.media.length
                ? 'md:grid-cols-[minmax(0,1.25fr)_minmax(360px,1fr)]'
                : 'md:grid-cols-1',
            contained ? 'h-full min-h-0 overflow-hidden' : '',
        ]"
    >
        <!-- Fotos y videos: en computadora, columna oscura a la izquierda -->
        <div
            v-if="post.media.length"
            class="hidden bg-black md:flex md:items-center"
        >
            <MediaCarousel
                :media="post.media"
                aspect="h-full w-full"
                controls
                fit="contain"
            />
        </div>

        <div class="flex min-h-0 flex-1 flex-col">
            <header
                class="border-border flex flex-none items-center gap-3 border-b px-4 py-3"
            >
                <Link
                    :href="businessShow(post.business.slug)"
                    class="flex min-w-0 flex-1 items-center gap-3"
                    @click="emit('close')"
                >
                    <BusinessAvatar
                        :src="post.business.avatar"
                        :name="post.business.name"
                    />
                    <span class="min-w-0">
                        <span class="flex items-center gap-1 font-bold">
                            <span class="truncate">{{
                                post.business.name
                            }}</span>
                            <BadgeCheck
                                v-if="post.business.verified"
                                class="text-brand size-4 flex-none"
                            />
                        </span>
                        <span
                            class="text-muted-foreground block truncate text-xs"
                            >{{ post.business.zone }} · {{ post.ago }}</span
                        >
                    </span>
                </Link>
                <button
                    v-if="closable"
                    type="button"
                    class="hover:bg-muted grid size-10 flex-none place-items-center rounded-full"
                    aria-label="Cerrar publicación"
                    @click="emit('close')"
                >
                    <X class="size-5" />
                </button>
            </header>

            <!-- Todo lo de abajo se desplaza junto; la caja de comentarios queda fija -->
            <div
                class="md:flex md:min-h-0 md:flex-1 md:flex-col md:overflow-y-auto md:overscroll-contain"
                :class="
                    contained
                        ? 'flex min-h-0 flex-1 flex-col overflow-y-auto overscroll-contain'
                        : ''
                "
            >
                <div class="flex-none px-4 pt-3 pb-2">
                    <h2 class="font-display text-xl leading-snug font-bold">
                        {{ post.title }}
                    </h2>
                    <p
                        v-if="post.body"
                        class="text-foreground/90 mt-1.5 text-[15px] leading-relaxed whitespace-pre-line"
                    >
                        {{ post.body }}
                    </p>
                </div>

                <MediaCarousel
                    v-if="post.media.length"
                    class="flex-none md:hidden"
                    :media="post.media"
                    aspect="aspect-square"
                    controls
                />

                <ReactionBar
                    class="flex-none"
                    :post="post"
                    @comment="thread?.focus()"
                />

                <CommentsThread
                    ref="thread"
                    :post-id="post.id"
                    :autofocus="focusComments"
                    class="border-border border-t"
                    @count="(n) => setComments(post.id, n)"
                />
            </div>
        </div>
    </div>
</template>
