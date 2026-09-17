<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import type { Paginated } from '@/types/admin';

defineProps<{ page: Paginated<unknown> }>();
</script>

<template>
    <nav v-if="page.last_page > 1" class="flex items-center justify-between gap-3 text-sm" aria-label="Páginas">
        <Link
            v-if="page.prev_page_url"
            :href="page.prev_page_url"
            preserve-scroll
            class="bg-muted inline-flex h-10 items-center gap-1 rounded-full px-4 font-bold"
        >
            <ChevronLeft class="size-4" /> Anterior
        </Link>
        <span v-else />
        <span class="text-muted-foreground tabular-nums">Página {{ page.current_page }} de {{ page.last_page }}</span>
        <Link
            v-if="page.next_page_url"
            :href="page.next_page_url"
            preserve-scroll
            class="bg-muted inline-flex h-10 items-center gap-1 rounded-full px-4 font-bold"
        >
            Siguiente <ChevronRight class="size-4" />
        </Link>
        <span v-else />
    </nav>
</template>
