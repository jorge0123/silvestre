<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Check, ChevronsUpDown, Plus, ShoppingBag, Store, Wrench } from '@lucide/vue';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { business as businessMode, personal } from '@/routes/mode';
import { show as onboarding } from '@/routes/onboarding';

/**
 * Selector de modo: la misma cuenta compra en modo personal y administra sus
 * negocios en modo negocio. Vive arriba del menú para que siempre se sepa
 * "como quién" se está usando la app.
 */
const page = usePage();
const { isMobile, state } = useSidebar();

const mode = computed(() => page.props.auth.mode);
const businesses = computed(() => page.props.auth.businesses ?? []);
const unfinished = computed(() => businesses.value.find((b) => !b.finished));
const isPersonal = computed(() => mode.value?.type !== 'business');
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton
                        size="lg"
                        class="bg-sidebar-accent/40 data-[state=open]:bg-sidebar-accent border-sidebar-border border"
                        data-test="mode-switcher"
                    >
                        <span
                            class="grid size-8 flex-none place-items-center rounded-lg"
                            :class="isPersonal ? 'bg-muted text-foreground' : 'bg-primary text-primary-foreground'"
                        >
                            <ShoppingBag v-if="isPersonal" class="size-4" />
                            <Store v-else class="size-4" />
                        </span>
                        <span class="grid min-w-0 flex-1 text-left leading-tight">
                            <span class="text-muted-foreground text-[11px] font-bold tracking-wide uppercase">
                                {{ isPersonal ? 'Modo personal' : 'Modo negocio' }}
                            </span>
                            <span class="truncate text-sm font-bold">
                                {{ isPersonal ? 'Comprar y seguir' : mode?.business?.name }}
                            </span>
                        </span>
                        <ChevronsUpDown class="ml-auto size-4 opacity-60" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>

                <DropdownMenuContent
                    class="w-(--reka-dropdown-menu-trigger-width) min-w-64 rounded-xl"
                    :side="isMobile ? 'bottom' : state === 'collapsed' ? 'right' : 'bottom'"
                    align="start"
                    :side-offset="6"
                >
                    <DropdownMenuLabel class="text-muted-foreground text-xs">
                        Usar Silvestre como…
                    </DropdownMenuLabel>

                    <DropdownMenuItem as-child>
                        <Link :href="personal()" as="button" class="flex w-full cursor-pointer items-center gap-2.5">
                            <ShoppingBag class="size-4" />
                            <span class="flex-1 text-left">
                                <span class="block font-bold">Yo, para comprar</span>
                                <span class="text-muted-foreground block text-xs">Buscar negocios, pedir y reseñar</span>
                            </span>
                            <Check v-if="isPersonal" class="text-primary size-4" />
                        </Link>
                    </DropdownMenuItem>

                    <template v-for="b in businesses" :key="b.id">
                        <DropdownMenuItem v-if="b.finished" as-child>
                            <Link :href="businessMode(b.slug)" as="button" class="flex w-full cursor-pointer items-center gap-2.5">
                                <Store class="size-4" />
                                <span class="flex-1 truncate text-left font-bold">{{ b.name }}</span>
                                <Check v-if="mode?.business?.id === b.id" class="text-primary size-4" />
                            </Link>
                        </DropdownMenuItem>
                    </template>

                    <DropdownMenuSeparator />

                    <DropdownMenuItem as-child>
                        <Link :href="onboarding()" class="flex w-full cursor-pointer items-center gap-2.5">
                            <Wrench v-if="unfinished" class="size-4" />
                            <Plus v-else class="size-4" />
                            <span class="flex-1">
                                <span class="block font-bold">
                                    {{ unfinished ? `Terminar de configurar ${unfinished.name}` : 'Abrir un negocio' }}
                                </span>
                                <span class="text-muted-foreground block text-xs">
                                    {{ unfinished ? 'Continúa donde te quedaste' : 'Productos, servicios o ambos · 7 pasos' }}
                                </span>
                            </span>
                        </Link>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
