<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { ShieldCheck } from '@lucide/vue';
import { ref } from 'vue';
import AdminNav from '@/components/admin/AdminNav.vue';
import PagerLinks from '@/components/admin/PagerLinks.vue';
import SearchBox from '@/components/admin/SearchBox.vue';
import InputError from '@/components/InputError.vue';
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
import { admin as adminRoute, index as usersIndex } from '@/routes/admin/users';
import type { Paginated } from '@/types/admin';

type Row = {
    id: number;
    name: string;
    email: string;
    isAdmin: boolean;
    businesses: number;
    since: string;
    isYou: boolean;
};

defineProps<{ users: Paginated<Row>; filters: { q: string } }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Administración', href: adminIndex() },
            { title: 'Personas', href: usersIndex() },
        ],
    },
});

const page = usePage();
const target = ref<Row | null>(null);
const saving = ref(false);

function toggle(): void {
    if (!target.value) return;
    router.post(
        adminRoute(target.value.id).url,
        {},
        {
            preserveScroll: true,
            onStart: () => (saving.value = true),
            onFinish: () => {
                saving.value = false;
                target.value = null;
            },
        },
    );
}

function initials(name: string): string {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0])
        .join('')
        .toUpperCase();
}
</script>

<template>
    <Head title="Personas" />

    <div class="mx-auto flex w-full max-w-5xl flex-col gap-5 px-4 py-5 md:py-8">
        <AdminNav>Personas</AdminNav>

        <p class="text-muted-foreground -mt-1 max-w-prose text-sm">
            Un administrador puede cambiar planes y precios, encender o apagar
            "Todo libre" y dar este mismo acceso a otras personas. Dáselo solo a
            quien trabaja contigo.
        </p>

        <SearchBox
            :url="usersIndex().url"
            :value="filters.q"
            placeholder="Buscar por nombre o correo"
        />
        <InputError
            :message="(page.props.errors as Record<string, string>).user"
        />

        <ul
            v-if="users.data.length"
            class="bg-card divide-border divide-y overflow-hidden rounded-2xl border"
        >
            <li
                v-for="u in users.data"
                :key="u.id"
                class="flex flex-wrap items-center gap-x-4 gap-y-2 p-4"
            >
                <span
                    class="bg-muted grid size-10 flex-none place-items-center rounded-full text-sm font-bold"
                    >{{ initials(u.name) }}</span
                >
                <div class="min-w-0 flex-1 basis-48">
                    <p class="flex flex-wrap items-center gap-x-2 font-bold">
                        <span class="truncate">{{ u.name }}</span>
                        <span
                            v-if="u.isYou"
                            class="text-muted-foreground text-xs font-normal"
                            >(tú)</span
                        >
                        <span
                            v-if="u.isAdmin"
                            class="bg-primary/10 text-primary inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-bold"
                        >
                            <ShieldCheck class="size-3" /> Administrador
                        </span>
                    </p>
                    <p class="text-muted-foreground truncate text-xs">
                        {{ u.email }} · desde {{ u.since
                        }}<template v-if="u.businesses">
                            · {{ u.businesses }}
                            {{
                                u.businesses === 1 ? 'negocio' : 'negocios'
                            }}</template
                        >
                    </p>
                </div>
                <Button
                    v-if="!u.isYou"
                    size="sm"
                    :variant="u.isAdmin ? 'outline' : 'default'"
                    class="rounded-full max-sm:ml-14"
                    @click="target = u"
                >
                    {{ u.isAdmin ? 'Quitar acceso' : 'Dar acceso' }}
                </Button>
            </li>
        </ul>
        <p
            v-else
            class="text-muted-foreground rounded-2xl border border-dashed p-8 text-center text-sm"
        >
            No hay personas que coincidan.
        </p>

        <PagerLinks :page="users" />
    </div>

    <Dialog :open="target !== null" @update:open="(v) => !v && (target = null)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{
                    target?.isAdmin
                        ? `¿Quitarle el acceso a ${target?.name}?`
                        : `¿Dar acceso a ${target?.name}?`
                }}</DialogTitle>
                <DialogDescription>
                    <template v-if="target?.isAdmin"
                        >Dejará de ver el panel de administración. Su cuenta y
                        sus negocios siguen igual.</template
                    >
                    <template v-else
                        >Podrá cambiar planes y precios, "Todo libre" y los
                        permisos de otras personas.</template
                    >
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="ghost" class="rounded-full"
                        >Cancelar</Button
                    >
                </DialogClose>
                <Button
                    class="rounded-full"
                    :variant="target?.isAdmin ? 'destructive' : 'default'"
                    :disabled="saving"
                    @click="toggle"
                >
                    <Spinner v-if="saving" />
                    {{ target?.isAdmin ? 'Quitar acceso' : 'Dar acceso' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
