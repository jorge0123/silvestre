<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Check, Lock, Pencil, Plus, X } from '@lucide/vue';
import AdminNav from '@/components/admin/AdminNav.vue';
import GuideCard from '@/components/guide/GuideCard.vue';
import { index as adminIndex } from '@/routes/admin';
import { create, edit, index as plansIndex } from '@/routes/admin/plans';
import type { AdminPlan } from '@/types/admin';
import { planCapabilities, planLimits } from '@/types/admin';

defineProps<{ plans: AdminPlan[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Administración', href: adminIndex() },
            { title: 'Planes', href: plansIndex() },
        ],
    },
});
</script>

<template>
    <Head title="Planes" />

    <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 px-4 py-5 md:py-8">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <AdminNav class="min-w-0 flex-1">Planes</AdminNav>
        </div>

        <GuideCard guide-key="admin.plans" title="Cómo funcionan los cambios">
            Cada negocio se queda con las condiciones y el precio del día en que
            entró a su plan. Si editas un plan, el cambio aplica a
            <strong>quienes entren desde ahora</strong>; al editar puedes elegir
            aplicarlo también a los actuales (útil cuando mejoras un plan).
            Gratis y Pro son planes base: se editan, pero no se borran.
        </GuideCard>

        <div class="flex justify-end">
            <Link
                :href="create()"
                class="bg-primary text-primary-foreground inline-flex h-10 items-center gap-2 rounded-full px-4 text-sm font-bold"
            >
                <Plus class="size-4" /> Crear plan
            </Link>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <article
                v-for="plan in plans"
                :key="plan.id"
                class="bg-card flex flex-col rounded-2xl border p-5"
                :class="!plan.is_active && 'opacity-70'"
            >
                <header class="flex items-start gap-3">
                    <div class="min-w-0 flex-1">
                        <h2
                            class="font-display flex flex-wrap items-center gap-2 text-xl font-bold"
                        >
                            {{ plan.name }}
                            <span
                                v-if="plan.protected"
                                class="bg-muted text-muted-foreground inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-bold"
                            >
                                <Lock class="size-3" /> Base
                            </span>
                            <span
                                v-if="!plan.is_active"
                                class="bg-wait-soft text-wait rounded-full px-2 py-0.5 text-[11px] font-bold"
                                >Inactivo</span
                            >
                        </h2>
                        <p class="text-muted-foreground font-mono text-xs">
                            {{ plan.code }}
                        </p>
                    </div>
                    <Link
                        :href="edit(plan.id)"
                        class="bg-muted hover:bg-muted/70 inline-flex h-9 items-center gap-1.5 rounded-full px-3.5 text-sm font-bold"
                    >
                        <Pencil class="size-3.5" /> Editar
                    </Link>
                </header>

                <p
                    v-if="plan.description"
                    class="text-muted-foreground mt-2 text-sm"
                >
                    {{ plan.description }}
                </p>

                <p class="mt-3 text-sm">
                    <template
                        v-if="plan.prices.filter((p) => p.is_active).length"
                    >
                        <span
                            v-for="(p, i) in plan.prices.filter(
                                (p) => p.is_active,
                            )"
                            :key="p.id ?? i"
                        >
                            <strong>{{ p.label }}</strong
                            ><span
                                v-if="
                                    i <
                                    plan.prices.filter((p) => p.is_active)
                                        .length -
                                        1
                                "
                            >
                                ·
                            </span>
                        </span>
                    </template>
                    <strong v-else>Sin costo</strong>
                </p>

                <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                    <div
                        v-for="l in planLimits"
                        :key="l.key"
                        class="flex flex-col"
                    >
                        <dt class="text-muted-foreground text-xs">
                            {{ l.label }} {{ l.unit }}
                        </dt>
                        <dd class="font-bold tabular-nums">
                            {{ plan[l.key] ?? 'Sin límite' }}
                        </dd>
                    </div>
                </dl>

                <ul class="mt-4 flex flex-wrap gap-1.5">
                    <li
                        v-for="c in planCapabilities"
                        :key="c.key"
                        class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-bold"
                        :class="
                            plan[c.key]
                                ? 'bg-ok-soft text-ok'
                                : 'bg-muted text-muted-foreground line-through decoration-1'
                        "
                    >
                        <Check
                            v-if="plan[c.key]"
                            class="size-3"
                            stroke-width="3"
                        />
                        <X v-else class="size-3" />
                        {{ c.label }}
                    </li>
                </ul>

                <p class="text-muted-foreground mt-auto pt-4 text-xs">
                    {{ plan.activeSubscriptions ?? 0 }}
                    {{
                        plan.activeSubscriptions === 1
                            ? 'negocio lo usa'
                            : 'negocios lo usan'
                    }}
                    hoy
                </p>
            </article>
        </div>
    </div>
</template>
