<script setup lang="ts">
import { Star } from '@lucide/vue';
import { computed } from 'vue';

const props = withDefaults(defineProps<{ value: number; size?: string }>(), {
    size: 'size-3.5',
});

// Estrellas llenas redondeando al medio punto más cercano hacia abajo.
const filled = computed(() => Math.round(props.value));
</script>

<template>
    <span class="inline-flex gap-0.5" :aria-label="`${value} de 5 estrellas`" role="img">
        <Star
            v-for="n in 5"
            :key="n"
            :class="[size, n <= filled ? 'text-star fill-current' : 'text-muted-foreground/30 fill-current']"
            aria-hidden="true"
        />
    </span>
</template>
