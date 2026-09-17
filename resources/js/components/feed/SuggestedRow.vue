<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { ref, useTemplateRef } from 'vue';
import BusinessSuggestCard from '@/components/feed/BusinessSuggestCard.vue';
import type { BusinessCard } from '@/types/feed';

/** Fila de negocios sugeridos que aparece entre publicaciones. */
defineProps<{ title: string; businesses: BusinessCard[] }>();

const track = useTemplateRef<HTMLDivElement>('track');
const atStart = ref(true);
const atEnd = ref(false);

function onScroll(): void {
    const el = track.value;
    if (!el) return;
    atStart.value = el.scrollLeft < 8;
    atEnd.value = el.scrollLeft + el.clientWidth >= el.scrollWidth - 8;
}

function scrollBy(direction: number): void {
    track.value?.scrollBy({ left: direction * 240, behavior: 'smooth' });
}
</script>

<template>
    <section
        class="bg-card border-y py-4 sm:rounded-2xl sm:border"
        :aria-label="title"
    >
        <div class="mb-3 flex items-center gap-2 px-4">
            <h2 class="font-display flex-1 text-[17px] font-bold">
                {{ title }}
            </h2>
            <button
                type="button"
                class="hover:bg-muted hidden size-8 place-items-center rounded-full disabled:opacity-30 sm:grid"
                :disabled="atStart"
                aria-label="Ver anteriores"
                @click="scrollBy(-1)"
            >
                <ChevronLeft class="size-4" />
            </button>
            <button
                type="button"
                class="hover:bg-muted hidden size-8 place-items-center rounded-full disabled:opacity-30 sm:grid"
                :disabled="atEnd"
                aria-label="Ver más"
                @click="scrollBy(1)"
            >
                <ChevronRight class="size-4" />
            </button>
        </div>
        <div
            ref="track"
            class="flex snap-x scroll-px-4 [scrollbar-width:none] gap-3 overflow-x-auto px-4 pb-1 [&::-webkit-scrollbar]:hidden"
            @scroll.passive="onScroll"
        >
            <BusinessSuggestCard
                v-for="b in businesses"
                :key="b.id"
                :business="b"
                class="snap-start"
            />
        </div>
    </section>
</template>
