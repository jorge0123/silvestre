<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight, Sparkles } from '@lucide/vue';
import { ref } from 'vue';
import AdminNav from '@/components/admin/AdminNav.vue';
import ToggleSwitch from '@/components/admin/ToggleSwitch.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { index as adminIndex } from '@/routes/admin';
import { index as businessesIndex } from '@/routes/admin/businesses';
import { update as updateFreeMode } from '@/routes/admin/free-mode';

const props = defineProps<{
    stats: {
        users: number;
        businesses: number;
        postsThisWeek: number;
        promoted: number;
        paidActive: number;
    };
    freeMode: boolean;
    founders: number;
    nonFounders: number;
    activity: {
        id: number;
        who: string;
        what: string;
        note: string | null;
        ago: string;
    }[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Administración', href: adminIndex() }] },
});

const confirming = ref(false);
const saving = ref(false);

/** Encender es inmediato; apagar pide confirmación porque cambia a los nuevos. */
function onToggle(value: boolean): void {
    if (value) {
        save(true);
    } else {
        confirming.value = true;
    }
}

function save(enabled: boolean): void {
    router.patch(
        updateFreeMode().url,
        { enabled },
        {
            preserveScroll: true,
            onStart: () => (saving.value = true),
            onFinish: () => {
                saving.value = false;
                confirming.value = false;
            },
        },
    );
}

const tiles = [
    { key: 'users', label: 'Personas registradas' },
    { key: 'businesses', label: 'Negocios abiertos' },
    { key: 'postsThisWeek', label: 'Publicaciones esta semana' },
    { key: 'promoted', label: 'Salen como Promocionado' },
] as const;
</script>

<template>
    <Head title="Administración" />

    <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 px-4 py-5 md:py-8">
        <AdminNav>Resumen</AdminNav>

        <!-- Todo libre -->
        <section class="bg-card overflow-hidden rounded-2xl border">
            <div class="flex items-start gap-4 p-5 md:p-6">
                <div class="min-w-0 flex-1">
                    <h2
                        class="font-display flex items-center gap-2 text-xl font-bold"
                    >
                        Todo libre
                        <span
                            class="rounded-full px-2.5 py-0.5 text-[11px] font-bold tracking-wide uppercase"
                            :class="
                                freeMode
                                    ? 'bg-ok-soft text-ok'
                                    : 'bg-muted text-muted-foreground'
                            "
                            >{{ freeMode ? 'Encendido' : 'Apagado' }}</span
                        >
                    </h2>
                    <p class="text-muted-foreground mt-1 max-w-prose text-sm">
                        <template v-if="freeMode">
                            Nadie tiene límites. Todo negocio que se abra ahora
                            queda como
                            <strong class="text-foreground">fundador</strong> y
                            conserva todo sin límites aunque después apagues
                            esto.
                        </template>
                        <template v-else>
                            Los negocios nuevos usan los límites de su plan. Los
                            fundadores siguen sin límites.
                        </template>
                    </p>
                </div>
                <Spinner v-if="saving" class="mt-1" />
                <ToggleSwitch
                    :model-value="props.freeMode"
                    label="Todo libre"
                    :disabled="saving"
                    @update:model-value="onToggle"
                />
            </div>

            <div
                class="bg-muted/60 flex flex-wrap items-center gap-x-6 gap-y-2 border-t px-5 py-3 text-sm md:px-6"
            >
                <span class="flex items-center gap-1.5">
                    <Sparkles class="text-ok size-4" />
                    <strong class="tabular-nums">{{ founders }}</strong>
                    fundadores
                </span>
                <span
                    ><strong class="tabular-nums">{{ nonFounders }}</strong> con
                    límites de su plan</span
                >
                <Link
                    :href="businessesIndex({ query: { filtro: 'fundadores' } })"
                    class="text-primary ml-auto inline-flex items-center gap-1 font-bold"
                >
                    Ver fundadores <ArrowRight class="size-4" />
                </Link>
            </div>
        </section>

        <!-- Números -->
        <section
            class="grid grid-cols-2 gap-3 lg:grid-cols-4"
            aria-label="Números de hoy"
        >
            <div
                v-for="tile in tiles"
                :key="tile.key"
                class="bg-card rounded-2xl border p-4"
            >
                <p class="font-display text-3xl font-extrabold tabular-nums">
                    {{ stats[tile.key] }}
                </p>
                <p class="text-muted-foreground mt-0.5 text-sm leading-snug">
                    {{ tile.label }}
                </p>
            </div>
        </section>

        <!-- Registro -->
        <section class="bg-card rounded-2xl border p-5 md:p-6">
            <h2 class="font-display text-xl font-bold">Últimos cambios</h2>
            <p class="text-muted-foreground text-sm">
                Todo lo que hace el equipo queda anotado y no se puede borrar.
            </p>

            <ul v-if="activity.length" class="divide-border mt-4 divide-y">
                <li
                    v-for="a in activity"
                    :key="a.id"
                    class="flex flex-wrap items-baseline gap-x-3 gap-y-0.5 py-3"
                >
                    <p class="min-w-0 flex-1 text-sm">
                        <strong>{{ a.who }}</strong> · {{ a.what }}
                        <span
                            v-if="a.note"
                            class="text-muted-foreground mt-0.5 block text-xs break-words"
                            >{{ a.note }}</span
                        >
                    </p>
                    <span
                        class="text-muted-foreground text-xs whitespace-nowrap"
                        >{{ a.ago }}</span
                    >
                </li>
            </ul>
            <p
                v-else
                class="text-muted-foreground mt-4 rounded-xl border border-dashed p-4 text-center text-sm"
            >
                Aún no hay cambios. Cuando edites un plan o des un permiso,
                aparecerá aquí.
            </p>
        </section>
    </div>

    <Dialog v-model:open="confirming">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>¿Apagar "Todo libre"?</DialogTitle>
                <DialogDescription class="space-y-2 text-left">
                    <span class="block">
                        Los <strong>{{ founders }}</strong> negocios fundadores
                        siguen sin límites, para siempre.
                    </span>
                    <span class="block">
                        Los negocios que se abran desde ahora tendrán los
                        límites de su plan y, si quieren más, tendrán que
                        comprar uno.
                    </span>
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="ghost" class="rounded-full"
                        >Dejarlo encendido</Button
                    >
                </DialogClose>
                <Button
                    class="rounded-full"
                    :disabled="saving"
                    @click="save(false)"
                >
                    <Spinner v-if="saving" /> Apagar
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
