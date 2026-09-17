<script setup lang="ts">
import { Moon, Sun } from '@lucide/vue';
import { useAppearance } from '@/composables/useAppearance';

const { updateAppearance } = useAppearance();

/*
 * Cuál icono se ve lo decide SOLO el CSS (la clase `dark` del <html>), no un
 * estado de Vue. Antes el icono dependía de un computed que en el servidor
 * valía una cosa y en el navegador otra; ese desajuste de hidratación dejaba
 * el botón vacío al pasar a modo día. Así no hay nada que desincronizar.
 */
function toggle(): void {
    const isDark = document.documentElement.classList.contains('dark');

    updateAppearance(isDark ? 'light' : 'dark');
}
</script>

<template>
    <button
        type="button"
        class="text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-ring relative inline-flex size-10 flex-none items-center justify-center rounded-full transition-colors focus-visible:ring-2 focus-visible:outline-none"
        aria-label="Cambiar entre modo día y modo noche"
        title="Modo día / noche"
        @click="toggle"
    >
        <!-- Luna: visible en modo día -->
        <Moon
            class="absolute size-[18px] scale-100 rotate-0 opacity-100 transition-all duration-300 dark:scale-50 dark:-rotate-90 dark:opacity-0"
        />
        <!-- Sol: visible en modo noche -->
        <Sun
            class="absolute size-[18px] scale-50 rotate-90 opacity-0 transition-all duration-300 dark:scale-100 dark:rotate-0 dark:opacity-100"
        />
    </button>
</template>
