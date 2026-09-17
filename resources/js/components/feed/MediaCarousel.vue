<script setup lang="ts">
import { ChevronLeft, ChevronRight, Play } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref, useTemplateRef } from 'vue';
import type { MediaItem } from '@/types/feed';

/**
 * Carrusel de fotos y videos.
 * Se desliza con el dedo (scroll-snap) y los videos se reproducen solos, sin
 * sonido, solo mientras están en pantalla: así no consumen datos de más.
 */
const props = withDefaults(
    defineProps<{
        media: MediaItem[];
        aspect?: string;
        controls?: boolean;
        fit?: 'cover' | 'contain';
    }>(),
    { aspect: 'aspect-square', controls: false, fit: 'cover' },
);

const emit = defineEmits<{ tap: []; doubleTap: [] }>();

const track = useTemplateRef<HTMLDivElement>('track');
const current = ref(0);
let observer: IntersectionObserver | null = null;
let lastTap = 0;
let tapTimer = 0;

function onScroll(): void {
    const el = track.value;
    if (el) {
        current.value = Math.round(el.scrollLeft / el.clientWidth);
    }
}

function go(delta: number): void {
    const el = track.value;
    if (el) {
        el.scrollTo({ left: (current.value + delta) * el.clientWidth, behavior: 'smooth' });
    }
}

/** Un toque abre; dos toques rápidos dan "Me gusta" (como en Instagram). */
function onTap(event: MouseEvent): void {
    if ((event.target as HTMLElement).closest('button, video[controls]')) {
        return;
    }
    const now = Date.now();
    if (now - lastTap < 280) {
        clearTimeout(tapTimer);
        lastTap = 0;
        emit('doubleTap');
        return;
    }
    lastTap = now;
    tapTimer = window.setTimeout(() => emit('tap'), 280);
}

onMounted(() => {
    observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                const video = entry.target as HTMLVideoElement;
                if (entry.isIntersecting && entry.intersectionRatio > 0.6) {
                    void video.play().catch(() => {});
                } else {
                    video.pause();
                }
            }
        },
        { threshold: [0, 0.6, 1] },
    );
    track.value?.querySelectorAll('video').forEach((v) => observer?.observe(v));
});

onBeforeUnmount(() => {
    observer?.disconnect();
    clearTimeout(tapTimer);
});
</script>

<template>
    <div class="group/carousel relative w-full overflow-hidden" :class="fit === 'contain' ? 'h-full bg-black' : 'bg-muted'">
        <div
            ref="track"
            class="flex snap-x snap-mandatory overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
            :class="aspect"
            @scroll.passive="onScroll"
            @click="onTap"
        >
            <div v-for="(item, i) in props.media" :key="i" class="relative h-full w-full flex-none snap-center">
                <video
                    v-if="item.type === 'video'"
                    :src="item.url"
                    :poster="item.poster ?? undefined"
                    class="size-full"
                    :class="fit === 'contain' ? 'object-contain' : 'object-cover'"
                    muted
                    loop
                    playsinline
                    preload="metadata"
                    :controls="controls"
                />
                <img
                    v-else
                    :src="item.url"
                    alt=""
                    class="size-full select-none"
                    :class="fit === 'contain' ? 'object-contain' : 'object-cover'"
                    loading="lazy"
                    decoding="async"
                    draggable="false"
                />
                <span
                    v-if="item.type === 'video' && !controls"
                    class="pointer-events-none absolute top-3 left-3 grid size-7 place-items-center rounded-full bg-black/55 text-white"
                    aria-label="Video"
                >
                    <Play class="size-3.5 fill-current" />
                </span>
            </div>
        </div>

        <slot />

        <template v-if="media.length > 1">
            <span class="pointer-events-none absolute top-3 right-3 rounded-full bg-black/55 px-2 py-0.5 text-xs font-bold text-white tabular-nums">
                {{ current + 1 }}/{{ media.length }}
            </span>

            <button
                v-if="current > 0"
                type="button"
                class="absolute top-1/2 left-2 hidden size-9 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-black opacity-0 shadow transition-opacity group-hover/carousel:opacity-100 focus-visible:opacity-100 md:grid"
                aria-label="Anterior"
                @click.stop="go(-1)"
            >
                <ChevronLeft class="size-5" />
            </button>
            <button
                v-if="current < media.length - 1"
                type="button"
                class="absolute top-1/2 right-2 hidden size-9 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-black opacity-0 shadow transition-opacity group-hover/carousel:opacity-100 focus-visible:opacity-100 md:grid"
                aria-label="Siguiente"
                @click.stop="go(1)"
            >
                <ChevronRight class="size-5" />
            </button>

            <div class="pointer-events-none absolute inset-x-0 bottom-2.5 flex justify-center gap-1.5" aria-hidden="true">
                <span
                    v-for="(_, i) in media"
                    :key="i"
                    class="h-1.5 rounded-full bg-white shadow transition-all"
                    :class="i === current ? 'w-4 opacity-100' : 'w-1.5 opacity-60'"
                />
            </div>
        </template>
    </div>
</template>
