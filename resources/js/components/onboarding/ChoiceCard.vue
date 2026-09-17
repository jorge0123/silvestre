<script setup lang="ts">
import { Check } from '@lucide/vue';
import type { Component } from 'vue';

/** Opción grande y fácil de tocar. Sirve como radio o como casilla múltiple. */
defineProps<{
    selected: boolean;
    title: string;
    description?: string;
    icon?: Component;
    multiple?: boolean;
}>();

defineEmits<{ select: [] }>();
</script>

<template>
    <button
        type="button"
        :role="multiple ? 'checkbox' : 'radio'"
        :aria-checked="selected"
        class="group bg-card hover:border-foreground/30 focus-visible:ring-ring relative flex w-full items-start gap-3 rounded-xl border p-4 text-left transition-all focus-visible:ring-2 focus-visible:outline-none active:scale-[0.99]"
        :class="
            selected
                ? 'border-primary bg-primary/[0.04] ring-primary ring-1'
                : 'border-border'
        "
        @click="$emit('select')"
    >
        <span
            v-if="icon"
            class="grid size-10 flex-none place-items-center rounded-lg transition-colors"
            :class="
                selected
                    ? 'bg-primary text-primary-foreground'
                    : 'bg-muted text-muted-foreground'
            "
        >
            <component :is="icon" class="size-5" />
        </span>
        <span class="min-w-0 flex-1">
            <span class="block text-[15px] font-bold">{{ title }}</span>
            <span
                v-if="description"
                class="text-muted-foreground mt-0.5 block text-sm leading-snug"
                >{{ description }}</span
            >
            <slot />
        </span>
        <span
            class="grid size-5 flex-none place-items-center border transition-colors"
            :class="[
                multiple ? 'rounded-md' : 'rounded-full',
                selected
                    ? 'border-primary bg-primary text-primary-foreground'
                    : 'border-border',
            ]"
            aria-hidden="true"
        >
            <Check v-if="selected" class="size-3.5" stroke-width="3" />
        </span>
    </button>
</template>
