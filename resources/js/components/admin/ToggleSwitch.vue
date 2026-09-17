<script setup lang="ts">
/** Interruptor de encendido/apagado, cómodo para el dedo (44 px de alto útil). */
const model = defineModel<boolean>({ required: true });

defineProps<{ label: string; disabled?: boolean }>();
</script>

<template>
    <button
        type="button"
        role="switch"
        :aria-checked="model"
        :aria-label="label"
        :disabled="disabled"
        class="group focus-visible:ring-ring/50 relative inline-flex h-7 w-12 flex-none items-center rounded-full transition-colors duration-200 outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50"
        :class="model ? 'bg-ok' : 'bg-foreground/20'"
        @click="model = !model"
    >
        <span
            class="bg-background inline-block size-5 rounded-full shadow-sm transition-transform duration-200 ease-out motion-reduce:transition-none"
            :class="model ? 'translate-x-6' : 'translate-x-1'"
        />
    </button>
</template>
