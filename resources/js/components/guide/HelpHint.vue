<script setup lang="ts">
import { CircleHelp } from '@lucide/vue';
import { ref, useId } from 'vue';

/**
 * "¿Qué es esto?" junto a un campo. Se abre al tocarlo (no al pasar el
 * mouse) para que funcione igual en el celular.
 */
defineProps<{
    label?: string;
}>();

const open = ref(false);
const id = `hint-${useId()}`;
</script>

<template>
    <div>
        <button
            type="button"
            class="text-muted-foreground hover:text-brand inline-flex items-center gap-1 text-xs font-bold"
            :aria-expanded="open"
            :aria-controls="id"
            @click="open = !open"
        >
            <CircleHelp class="size-3.5" />
            {{ label ?? '¿Qué es esto?' }}
        </button>
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 -translate-y-1"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                :id="id"
                class="bg-muted text-foreground/85 mt-2 rounded-lg px-3.5 py-3 text-sm leading-relaxed"
            >
                <slot />
            </div>
        </Transition>
    </div>
</template>
