<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ExternalLink, Sparkles } from '@lucide/vue';
import { computed, ref } from 'vue';
import AdminNav from '@/components/admin/AdminNav.vue';
import PagerLinks from '@/components/admin/PagerLinks.vue';
import SearchBox from '@/components/admin/SearchBox.vue';
import BusinessAvatar from '@/components/feed/BusinessAvatar.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { selectClass } from '@/lib/formClasses';
import { index as adminIndex } from '@/routes/admin';
import { founder as founderRoute, index as businessesIndex, plan as planRoute } from '@/routes/admin/businesses';
import { show as businessShow } from '@/routes/business';
import type { Paginated } from '@/types/admin';

type Row = {
    id: number;
    name: string;
    slug: string;
    avatar: string | null;
    owner: { name: string; email: string } | null;
    finished: boolean;
    founder: boolean;
    founderSince: string | null;
    plan: string;
    state: string | null;
    endsAt: string | null;
    lockedPrice: string | null;
};

const props = defineProps<{
    businesses: Paginated<Row>;
    plans: { id: number; name: string; code: string }[];
    filters: { q: string; filtro: 'fundadores' | 'nuevos' | null };
    counts: { all: number; founders: number };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Administración', href: adminIndex() },
            { title: 'Negocios', href: businessesIndex() },
        ],
    },
});

const tabs = computed(() => [
    { key: null, label: 'Todos', count: props.counts.all },
    { key: 'fundadores' as const, label: 'Fundadores', count: props.counts.founders },
    { key: 'nuevos' as const, label: 'Con límites', count: props.counts.all - props.counts.founders },
]);

function filterBy(key: string | null): void {
    router.get(businessesIndex().url, Object.fromEntries(Object.entries({ q: props.filters.q, filtro: key }).filter(([, v]) => v)), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

// ---- asignar plan
const target = ref<Row | null>(null);
const form = useForm({ plan_id: null as number | null, months: 1 as number | null, note: '' });

function openAssign(row: Row): void {
    target.value = row;
    form.reset();
    form.clearErrors();
    form.plan_id = props.plans.find((p) => p.code === 'pro')?.id ?? props.plans[0]?.id ?? null;
}

function assign(): void {
    if (!target.value) return;
    form.post(planRoute(target.value.id).url, {
        preserveScroll: true,
        onSuccess: () => (target.value = null),
    });
}

// ---- fundador
const founderTarget = ref<Row | null>(null);
const togglingFounder = ref(false);

function toggleFounder(): void {
    if (!founderTarget.value) return;
    router.post(founderRoute(founderTarget.value.id).url, {}, {
        preserveScroll: true,
        onStart: () => (togglingFounder.value = true),
        onFinish: () => {
            togglingFounder.value = false;
            founderTarget.value = null;
        },
    });
}
</script>

<template>
    <Head title="Negocios" />

    <div class="mx-auto flex w-full max-w-5xl flex-col gap-5 px-4 py-5 md:py-8">
        <AdminNav>Negocios</AdminNav>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <SearchBox :url="businessesIndex().url" :value="filters.q" :extra="{ filtro: filters.filtro }" placeholder="Buscar por nombre, @usuario o correo" class="flex-1" />
            <div class="flex gap-1.5 overflow-x-auto [scrollbar-width:none]">
                <button
                    v-for="t in tabs"
                    :key="t.label"
                    type="button"
                    class="inline-flex h-9 items-center gap-1.5 rounded-full px-3.5 text-sm font-bold whitespace-nowrap transition-colors"
                    :class="filters.filtro === t.key ? 'bg-foreground text-background' : 'bg-muted text-muted-foreground hover:text-foreground'"
                    @click="filterBy(t.key)"
                >
                    {{ t.label }} <span class="tabular-nums opacity-70">{{ t.count }}</span>
                </button>
            </div>
        </div>

        <ul v-if="businesses.data.length" class="bg-card divide-border divide-y overflow-hidden rounded-2xl border">
            <li v-for="b in businesses.data" :key="b.id" class="flex flex-wrap items-center gap-x-4 gap-y-3 p-4">
                <div class="flex min-w-0 flex-1 basis-64 items-center gap-3">
                    <BusinessAvatar :src="b.avatar" :name="b.name" size="md" />
                    <div class="min-w-0">
                        <p class="flex flex-wrap items-center gap-x-2 font-bold">
                            <span class="truncate">{{ b.name }}</span>
                            <span v-if="b.founder" class="bg-ok-soft text-ok inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-bold">
                                <Sparkles class="size-3" /> Fundador
                            </span>
                            <span v-if="!b.finished" class="bg-wait-soft text-wait rounded-full px-2 py-0.5 text-[11px] font-bold">Sin terminar</span>
                        </p>
                        <p class="text-muted-foreground truncate text-xs">@{{ b.slug }} · {{ b.owner?.email }}</p>
                    </div>
                </div>

                <div class="min-w-32 text-sm">
                    <p class="font-bold">{{ b.plan }}</p>
                    <p class="text-muted-foreground text-xs">
                        <template v-if="b.state">{{ b.state }}</template>
                        <template v-if="b.endsAt"> · hasta {{ b.endsAt }}</template>
                        <template v-if="b.lockedPrice"><br />Paga {{ b.lockedPrice }}</template>
                    </p>
                </div>

                <div class="flex w-full items-center gap-1.5 sm:w-auto">
                    <Link v-if="b.finished" :href="businessShow(b.slug)" class="text-muted-foreground hover:text-foreground grid size-9 place-items-center rounded-full" :aria-label="`Ver perfil de ${b.name}`">
                        <ExternalLink class="size-4" />
                    </Link>
                    <Button variant="outline" size="sm" class="flex-1 rounded-full sm:flex-none" @click="founderTarget = b">
                        {{ b.founder ? 'Quitar fundador' : 'Hacer fundador' }}
                    </Button>
                    <Button size="sm" class="flex-1 rounded-full sm:flex-none" @click="openAssign(b)">Cambiar plan</Button>
                </div>
            </li>
        </ul>
        <p v-else class="text-muted-foreground rounded-2xl border border-dashed p-8 text-center text-sm">
            No hay negocios que coincidan.
        </p>

        <PagerLinks :page="businesses" />
    </div>

    <!-- Asignar plan -->
    <Dialog :open="target !== null" @update:open="(v) => !v && (target = null)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Cambiar plan de {{ target?.name }}</DialogTitle>
                <DialogDescription>
                    Útil para regalar Pro o activar un pago que llegó por transferencia. Toma las
                    condiciones de hoy del plan elegido.
                </DialogDescription>
            </DialogHeader>

            <form id="assign-plan" class="grid gap-4" @submit.prevent="assign">
                <div class="grid gap-1.5">
                    <Label for="assign-plan-id">Plan</Label>
                    <select id="assign-plan-id" v-model.number="form.plan_id" :class="selectClass" required>
                        <option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                    <InputError :message="form.errors.plan_id" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="assign-months">Duración</Label>
                    <select id="assign-months" v-model="form.months" :class="selectClass">
                        <option :value="1">1 mes</option>
                        <option :value="3">3 meses</option>
                        <option :value="6">6 meses</option>
                        <option :value="12">12 meses</option>
                        <option :value="null">Sin vencimiento</option>
                    </select>
                    <InputError :message="form.errors.months" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="assign-note">Nota (opcional)</Label>
                    <input id="assign-note" v-model="form.note" maxlength="200" placeholder="Por ejemplo: transferencia BI #123456" class="border-input bg-card focus-visible:border-ring focus-visible:ring-ring/50 h-11 rounded-md border px-3 text-base outline-none focus-visible:ring-[3px] md:text-sm" />
                    <p class="text-muted-foreground text-xs">Queda en el registro de cambios.</p>
                </div>
            </form>

            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="ghost" class="rounded-full">Cancelar</Button>
                </DialogClose>
                <Button type="submit" form="assign-plan" class="rounded-full" :disabled="form.processing">
                    <Spinner v-if="form.processing" /> Asignar plan
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Fundador -->
    <Dialog :open="founderTarget !== null" @update:open="(v) => !v && (founderTarget = null)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ founderTarget?.founder ? `¿Quitar a ${founderTarget?.name} de fundadores?` : `¿Hacer fundador a ${founderTarget?.name}?` }}</DialogTitle>
                <DialogDescription>
                    <template v-if="founderTarget?.founder">
                        Dejará de tener todo sin límites y se le aplicarán los de su plan
                        (salvo mientras "Todo libre" esté encendido). Lo que ya publicó no se borra.
                    </template>
                    <template v-else>
                        Tendrá todo sin límites para siempre, sin importar su plan.
                        "Promocionado" sigue dependiendo de su plan.
                    </template>
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="ghost" class="rounded-full">Cancelar</Button>
                </DialogClose>
                <Button class="rounded-full" :variant="founderTarget?.founder ? 'destructive' : 'default'" :disabled="togglingFounder" @click="toggleFounder">
                    <Spinner v-if="togglingFounder" /> {{ founderTarget?.founder ? 'Quitar fundador' : 'Hacer fundador' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
