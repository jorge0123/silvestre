<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Gauge, Layers, Store, UsersRound } from '@lucide/vue';
import { index as adminIndex } from '@/routes/admin';
import { index as businessesIndex } from '@/routes/admin/businesses';
import { index as plansIndex } from '@/routes/admin/plans';
import { index as usersIndex } from '@/routes/admin/users';

const page = usePage();

const items = [
    { label: 'Resumen', href: adminIndex().url, icon: Gauge, exact: true },
    { label: 'Planes', href: plansIndex().url, icon: Layers },
    { label: 'Negocios', href: businessesIndex().url, icon: Store },
    { label: 'Personas', href: usersIndex().url, icon: UsersRound },
];

function isActive(item: (typeof items)[number]): boolean {
    const path = page.url.split('?')[0];
    const target = new URL(item.href, 'http://x').pathname;

    return item.exact ? path === target : path.startsWith(target);
}
</script>

<template>
    <div>
        <p class="text-muted-foreground text-xs font-bold tracking-[0.14em] uppercase">Equipo Silvestre</p>
        <h1 class="font-display mt-1 text-3xl font-extrabold"><slot /></h1>

        <!-- En el celular: cuatro botones iguales, ícono arriba y nombre abajo. -->
        <nav class="mt-4" aria-label="Secciones de administración">
            <div class="bg-muted grid grid-cols-4 gap-1 rounded-2xl p-1 sm:inline-flex">
                <Link
                    v-for="item in items"
                    :key="item.label"
                    :href="item.href"
                    :aria-current="isActive(item) ? 'page' : undefined"
                    class="flex min-w-0 flex-col items-center justify-center gap-0.5 rounded-xl px-1 py-2 text-xs font-bold transition-all sm:flex-row sm:gap-1.5 sm:px-3.5 sm:text-sm"
                    :class="isActive(item) ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                >
                    <component :is="item.icon" class="size-4" />
                    {{ item.label }}
                </Link>
            </div>
        </nav>
    </div>
</template>
