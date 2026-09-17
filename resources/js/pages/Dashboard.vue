<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Check, ExternalLink, FileCheck, Sparkles } from '@lucide/vue';
import { computed } from 'vue';
import GuideCard from '@/components/guide/GuideCard.vue';
import HelpHint from '@/components/guide/HelpHint.vue';
import { dashboard } from '@/routes';
import { publish, show as businessShow } from '@/routes/business';
import { reset as resetGuides } from '@/routes/guides';

type ChecklistItem = { label: string; done: boolean };

type BusinessSummary = {
    name: string;
    slug: string;
    offering: string;
    offeringLabel: string;
    activation: number;
    published: boolean;
    minToPublish: number;
    checklist: ChecklistItem[];
    plan: { name: string; onTrial: boolean; trialDaysLeft: number; pricing: string; founder: boolean; freeMode: boolean };
    needsDocument: string | null;
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Panel de mi negocio', href: dashboard() }],
    },
});

const props = defineProps<{ business: BusinessSummary }>();

const page = usePage();
const firstName = computed(() => String(page.props.auth.user?.name ?? '').split(' ')[0]);

/**
 * Cómo se hace cada punto de la lista. Los que todavía no tienen pantalla lo
 * dicen claro en vez de mandar a un botón que no hace nada.
 */
const howTo: Record<string, { text: string; soon?: boolean; href?: string; action?: string }> = {
    'Nombre y usuario': { text: 'Ya lo hiciste al abrir tu negocio.' },
    Presentación: { text: 'Tu frase aparece arriba de tu perfil.', href: publish({ query: { tab: 'perfil' } }).url, action: 'Editar' },
    Categoría: { text: 'Ayuda a que te encuentren en Descubrir.' },
    'Foto de perfil': { text: 'Sube tu logo o una foto clara de lo que vendes.', href: publish({ query: { tab: 'perfil' } }).url, action: 'Subir foto' },
    'Zona o dirección': { text: 'Te ubica en las búsquedas por zona.' },
    Horario: { text: 'Muestra "Abierto ahora" a tus clientes.' },
    'Cómo te pagan': { text: 'Tus clientes ven cómo pagarte directo.' },
    'Un producto': { text: 'Nombre, precio en quetzales y foto. Con uno ya puedes vender.', soon: true },
    'Cómo entregas': { text: 'Recoger, entrega propia o paquetería.' },
    'Un servicio': { text: 'Nombre, duración y precio (o "cotizar").', soon: true },
    'Dónde atiendes': { text: 'En tu local, a domicilio o en línea.' },
};

const pending = computed(() => props.business.checklist.filter((i) => !i.done).length);
</script>

<template>
    <Head title="Panel de mi negocio" />

    <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-8">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-muted-foreground text-xs font-bold tracking-[0.14em] uppercase">Modo negocio</p>
                <h1 class="font-display mt-1 text-3xl leading-tight font-extrabold md:text-4xl">Hola, {{ firstName }}.</h1>
                <p class="text-muted-foreground mt-1">
                    Estás administrando <strong class="text-foreground">{{ business.name }}</strong>.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <Link
                    :href="businessShow(business.slug)"
                    class="bg-foreground text-background inline-flex h-10 items-center gap-2 rounded-full px-4 text-sm font-bold"
                >
                    <ExternalLink class="size-4" /> Ver mi perfil público
                </Link>
                <Link :href="resetGuides()" as="button" preserve-scroll class="text-muted-foreground hover:text-foreground text-xs font-bold">
                    Volver a ver las guías
                </Link>
            </div>
        </div>

        <GuideCard
            guide-key="dashboard.business"
            title="Este es el panel de tu negocio"
            :steps="[
                { title: 'Completa tu perfil', text: 'Sigue la lista de abajo. Al llegar al 100 % empiezan tus días de Pro gratis.' },
                { title: 'Cambia de modo', text: 'Arriba a la izquierda eliges si usas la app para comprar o para administrar tu negocio.' },
                { title: 'Comparte tu perfil', text: `Tus clientes te encuentran como @${business.slug}. Mándalo por WhatsApp.` },
            ]"
        >
            Aquí ves lo que le falta a tu negocio para empezar a vender, en orden, y
            cómo se hace cada cosa.
        </GuideCard>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
            <section class="bg-card rounded-2xl border p-5 md:p-6">
                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex-1">
                        <h2 class="font-display text-xl font-bold">Primeros pasos</h2>
                        <p class="text-muted-foreground text-sm">
                            <template v-if="pending">Te faltan {{ pending }} para tener tu perfil completo.</template>
                            <template v-else>¡Tu perfil está completo!</template>
                        </p>
                    </div>
                    <span class="font-display text-3xl font-extrabold tabular-nums">{{ business.activation }}%</span>
                </div>

                <div class="bg-muted relative mt-4 h-2.5 overflow-hidden rounded-full">
                    <div class="bg-primary h-full rounded-full transition-[width] duration-700" :style="{ width: `${business.activation}%` }" />
                    <span class="bg-foreground/40 absolute inset-y-0 w-0.5" :style="{ left: `${business.minToPublish}%` }" aria-hidden="true" />
                </div>
                <p class="text-muted-foreground mt-2 text-xs">
                    <template v-if="business.published">Ya apareces en búsquedas.</template>
                    <template v-else>Al {{ business.minToPublish }} % empiezas a aparecer en búsquedas.</template>
                </p>

                <ul class="divide-border mt-5 divide-y">
                    <li v-for="item in business.checklist" :key="item.label" class="flex items-start gap-3 py-3.5">
                        <span
                            class="mt-0.5 grid size-6 flex-none place-items-center rounded-full"
                            :class="item.done ? 'bg-ok text-white dark:text-[#0d1411]' : 'border-border border-2'"
                        >
                            <Check v-if="item.done" class="size-3.5" stroke-width="3" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="font-bold" :class="item.done ? 'text-muted-foreground line-through decoration-1' : ''">{{ item.label }}</p>
                            <p class="text-muted-foreground text-sm">{{ howTo[item.label]?.text }}</p>
                        </div>
                        <Link
                            v-if="!item.done && howTo[item.label]?.href"
                            :href="howTo[item.label].href!"
                            class="bg-primary text-primary-foreground flex-none rounded-full px-3.5 py-1.5 text-xs font-bold"
                        >
                            {{ howTo[item.label].action }}
                        </Link>
                        <span v-else-if="!item.done && howTo[item.label]?.soon" class="bg-wait-soft text-wait flex-none rounded-full px-2.5 py-1 text-[11px] font-bold">Muy pronto</span>
                    </li>
                </ul>
            </section>

            <aside class="flex flex-col gap-4">
                <div class="bg-card rounded-2xl border p-5">
                    <p class="text-muted-foreground text-xs font-bold tracking-[0.12em] uppercase">Tu plan</p>
                    <p class="font-display mt-1 text-2xl font-extrabold">{{ business.plan.name }}</p>
                    <div v-if="business.plan.founder" class="bg-ok-soft text-ok mt-3 rounded-xl px-3 py-2.5 text-sm">
                        <p class="flex items-center gap-1.5 font-bold"><Sparkles class="size-4" /> Negocio fundador</p>
                        <p class="mt-0.5">Llegaste a Silvestre desde el principio: publicas, vendes y usas todo sin límites, para siempre.</p>
                    </div>
                    <p v-else-if="business.plan.freeMode" class="bg-ok-soft text-ok mt-3 rounded-xl px-3 py-2 text-sm font-bold">
                        Por ahora no tienes límites.
                    </p>
                    <p v-if="business.plan.onTrial" class="text-ok mt-1 text-sm font-bold">
                        Prueba: te quedan {{ business.plan.trialDaysLeft }} días
                    </p>
                    <p v-else-if="business.plan.name === 'Gratis'" class="text-muted-foreground mt-1 text-sm">
                        Tienes todas las funciones. Pro es opcional.
                    </p>
                    <HelpHint label="¿Qué cambia con Pro?" class="mt-3">
                        <strong>Todas las funciones son gratis</strong>: vender, publicar,
                        historias y más. Pro no desbloquea nada; te da
                        <strong>alcance</strong> (salir como Promocionado en el Inicio de
                        quienes aún no te siguen) y <strong>quita los topes</strong> de
                        publicaciones, historias y catálogo. Cuesta
                        {{ business.plan.pricing }}, por transferencia a Banco Industrial.
                    </HelpHint>
                </div>

                <div v-if="business.needsDocument" class="bg-wait-soft text-wait rounded-2xl p-5">
                    <p class="flex items-center gap-2 font-bold"><FileCheck class="size-4" /> Verificación pendiente</p>
                    <p class="mt-1 text-sm">
                        Tu categoría está regulada. Tendrás que subir:
                        <strong>{{ business.needsDocument }}</strong>. La subida de documentos
                        llega muy pronto.
                    </p>
                </div>
            </aside>
        </div>
    </div>
</template>
