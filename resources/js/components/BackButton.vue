<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';

/**
 * "Volver": regresa a la pantalla anterior si la hay. Si la página se abrió
 * desde un enlace (por ejemplo, de WhatsApp), va al lugar de respaldo.
 */
const props = defineProps<{ fallback: string; label?: string }>();

function back(): void {
    // Inertia guarda su estado en el historial: si existe, vinimos navegando.
    if (window.history.length > 1 && window.history.state?.page) {
        window.history.back();
    } else {
        router.visit(props.fallback);
    }
}
</script>

<template>
    <button
        type="button"
        class="text-muted-foreground hover:bg-muted hover:text-foreground inline-flex h-10 items-center gap-2 rounded-full pr-4 pl-2.5 text-sm font-bold transition-colors"
        @click="back"
    >
        <ArrowLeft class="size-5" />
        {{ label ?? 'Volver' }}
    </button>
</template>
