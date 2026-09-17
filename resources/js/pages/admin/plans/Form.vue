<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import ToggleSwitch from '@/components/admin/ToggleSwitch.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { selectClass, textareaClass } from '@/lib/formClasses';
import { index as adminIndex } from '@/routes/admin';
import { destroy, index as plansIndex, store, update } from '@/routes/admin/plans';
import type { AdminPlan, AdminPlanPrice, PlanCapabilityKey, PlanLimitKey } from '@/types/admin';
import { planCapabilities, planLimits } from '@/types/admin';

const props = defineProps<{ plan: AdminPlan | null }>();
const page = usePage();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Administración', href: adminIndex() },
            { title: 'Planes', href: plansIndex() },
        ],
    },
});

const editing = computed(() => props.plan !== null);
const current = computed(() => props.plan?.activeSubscriptions ?? 0);

const form = useForm({
    code: '',
    name: props.plan?.name ?? '',
    description: props.plan?.description ?? '',
    position: props.plan?.position ?? 10,
    is_active: props.plan?.is_active ?? true,
    post_quota_weekly: props.plan ? props.plan.post_quota_weekly : 7,
    story_quota_daily: props.plan ? props.plan.story_quota_daily : 10,
    photo_limit: props.plan ? props.plan.photo_limit : 10,
    product_limit: props.plan ? props.plan.product_limit : 30,
    service_limit: props.plan ? props.plan.service_limit : 30,
    can_checkout: props.plan?.can_checkout ?? true,
    can_reserve_stock: props.plan?.can_reserve_stock ?? true,
    can_use_team: props.plan?.can_use_team ?? true,
    can_invoice: props.plan?.can_invoice ?? false,
    can_promote: props.plan?.can_promote ?? false,
    prices: (props.plan?.prices ?? []).map((p) => ({ ...p })) as AdminPlanPrice[],
    apply_to_current: false,
});

// Al marcar "Sin límite" se guarda null; al desmarcar, vuelve el último número.
const lastNumbers = ref<Record<string, number>>({});

function setUnlimited(key: PlanLimitKey, unlimited: boolean): void {
    if (unlimited) {
        lastNumbers.value[key] = form[key] ?? 10;
        form[key] = null;
    } else {
        form[key] = lastNumbers.value[key] ?? 10;
    }
}

const intervals = [1, 3, 6, 12];

function addPrice(): void {
    const used = form.prices.map((p) => p.interval_months);
    const next = intervals.find((m) => !used.includes(m)) ?? 1;
    form.prices.push({ id: null, interval_months: next, price: 0, is_active: true });
}

function perMonth(p: AdminPlanPrice): string {
    const value = Number(p.price) / Math.max(p.interval_months, 1);
    return `$${value.toFixed(2).replace(/\.00$/, '')} al mes`;
}

function submit(): void {
    const options = { preserveScroll: true };
    if (props.plan) {
        form.transform(({ code: _code, ...data }) => data).put(update(props.plan.id).url, options);
    } else {
        form.transform(({ apply_to_current: _apply, ...data }) => data).post(store().url, options);
    }
}

const confirmDelete = ref(false);
const deleting = ref(false);

function remove(): void {
    if (!props.plan) return;
    router.delete(destroy(props.plan.id).url, {
        onStart: () => (deleting.value = true),
        onFinish: () => {
            deleting.value = false;
            confirmDelete.value = false;
        },
    });
}

const errors = computed(() => form.errors as Record<string, string | undefined>);
</script>

<template>
    <Head :title="plan ? `Editar ${plan.name}` : 'Crear plan'" />

    <form class="mx-auto flex w-full max-w-3xl flex-col gap-5 px-4 py-5 pb-28 md:py-8" @submit.prevent="submit">
        <div>
            <Link :href="plansIndex()" class="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 text-sm font-bold">
                <ArrowLeft class="size-4" /> Planes
            </Link>
            <h1 class="font-display mt-2 text-3xl font-extrabold">{{ plan ? `Editar ${plan.name}` : 'Crear plan' }}</h1>
        </div>

        <InputError :message="(page.props.errors as Record<string, string>).plan" />

        <!-- Datos -->
        <section class="bg-card grid gap-4 rounded-2xl border p-5 md:p-6">
            <h2 class="font-display text-lg font-bold">Datos del plan</h2>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <Label for="name">Nombre</Label>
                    <Input id="name" v-model="form.name" required maxlength="60" placeholder="Por ejemplo: Emprende" class="h-11" />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="code">Código</Label>
                    <Input v-if="!editing" id="code" v-model="form.code" required pattern="[a-z0-9_\-]{2,30}" placeholder="emprende" class="h-11 font-mono" />
                    <p v-else class="bg-muted text-muted-foreground flex h-11 items-center rounded-md px-3 font-mono text-sm">{{ plan?.code }}</p>
                    <p class="text-muted-foreground text-xs">{{ editing ? 'El código no cambia: la app lo usa por dentro.' : 'Minúsculas, sin espacios. No se puede cambiar después.' }}</p>
                    <InputError :message="form.errors.code" />
                </div>
            </div>

            <div class="grid gap-1.5">
                <Label for="description">Para quién es</Label>
                <textarea id="description" v-model="form.description" maxlength="255" rows="2" :class="textareaClass" placeholder="Una frase que explique a quién le conviene" />
                <InputError :message="form.errors.description" />
            </div>

            <div class="flex flex-wrap items-end gap-4">
                <div class="grid w-28 gap-1.5">
                    <Label for="position">Orden</Label>
                    <Input id="position" v-model.number="form.position" type="number" min="0" max="999" inputmode="numeric" class="h-11" />
                </div>
                <label class="flex min-h-11 flex-1 items-center gap-3">
                    <ToggleSwitch v-model="form.is_active" label="Plan activo" />
                    <span class="text-sm">
                        <strong>{{ form.is_active ? 'Activo' : 'Inactivo' }}</strong>
                        <span class="text-muted-foreground block text-xs">Inactivo: nadie nuevo puede elegirlo; quien ya lo tiene lo conserva.</span>
                    </span>
                </label>
            </div>
            <InputError :message="form.errors.is_active" />
        </section>

        <!-- Límites -->
        <section class="bg-card rounded-2xl border p-5 md:p-6">
            <h2 class="font-display text-lg font-bold">Límites</h2>
            <p class="text-muted-foreground text-sm">No aplican a los negocios fundadores ni mientras "Todo libre" esté encendido.</p>

            <ul class="divide-border mt-3 divide-y">
                <li v-for="l in planLimits" :key="l.key" class="flex flex-wrap items-center gap-x-4 gap-y-2 py-3">
                    <p class="min-w-40 flex-1 text-sm">
                        <strong>{{ l.label }}</strong>&nbsp;<span class="text-muted-foreground">{{ l.unit }}</span>
                    </p>
                    <label class="flex items-center gap-2 text-sm">
                        <Checkbox :model-value="form[l.key] === null" @update:model-value="(v) => setUnlimited(l.key, v === true)" />
                        Sin límite
                    </label>
                    <Input
                        :model-value="form[l.key] ?? ''"
                        type="number"
                        min="0"
                        max="65000"
                        inputmode="numeric"
                        :disabled="form[l.key] === null"
                        :aria-label="`${l.label} ${l.unit}`"
                        class="h-10 w-24 text-right tabular-nums"
                        :placeholder="form[l.key] === null ? '∞' : ''"
                        @update:model-value="(v) => (form[l.key] = Math.max(0, Math.floor(Number(v) || 0)))"
                    />
                    <InputError :message="errors[l.key]" class="w-full" />
                </li>
            </ul>
        </section>

        <!-- Funciones -->
        <section class="bg-card rounded-2xl border p-5 md:p-6">
            <h2 class="font-display text-lg font-bold">Funciones</h2>
            <ul class="divide-border mt-2 divide-y">
                <li v-for="c in planCapabilities" :key="c.key" class="flex items-center gap-4 py-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-bold">{{ c.label }}</p>
                        <p class="text-muted-foreground text-xs">{{ c.hint }}</p>
                    </div>
                    <ToggleSwitch v-model="form[c.key as PlanCapabilityKey]" :label="c.label" />
                </li>
            </ul>
        </section>

        <!-- Precios -->
        <section class="bg-card rounded-2xl border p-5 md:p-6">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h2 class="font-display text-lg font-bold">Precios</h2>
                    <p class="text-muted-foreground text-sm">En dólares, pagados por transferencia. Sin precios, el plan no tiene costo.</p>
                </div>
                <Button type="button" variant="outline" size="sm" class="rounded-full" :disabled="form.prices.length >= 6" @click="addPrice">
                    <Plus class="size-4" /> Agregar precio
                </Button>
            </div>

            <TransitionGroup
                tag="ul"
                class="mt-4 grid gap-3"
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0 -translate-y-1"
                leave-active-class="transition duration-150 ease-in"
                leave-to-class="opacity-0"
            >
                <li v-for="(p, i) in form.prices" :key="p.id ?? `new-${i}`" class="bg-muted/50 flex flex-wrap items-end gap-3 rounded-xl p-3">
                    <div class="grid gap-1">
                        <Label :for="`months-${i}`" class="text-xs">Duración</Label>
                        <select :id="`months-${i}`" v-model.number="p.interval_months" :class="[selectClass, 'w-32']">
                            <option v-for="m in intervals" :key="m" :value="m">{{ m === 1 ? '1 mes' : `${m} meses` }}</option>
                        </select>
                    </div>
                    <div class="grid gap-1">
                        <Label :for="`price-${i}`" class="text-xs">Precio (USD)</Label>
                        <div class="relative">
                            <span class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm">$</span>
                            <Input :id="`price-${i}`" v-model.number="p.price" type="number" min="0" max="9999" step="0.01" inputmode="decimal" class="h-11 w-28 pl-6 tabular-nums" />
                        </div>
                    </div>
                    <p class="text-muted-foreground pb-3 text-xs tabular-nums">{{ p.interval_months > 1 ? perMonth(p) : '' }}</p>
                    <label class="ml-auto flex items-center gap-2 pb-2 text-sm">
                        <ToggleSwitch v-model="p.is_active" :label="`Precio de ${p.interval_months} meses activo`" />
                        <span class="hidden sm:inline">{{ p.is_active ? 'A la venta' : 'Oculto' }}</span>
                    </label>
                    <Button type="button" variant="ghost" size="icon" class="mb-0.5 rounded-full" :aria-label="`Quitar precio de ${p.interval_months} meses`" @click="form.prices.splice(i, 1)">
                        <Trash2 class="size-4" />
                    </Button>
                    <InputError :message="errors[`prices.${i}.interval_months`] ?? errors[`prices.${i}.price`]" class="w-full" />
                </li>
            </TransitionGroup>
            <p v-if="editing" class="text-muted-foreground mt-3 text-xs">
                Quien ya paga conserva el precio con el que entró. Un precio con pagos registrados no se borra: queda oculto.
            </p>
        </section>

        <!-- Quién recibe el cambio -->
        <section v-if="editing && current > 0" class="bg-wait-soft rounded-2xl p-5">
            <label class="flex items-start gap-3">
                <Checkbox v-model="form.apply_to_current" class="mt-0.5 bg-white dark:bg-transparent" />
                <span class="text-sm">
                    <strong>Aplicar también a los {{ current }} {{ current === 1 ? 'negocio que ya lo usa' : 'negocios que ya lo usan' }}</strong>
                    <span class="mt-0.5 block">
                        Sin marcar, los límites y funciones nuevos solo aplican a quien entre desde ahora.
                        Úsalo para mejorar un plan, no para recortarlo. Su precio no cambia.
                    </span>
                </span>
            </label>
        </section>

        <!-- Borrar -->
        <section v-if="plan && !plan.protected" class="flex flex-wrap items-center gap-3 rounded-2xl border border-dashed p-4">
            <p class="text-muted-foreground min-w-0 flex-1 text-sm">
                Solo se puede borrar si ningún negocio lo ha usado. Si ya lo usaron, desactívalo.
            </p>
            <Button type="button" variant="ghost" class="text-destructive rounded-full" @click="confirmDelete = true">
                <Trash2 class="size-4" /> Borrar plan
            </Button>
        </section>

        <!-- Barra fija para guardar: siempre a mano en el celular -->
        <div class="bg-background/90 fixed inset-x-0 bottom-0 z-20 border-t px-4 pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom,0px))] backdrop-blur md:sticky md:-mx-4 md:rounded-b-2xl">
            <div class="mx-auto flex max-w-3xl items-center justify-end gap-2">
                <p v-if="form.isDirty" class="text-muted-foreground mr-auto text-xs">Tienes cambios sin guardar</p>
                <Link :href="plansIndex()" class="text-muted-foreground hover:text-foreground px-3 text-sm font-bold">Cancelar</Link>
                <Button type="submit" class="h-11 rounded-full px-6" :disabled="form.processing">
                    <Spinner v-if="form.processing" /> {{ plan ? 'Guardar cambios' : 'Crear plan' }}
                </Button>
            </div>
        </div>
    </form>

    <Dialog v-model:open="confirmDelete">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>¿Borrar {{ plan?.name }}?</DialogTitle>
                <DialogDescription>Se borra con sus precios. Esto no se puede deshacer.</DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="ghost" class="rounded-full">No borrar</Button>
                </DialogClose>
                <Button variant="destructive" class="rounded-full" :disabled="deleting" @click="remove">
                    <Spinner v-if="deleting" /> Borrar plan
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
