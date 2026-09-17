<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ExternalLink,
    House,
    ImagePlus,
    LayoutDashboard,
    ShieldCheck,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import ModeSwitcher from '@/components/ModeSwitcher.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard, feed } from '@/routes';
import { index as adminIndex } from '@/routes/admin';
import { publish, show as businessShow } from '@/routes/business';
import type { NavItem } from '@/types';

const page = usePage();
const mode = computed(() => page.props.auth.mode);

// El menú cambia según el modo: en modo negocio aparecen el panel y el perfil.
const mainNavItems = computed<NavItem[]>(() => [
    { title: 'Inicio', href: feed(), icon: House },
    ...(mode.value?.type === 'business' && mode.value.business
        ? [
              { title: 'Publicar', href: publish(), icon: ImagePlus },
              {
                  title: 'Panel de mi negocio',
                  href: dashboard(),
                  icon: LayoutDashboard,
              },
              {
                  title: 'Ver mi perfil público',
                  href: businessShow(mode.value.business.slug),
                  icon: ExternalLink,
              },
          ]
        : []),
    ...(page.props.auth.user?.is_admin
        ? [{ title: 'Administración', href: adminIndex(), icon: ShieldCheck }]
        : []),
]);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="feed()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <ModeSwitcher />
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
