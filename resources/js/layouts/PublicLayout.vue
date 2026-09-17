<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { House } from '@lucide/vue';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import { Toaster } from '@/components/ui/sonner';
import { home, login, register } from '@/routes';
import { feed } from '@/routes';

/**
 * Plantilla de las páginas que se pueden ver sin cuenta (perfil de negocio,
 * publicación). Es lo que abre alguien que recibió el link por WhatsApp.
 */
const page = usePage();
const user = computed(() => page.props.auth?.user);
</script>

<template>
    <div class="bg-background min-h-svh">
        <header class="bg-background/85 border-border sticky top-0 z-40 border-b backdrop-blur">
            <div class="mx-auto flex h-14 max-w-5xl items-center gap-2 px-4">
                <Link :href="user ? feed() : home()" class="mr-auto flex items-center gap-2">
                    <AppLogoIcon class="size-8" />
                    <span class="font-display text-lg font-extrabold tracking-tight">Silvestre</span>
                </Link>
                <ThemeToggle />
                <Link
                    v-if="user"
                    :href="feed()"
                    class="bg-muted hover:bg-muted/70 inline-flex h-9 items-center gap-2 rounded-full px-4 text-sm font-bold"
                >
                    <House class="size-4" /> Inicio
                </Link>
                <template v-else>
                    <Link :href="login()" class="hover:bg-muted hidden h-9 items-center rounded-full px-4 text-sm font-bold sm:inline-flex">Entrar</Link>
                    <Link :href="register()" class="bg-primary text-primary-foreground inline-flex h-9 items-center rounded-full px-4 text-sm font-bold">Crear cuenta</Link>
                </template>
            </div>
        </header>
        <slot />
        <Toaster />
    </div>
</template>
