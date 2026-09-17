<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import type { BreadcrumbItem } from '@/types';

/**
 * Plantilla para páginas que se pueden ver con o sin cuenta (perfil de
 * negocio, publicación compartida):
 *  - Con sesión: el menú normal de la app, para no quedar "atrapado".
 *  - Sin sesión: encabezado público con Entrar y Crear cuenta.
 */
defineProps<{ breadcrumbs?: BreadcrumbItem[] }>();

const page = usePage();
const signedIn = computed(() => Boolean(page.props.auth?.user));
</script>

<template>
    <AppLayout v-if="signedIn" :breadcrumbs="breadcrumbs">
        <slot />
    </AppLayout>
    <PublicLayout v-else>
        <slot />
    </PublicLayout>
</template>
