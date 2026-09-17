<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Check, ChevronDown, CircleHelp, Lightbulb } from '@lucide/vue';
import { computed, ref } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import StepFulfillment from '@/components/onboarding/StepFulfillment.vue';
import StepIdentity from '@/components/onboarding/StepIdentity.vue';
import StepLocation from '@/components/onboarding/StepLocation.vue';
import StepOffering from '@/components/onboarding/StepOffering.vue';
import StepPayments from '@/components/onboarding/StepPayments.vue';
import StepPresentation from '@/components/onboarding/StepPresentation.vue';
import StepRules from '@/components/onboarding/StepRules.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import { onboardingGuide } from '@/lib/onboardingGuide';
import { personal } from '@/routes/mode';
import { show } from '@/routes/onboarding';
import type {
    OnboardingBusiness,
    OnboardingOptions,
    OnboardingStep,
} from '@/types/onboarding';

const props = defineProps<{
    step: OnboardingStep;
    reached: OnboardingStep;
    steps: OnboardingStep[];
    business: OnboardingBusiness | null;
    options: OnboardingOptions;
}>();

const components = {
    negocio: StepIdentity,
    oferta: StepOffering,
    ubicacion: StepLocation,
    entrega: StepFulfillment,
    cobros: StepPayments,
    presentacion: StepPresentation,
    reglas: StepRules,
};

const index = computed(() => props.steps.indexOf(props.step));
const reachedIndex = computed(() => props.steps.indexOf(props.reached));
const guide = computed(() => onboardingGuide[props.step]);
const progress = computed(() => ((index.value + 1) / props.steps.length) * 100);

const backHref = computed(() =>
    index.value > 0
        ? show({ query: { paso: props.steps[index.value - 1] } }).url
        : null,
);

// En el celular la guía empieza cerrada para no empujar el formulario hacia abajo.
const guideOpen = ref(false);
</script>

<template>
    <Head :title="`Abrir mi negocio · ${guide.short}`" />

    <div class="bg-background min-h-svh">
        <header class="bg-background/85 border-border sticky top-0 z-20 border-b backdrop-blur">
            <div class="mx-auto flex max-w-5xl items-center gap-3 px-4 py-3 sm:px-6">
                <AppLogoIcon class="size-9 flex-none" />
                <div class="min-w-0 flex-1">
                    <p class="font-display truncate leading-tight font-bold">Abrir mi negocio</p>
                    <p class="text-muted-foreground text-xs">
                        Paso {{ index + 1 }} de {{ steps.length }} · se guarda solo
                    </p>
                </div>
                <ThemeToggle />
                <Link
                    :href="personal()"
                    as="button"
                    class="text-muted-foreground hover:text-foreground hidden rounded-full px-3 py-2 text-sm font-bold sm:block"
                >
                    Salir y solo comprar
                </Link>
            </div>

            <!-- Progreso -->
            <div class="bg-muted h-1">
                <div
                    class="bg-primary h-full transition-[width] duration-500 ease-out"
                    :style="{ width: `${progress}%` }"
                />
            </div>
        </header>

        <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:py-10">
            <!-- Mapa de pasos: se puede volver a los ya hechos -->
            <nav aria-label="Pasos" class="mb-8 hidden md:block">
                <ol class="flex items-center gap-1">
                    <li v-for="(key, i) in steps" :key="key" class="flex flex-1 items-center gap-1">
                        <Link
                            v-if="i <= reachedIndex && i !== index"
                            :href="show({ query: { paso: key } })"
                            class="group flex items-center gap-2 rounded-full py-1 pr-2 text-xs font-bold"
                        >
                            <span class="bg-brand text-brand-foreground grid size-6 place-items-center rounded-full">
                                <Check class="size-3.5" stroke-width="3" />
                            </span>
                            <span class="text-muted-foreground group-hover:text-foreground">{{ onboardingGuide[key].short }}</span>
                        </Link>
                        <span
                            v-else
                            class="flex items-center gap-2 py-1 pr-2 text-xs font-bold"
                            :aria-current="i === index ? 'step' : undefined"
                        >
                            <span
                                class="grid size-6 place-items-center rounded-full tabular-nums"
                                :class="i === index ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'"
                                >{{ i + 1 }}</span
                            >
                            <span :class="i === index ? 'text-foreground' : 'text-muted-foreground'">{{ onboardingGuide[key].short }}</span>
                        </span>
                        <span v-if="i < steps.length - 1" class="bg-border h-px flex-1" />
                    </li>
                </ol>
            </nav>

            <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_300px] lg:gap-12">
                <main :key="step" class="animate-in fade-in slide-in-from-bottom-2 min-w-0 duration-300">
                    <p class="text-primary text-xs font-bold tracking-[0.14em] uppercase">
                        Paso {{ index + 1 }} · {{ guide.short }}
                    </p>
                    <h1 class="font-display mt-2 text-[clamp(1.8rem,4vw,2.4rem)] leading-[1.08] font-extrabold">
                        {{ guide.title }}
                    </h1>
                    <p class="text-muted-foreground mt-2 text-[16px]">{{ guide.lead }}</p>

                    <!-- Guía en el celular: plegable -->
                    <div class="bg-brand-soft mt-5 rounded-xl lg:hidden">
                        <button
                            type="button"
                            class="text-brand flex w-full items-center gap-2 px-4 py-3 text-sm font-bold"
                            :aria-expanded="guideOpen"
                            @click="guideOpen = !guideOpen"
                        >
                            <CircleHelp class="size-4" />
                            ¿Para qué es esto?
                            <ChevronDown class="ml-auto size-4 transition-transform" :class="{ 'rotate-180': guideOpen }" />
                        </button>
                        <div v-if="guideOpen" class="text-foreground/85 space-y-2 px-4 pb-4 text-sm">
                            <p><strong>Qué es:</strong> {{ guide.what }}</p>
                            <p><strong>Por qué lo pedimos:</strong> {{ guide.why }}</p>
                            <ul class="list-disc space-y-1 pl-5">
                                <li v-for="tip in guide.tips" :key="tip">{{ tip }}</li>
                            </ul>
                        </div>
                    </div>

                    <div class="mt-7">
                        <component
                            :is="components[step]"
                            :business="business"
                            :options="options"
                            :back-href="backHref"
                        />
                    </div>

                    <Link
                        :href="personal()"
                        as="button"
                        class="text-muted-foreground mx-auto mt-6 block text-sm sm:hidden"
                    >
                        Salir y solo comprar por ahora
                    </Link>
                </main>

                <!-- Guía en escritorio: siempre visible junto al formulario -->
                <aside class="hidden lg:block">
                    <div class="bg-card sticky top-28 rounded-2xl border p-5">
                        <p class="text-brand flex items-center gap-2 text-xs font-bold tracking-[0.14em] uppercase">
                            <Lightbulb class="size-4" /> Guía
                        </p>
                        <h2 class="font-display mt-3 text-sm font-bold">Qué es</h2>
                        <p class="text-muted-foreground mt-1 text-sm leading-relaxed">{{ guide.what }}</p>
                        <h2 class="font-display mt-4 text-sm font-bold">Por qué lo pedimos</h2>
                        <p class="text-muted-foreground mt-1 text-sm leading-relaxed">{{ guide.why }}</p>
                        <h2 class="font-display mt-4 text-sm font-bold">Consejos</h2>
                        <ul class="mt-2 space-y-2">
                            <li v-for="tip in guide.tips" :key="tip" class="text-muted-foreground flex gap-2 text-sm leading-relaxed">
                                <span class="bg-primary mt-2 size-1.5 flex-none rounded-full" />
                                {{ tip }}
                            </li>
                        </ul>
                    </div>
                </aside>
            </div>
        </div>
    </div>
</template>
