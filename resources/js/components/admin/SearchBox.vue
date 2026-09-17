<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Search, X } from '@lucide/vue';
import { onBeforeUnmount, ref, watch } from 'vue';

/** Búsqueda que filtra mientras escribes, sin recargar la página entera. */
const props = defineProps<{
    url: string;
    value: string;
    placeholder: string;
    extra?: Record<string, string | null>;
}>();

const q = ref(props.value);
let timer: ReturnType<typeof setTimeout> | undefined;

watch(q, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        const query = Object.fromEntries(
            Object.entries({ ...props.extra, q: value.trim() }).filter(
                ([, v]) => v !== null && v !== '',
            ),
        );
        router.get(props.url, query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, 300);
});

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <div class="relative">
        <Search
            class="text-muted-foreground pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2"
        />
        <input
            v-model="q"
            type="search"
            :placeholder="placeholder"
            :aria-label="placeholder"
            class="border-input bg-card placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 h-11 w-full rounded-full border pr-10 pl-10 text-base outline-none focus-visible:ring-[3px] md:text-sm [&::-webkit-search-cancel-button]:hidden"
        />
        <button
            v-if="q"
            type="button"
            class="text-muted-foreground hover:text-foreground absolute top-1/2 right-2 grid size-8 -translate-y-1/2 place-items-center rounded-full"
            aria-label="Borrar búsqueda"
            @click="q = ''"
        >
            <X class="size-4" />
        </button>
    </div>
</template>
