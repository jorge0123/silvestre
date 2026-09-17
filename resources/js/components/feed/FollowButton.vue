<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Check, Plus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { login } from '@/routes';
import { follow } from '@/routes/business';

/** Seguir un negocio. Sin cuenta, lleva a iniciar sesión. */
const props = withDefaults(
    defineProps<{ slug: string; following: boolean; size?: 'sm' | 'md' }>(),
    { size: 'md' },
);

const page = usePage();
const isGuest = computed(() => !page.props.auth?.user);

// Respuesta inmediata en pantalla; el servidor confirma por detrás.
const on = ref(props.following);
watch(
    () => props.following,
    (v) => (on.value = v),
);

function toggle(): void {
    on.value = !on.value;
    router.post(
        follow(props.slug).url,
        {},
        {
            preserveScroll: true,
            onError: () => (on.value = !on.value),
        },
    );
}

const classes = computed(() => [
    'inline-flex items-center justify-center gap-1.5 rounded-full font-bold transition-all active:scale-[.97]',
    props.size === 'sm' ? 'h-8 px-3.5 text-xs' : 'h-11 px-5 text-sm',
    on.value
        ? 'bg-muted text-foreground hover:bg-muted/70'
        : 'bg-foreground text-background hover:opacity-90',
]);
</script>

<template>
    <Link v-if="isGuest" :href="login()" :class="classes">
        <Plus class="size-4" /> Seguir
    </Link>
    <button
        v-else
        type="button"
        :class="classes"
        :aria-pressed="on"
        @click="toggle"
    >
        <Check v-if="on" class="size-4" />
        <Plus v-else class="size-4" />
        {{ on ? 'Siguiendo' : 'Seguir' }}
    </button>
</template>
