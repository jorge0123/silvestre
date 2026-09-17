<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { BadgeCheck, Heart, Megaphone, Sparkles } from '@lucide/vue';
import { computed, ref } from 'vue';
import BusinessAvatar from '@/components/feed/BusinessAvatar.vue';
import FollowButton from '@/components/feed/FollowButton.vue';
import MediaCarousel from '@/components/feed/MediaCarousel.vue';
import ReactionBar from '@/components/feed/ReactionBar.vue';
import { usePostInteractions } from '@/composables/usePostInteractions';
import { show as businessShow } from '@/routes/business';
import type { PostItem, PostVariant } from '@/types/feed';

/**
 * Una publicación en el Inicio.
 *  - Tocar la foto o "Comentar" la abre ahí mismo, sin cambiar de página.
 *  - Dos toques en la foto dan "Me gusta".
 *  - Si es publicidad lo dice claro: "Promocionado" nunca se oculta.
 */
const props = withDefaults(
    defineProps<{ post: PostItem; variant?: PostVariant }>(),
    { variant: 'following' },
);
const emit = defineEmits<{ open: [focusComments: boolean] }>();

const { track, toggleReaction } = usePostInteractions();
const state = track(props.post);

const expanded = ref(false);
const whyOpen = ref(false);
const burst = ref(false);

const longText = computed(
    () =>
        props.post.body.length > 180 || props.post.body.split('\n').length > 3,
);
const profileHref = computed(() => businessShow(props.post.business.slug));

function doubleTapLike(): void {
    burst.value = true;
    setTimeout(() => (burst.value = false), 700);
    if (!state.reacted) {
        void toggleReaction(props.post);
    }
}
</script>

<template>
    <article
        class="bg-card overflow-hidden border-y sm:rounded-2xl sm:border"
        :class="variant === 'sponsored' ? 'sm:border-primary/25' : ''"
    >
        <header class="flex items-center gap-3 px-4 pt-3 pb-2.5">
            <Link :href="profileHref" class="flex-none">
                <BusinessAvatar
                    :src="post.business.avatar"
                    :name="post.business.name"
                />
            </Link>
            <div class="min-w-0 flex-1">
                <Link
                    :href="profileHref"
                    class="flex items-center gap-1 font-bold hover:underline"
                >
                    <span class="truncate">{{ post.business.name }}</span>
                    <BadgeCheck
                        v-if="post.business.verified"
                        class="text-brand size-4 flex-none"
                        aria-label="Verificado"
                    />
                </Link>
                <p
                    class="text-muted-foreground flex items-center gap-1 truncate text-xs"
                >
                    <template v-if="variant === 'sponsored'">
                        <button
                            type="button"
                            class="text-primary inline-flex items-center gap-1 font-bold"
                            :aria-expanded="whyOpen"
                            @click="whyOpen = !whyOpen"
                        >
                            <Megaphone class="size-3" /> Promocionado
                        </button>
                        <span>· {{ post.business.category }}</span>
                    </template>
                    <template v-else-if="variant === 'discovery'">
                        <span
                            class="text-brand inline-flex items-center gap-1 font-bold"
                            ><Sparkles class="size-3" /> Sugerido</span
                        >
                        <span>· {{ post.business.zone }} · {{ post.ago }}</span>
                    </template>
                    <template v-else
                        >{{ post.business.zone }} · {{ post.ago }}</template
                    >
                </p>
            </div>
            <FollowButton
                v-if="variant !== 'following'"
                :slug="post.business.slug"
                :following="false"
                size="sm"
            />
        </header>

        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 -translate-y-1"
        >
            <p
                v-if="variant === 'sponsored' && whyOpen"
                class="bg-primary/[0.06] text-foreground/80 mx-4 mb-2.5 rounded-lg px-3 py-2 text-xs leading-relaxed"
            >
                Ves esto porque <strong>{{ post.business.name }}</strong> tiene
                plan Pro. Pagar da visibilidad, nunca estrellas: su calificación
                es la que le dieron sus clientes.
            </p>
        </Transition>

        <div class="px-4 pb-3">
            <button
                type="button"
                class="font-display block text-left text-[17px] leading-snug font-bold"
                @click="emit('open', false)"
            >
                {{ post.title }}
            </button>
            <p
                v-if="post.body"
                class="text-foreground/85 mt-1 text-[15px] leading-relaxed whitespace-pre-line"
                :class="expanded ? '' : 'line-clamp-3'"
            >
                {{ post.body }}
            </p>
            <button
                v-if="longText && !expanded"
                type="button"
                class="text-muted-foreground mt-0.5 text-sm font-bold"
                @click="expanded = true"
            >
                Ver más
            </button>
        </div>

        <MediaCarousel
            v-if="post.media.length"
            :media="post.media"
            aspect="aspect-[4/5] sm:aspect-square"
            class="cursor-pointer"
            @tap="emit('open', false)"
            @double-tap="doubleTapLike"
        >
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="scale-50 opacity-0"
                leave-active-class="transition duration-300 ease-in"
                leave-to-class="scale-125 opacity-0"
            >
                <span
                    v-if="burst"
                    class="pointer-events-none absolute inset-0 grid place-items-center"
                    aria-hidden="true"
                >
                    <Heart
                        class="size-24 fill-white text-white drop-shadow-[0_4px_24px_rgba(0,0,0,.35)]"
                    />
                </span>
            </Transition>
        </MediaCarousel>

        <ReactionBar :post="post" @comment="emit('open', true)" />

        <Link
            v-if="variant === 'sponsored'"
            :href="profileHref"
            class="bg-primary/[0.06] text-primary hover:bg-primary/10 flex items-center justify-between px-4 py-3 text-sm font-bold"
        >
            Visitar {{ post.business.name }} <span aria-hidden="true">→</span>
        </Link>
    </article>
</template>
