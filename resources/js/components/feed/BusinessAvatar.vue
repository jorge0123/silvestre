<script setup lang="ts">
import { computed } from 'vue';

/**
 * Foto del negocio. Con `ring` muestra el aro de colores que indica que tiene
 * historias nuevas, igual que en otras redes: la gente ya sabe que se toca.
 */
const props = withDefaults(
    defineProps<{
        src: string | null;
        name: string;
        size?: 'sm' | 'md' | 'lg' | 'xl';
        ring?: boolean;
    }>(),
    { size: 'md', ring: false },
);

const sizes = {
    sm: 'size-9',
    md: 'size-11',
    lg: 'size-16',
    xl: 'size-24 md:size-32',
};

const initials = computed(() =>
    props.name
        .split(/\s+/)
        .slice(0, 2)
        .map((w) => w[0])
        .join('')
        .toUpperCase(),
);
</script>

<template>
    <span
        class="inline-grid flex-none place-items-center rounded-full"
        :class="[
            sizes[size],
            ring
                ? 'bg-[conic-gradient(from_210deg,var(--primary),var(--star),var(--brand),var(--primary))] p-[2.5px]'
                : '',
        ]"
    >
        <span
            class="bg-background grid size-full place-items-center overflow-hidden rounded-full"
            :class="ring ? 'p-[2px]' : ''"
        >
            <img
                v-if="src"
                :src="src"
                :alt="name"
                class="size-full rounded-full object-cover"
                :loading="size === 'xl' ? 'eager' : 'lazy'"
                decoding="async"
            />
            <span
                v-else
                class="bg-brand-soft text-brand grid size-full place-items-center rounded-full text-sm font-bold"
            >
                {{ initials }}
            </span>
        </span>
    </span>
</template>
