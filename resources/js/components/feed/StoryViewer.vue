<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Pause, Play, Volume2, VolumeX, X } from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, ref, useTemplateRef, watch } from 'vue';
import BusinessAvatar from '@/components/feed/BusinessAvatar.vue';
import { show as businessShow } from '@/routes/business';
import type { StoryGroup } from '@/types/feed';

/**
 * Visor de historias a pantalla completa.
 *  - Toca a la derecha para avanzar, a la izquierda para regresar.
 *  - Mantén presionado para pausar.
 *  - Las fotos duran 5 segundos; los videos, lo que dure el video.
 *  - Al terminar las de un negocio, pasa al siguiente.
 */
const props = defineProps<{ groups: StoryGroup[]; startGroup: number }>();
const open = defineModel<boolean>('open', { required: true });

const IMAGE_MS = 5000;

const gi = ref(props.startGroup);
const si = ref(0);
const progress = ref(0);
const paused = ref(false);
const muted = ref(true);
const video = useTemplateRef<HTMLVideoElement>('video');

const group = computed(() => props.groups[gi.value]);
const story = computed(() => group.value?.stories[si.value]);

let raf = 0;
let startedAt = 0;
let elapsedBeforePause = 0;
let pressTimer = 0;
let longPress = false;

function tick(now: number): void {
    if (!story.value || story.value.type === 'video') {
        return;
    }
    if (!paused.value) {
        progress.value = Math.min(1, (elapsedBeforePause + now - startedAt) / IMAGE_MS);
        if (progress.value >= 1) {
            next();
            return;
        }
    }
    raf = requestAnimationFrame(tick);
}

function start(): void {
    cancelAnimationFrame(raf);
    progress.value = 0;
    elapsedBeforePause = 0;
    startedAt = performance.now();
    paused.value = false;

    if (story.value?.type === 'image') {
        raf = requestAnimationFrame(tick);
    } else {
        void nextTick(() => {
            if (video.value) {
                video.value.currentTime = 0;
                video.value.muted = muted.value;
                void video.value.play().catch(() => {});
            }
        });
    }
}

function next(): void {
    if (!group.value) {
        return;
    }
    if (si.value < group.value.stories.length - 1) {
        si.value++;
    } else if (gi.value < props.groups.length - 1) {
        gi.value++;
        si.value = 0;
    } else {
        close();
        return;
    }
    start();
}

function prev(): void {
    if (si.value > 0) {
        si.value--;
    } else if (gi.value > 0) {
        gi.value--;
        si.value = props.groups[gi.value].stories.length - 1;
    }
    start();
}

function setPaused(value: boolean): void {
    if (value === paused.value) {
        return;
    }
    paused.value = value;

    if (story.value?.type === 'video') {
        if (value) {
            video.value?.pause();
        } else {
            void video.value?.play().catch(() => {});
        }
    } else if (value) {
        elapsedBeforePause += performance.now() - startedAt;
    } else {
        startedAt = performance.now();
        cancelAnimationFrame(raf);
        raf = requestAnimationFrame(tick);
    }
}

function onVideoTime(): void {
    const v = video.value;
    if (v && v.duration) {
        progress.value = v.currentTime / v.duration;
    }
}

function onPointerDown(): void {
    longPress = false;
    pressTimer = window.setTimeout(() => {
        longPress = true;
        setPaused(true);
    }, 220);
}

function onPointerUp(side: 'left' | 'right'): void {
    clearTimeout(pressTimer);
    if (longPress) {
        setPaused(false);
        return;
    }
    if (side === 'left') {
        prev();
    } else {
        next();
    }
}

function toggleMute(): void {
    muted.value = !muted.value;
    if (video.value) {
        video.value.muted = muted.value;
    }
}

function close(): void {
    cancelAnimationFrame(raf);
    open.value = false;
}

function onKey(e: KeyboardEvent): void {
    if (e.key === 'Escape') close();
    if (e.key === 'ArrowRight') next();
    if (e.key === 'ArrowLeft') prev();
    if (e.key === ' ') {
        e.preventDefault();
        setPaused(!paused.value);
    }
}

watch(
    open,
    (isOpen) => {
        if (isOpen) {
            gi.value = props.startGroup;
            si.value = 0;
            document.body.style.overflow = 'hidden';
            window.addEventListener('keydown', onKey);
            start();
        } else {
            document.body.style.overflow = '';
            window.removeEventListener('keydown', onKey);
            cancelAnimationFrame(raf);
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    document.body.style.overflow = '';
    window.removeEventListener('keydown', onKey);
    cancelAnimationFrame(raf);
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 scale-[0.98]"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open && group && story"
                class="fixed inset-0 z-[70] flex items-center justify-center bg-black/95"
                role="dialog"
                aria-modal="true"
                :aria-label="`Historias de ${group.business.name}`"
            >
                <button
                    v-if="gi > 0"
                    type="button"
                    class="absolute left-4 hidden size-11 place-items-center rounded-full bg-white/15 text-white hover:bg-white/25 md:grid"
                    aria-label="Negocio anterior"
                    @click="gi--; si = 0; start()"
                >
                    <ChevronLeft class="size-6" />
                </button>

                <div class="relative h-full w-full overflow-hidden bg-black md:h-[92vh] md:w-auto md:aspect-[9/16] md:rounded-2xl">
                    <!-- Contenido -->
                    <video
                        v-if="story.type === 'video'"
                        ref="video"
                        :key="`v-${story.id}`"
                        :src="story.url"
                        :poster="story.poster ?? undefined"
                        class="absolute inset-0 size-full object-cover"
                        playsinline
                        :muted="muted"
                        @timeupdate="onVideoTime"
                        @ended="next"
                    />
                    <img
                        v-else
                        :key="`i-${story.id}`"
                        :src="story.url"
                        alt=""
                        class="absolute inset-0 size-full object-cover"
                    />

                    <div class="pointer-events-none absolute inset-x-0 top-0 h-32 bg-gradient-to-b from-black/70 to-transparent" />
                    <div class="pointer-events-none absolute inset-x-0 bottom-0 h-48 bg-gradient-to-t from-black/80 to-transparent" />

                    <!-- Zonas para tocar -->
                    <div class="absolute inset-0 flex">
                        <button
                            type="button"
                            class="h-full w-1/3"
                            aria-label="Historia anterior"
                            @pointerdown="onPointerDown"
                            @pointerup="onPointerUp('left')"
                            @pointerleave="setPaused(false)"
                        />
                        <button
                            type="button"
                            class="h-full w-2/3"
                            aria-label="Siguiente historia"
                            @pointerdown="onPointerDown"
                            @pointerup="onPointerUp('right')"
                            @pointerleave="setPaused(false)"
                        />
                    </div>

                    <!-- Barras de progreso -->
                    <div class="absolute inset-x-3 top-3 flex gap-1" aria-hidden="true">
                        <span v-for="(s, i) in group.stories" :key="s.id" class="h-[3px] flex-1 overflow-hidden rounded-full bg-white/35">
                            <span
                                class="block h-full rounded-full bg-white"
                                :style="{ width: i < si ? '100%' : i === si ? `${progress * 100}%` : '0%' }"
                            />
                        </span>
                    </div>

                    <!-- Encabezado -->
                    <div class="absolute inset-x-3 top-6 flex items-center gap-2.5 text-white">
                        <Link :href="businessShow(group.business.slug)" class="flex min-w-0 items-center gap-2.5" @click="close">
                            <BusinessAvatar :src="group.business.avatar" :name="group.business.name" size="sm" />
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-bold">{{ group.business.name }}</span>
                                <span class="block text-xs text-white/70">{{ story.ago }}</span>
                            </span>
                        </Link>
                        <span class="ml-auto flex items-center gap-1">
                            <button type="button" class="grid size-9 place-items-center rounded-full hover:bg-white/15" :aria-label="paused ? 'Reanudar' : 'Pausar'" @click="setPaused(!paused)">
                                <Play v-if="paused" class="size-5 fill-current" />
                                <Pause v-else class="size-5 fill-current" />
                            </button>
                            <button v-if="story.type === 'video'" type="button" class="grid size-9 place-items-center rounded-full hover:bg-white/15" :aria-label="muted ? 'Activar sonido' : 'Silenciar'" @click="toggleMute">
                                <VolumeX v-if="muted" class="size-5" />
                                <Volume2 v-else class="size-5" />
                            </button>
                            <button type="button" class="grid size-9 place-items-center rounded-full hover:bg-white/15" aria-label="Cerrar historias" @click="close">
                                <X class="size-6" />
                            </button>
                        </span>
                    </div>

                    <!-- Texto y acción -->
                    <div class="absolute inset-x-4 bottom-6 text-white">
                        <p v-if="story.caption" class="font-display text-xl leading-snug font-bold [text-shadow:0_1px_12px_rgba(0,0,0,.5)]">
                            {{ story.caption }}
                        </p>
                        <Link
                            :href="businessShow(group.business.slug)"
                            class="mt-4 inline-flex h-10 items-center rounded-full bg-white px-5 text-sm font-bold text-black"
                            @click="close"
                        >
                            Ver negocio
                        </Link>
                    </div>
                </div>

                <button
                    v-if="gi < groups.length - 1"
                    type="button"
                    class="absolute right-4 hidden size-11 place-items-center rounded-full bg-white/15 text-white hover:bg-white/25 md:grid"
                    aria-label="Siguiente negocio"
                    @click="gi++; si = 0; start()"
                >
                    <ChevronRight class="size-6" />
                </button>
            </div>
        </Transition>
    </Teleport>
</template>
