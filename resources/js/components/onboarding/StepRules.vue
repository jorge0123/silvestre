<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import {
    Ban,
    MessageSquareReply,
    PackageCheck,
    Sparkles,
    Star,
} from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import StepActions from '@/components/onboarding/StepActions.vue';
import { update } from '@/routes/onboarding';
import type { StepProps } from '@/types/onboarding';

defineProps<StepProps>();

const pricing = usePage().props.pricing.pro;

const form = useForm({ accept_policy: false });

const rules = [
    {
        icon: PackageCheck,
        title: 'Entrega lo que publicas',
        text: 'Precio, foto y descripción deben ser reales. Si algo se agota, márcalo.',
    },
    {
        icon: Star,
        title: 'Las reseñas son de quien compró',
        text: 'Solo califica quien recibió su pedido, y una vez por negocio.',
    },
    {
        icon: MessageSquareReply,
        title: 'No se borran, se responden',
        text: 'No puedes quitar una reseña, pero sí responderla una vez, en público.',
    },
];

function submit(): void {
    form.post(update('reglas').url, { preserveScroll: true });
}
</script>

<template>
    <form class="grid gap-6" @submit.prevent="submit">
        <ul class="grid gap-3">
            <li
                v-for="rule in rules"
                :key="rule.title"
                class="bg-card flex gap-3 rounded-xl border p-4"
            >
                <span
                    class="bg-brand-soft text-brand grid size-9 flex-none place-items-center rounded-lg"
                >
                    <component :is="rule.icon" class="size-[18px]" />
                </span>
                <div>
                    <p class="font-bold">{{ rule.title }}</p>
                    <p class="text-muted-foreground text-sm">{{ rule.text }}</p>
                </div>
            </li>
        </ul>

        <div
            class="border-destructive/25 bg-destructive/[0.05] rounded-xl border p-4"
        >
            <p class="text-destructive flex items-center gap-2 font-bold">
                <Ban class="size-4" /> No se puede vender en Silvestre
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
                <span
                    v-for="item in options.prohibited"
                    :key="item"
                    class="bg-card rounded-full border px-3 py-1 text-xs font-bold"
                    >{{ item }}</span
                >
            </div>
            <p class="text-muted-foreground mt-3 text-xs">
                Alimentos, salud, alcohol y servicios profesionales sí se
                permiten, pero piden su licencia o constancia de colegiado.
            </p>
        </div>

        <div class="bg-primary/[0.05] flex gap-3 rounded-xl p-4 text-sm">
            <Sparkles class="text-primary mt-0.5 size-5 flex-none" />
            <p>
                <strong>Todas las funciones son gratis, para siempre.</strong>
                Cuando tu perfil esté completo al 100 %, te regalamos
                {{ options.trialDays }} días de Pro, que te promociona en el
                Inicio de más personas. Después es opcional: {{ pricing }}.
            </p>
        </div>

        <label
            class="bg-card flex cursor-pointer items-start gap-3 rounded-xl border p-4"
        >
            <input
                v-model="form.accept_policy"
                type="checkbox"
                class="accent-primary mt-0.5 size-5 flex-none"
            />
            <span class="text-[15px]">
                Acepto las reglas de Silvestre y confirmo que lo que voy a
                vender está permitido.
            </span>
        </label>
        <InputError :message="form.errors.accept_policy" />

        <StepActions
            :back-href="backHref"
            :processing="form.processing"
            label="Abrir mi negocio"
        />
    </form>
</template>
