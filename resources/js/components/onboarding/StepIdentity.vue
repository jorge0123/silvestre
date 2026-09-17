<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Building2, User } from '@lucide/vue';
import { ref, watch } from 'vue';
import HelpHint from '@/components/guide/HelpHint.vue';
import InputError from '@/components/InputError.vue';
import ChoiceCard from '@/components/onboarding/ChoiceCard.vue';
import StepActions from '@/components/onboarding/StepActions.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { update } from '@/routes/onboarding';
import type { StepProps } from '@/types/onboarding';

const props = defineProps<StepProps>();

const form = useForm({
    name: props.business?.name ?? '',
    slug: props.business?.slug ?? '',
    kind: props.business?.kind ?? 'negocio',
    legal_name: props.business?.legal_name ?? '',
    tax_id: props.business?.tax_id ?? '',
});

const kindIcons: Record<string, typeof User> = {
    negocio: User,
    empresa: Building2,
};

// Sugerimos el usuario a partir del nombre hasta que la persona lo edite.
const slugTouched = ref(Boolean(props.business?.slug));

function toSlug(value: string): string {
    return value
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '')
        .slice(0, 30);
}

watch(
    () => form.name,
    (name) => {
        if (!slugTouched.value) {
            form.slug = toSlug(name);
        }
    },
);

function submit(): void {
    form.post(update('negocio').url, { preserveScroll: true });
}
</script>

<template>
    <form class="grid gap-6" @submit.prevent="submit">
        <div class="grid gap-2">
            <Label for="name">Nombre del negocio</Label>
            <Input
                id="name"
                v-model="form.name"
                class="h-11"
                placeholder="Ej. Galletas de la Abuela Chela"
                autocomplete="organization"
                autofocus
                required
            />
            <InputError :message="form.errors.name" />
        </div>

        <div class="grid gap-2">
            <Label for="slug">Tu usuario</Label>
            <div class="relative">
                <span
                    class="text-muted-foreground pointer-events-none absolute inset-y-0 left-3 flex items-center font-bold"
                    >@</span
                >
                <Input
                    id="slug"
                    v-model="form.slug"
                    class="h-11 pl-8"
                    placeholder="galletasdechela"
                    autocapitalize="none"
                    spellcheck="false"
                    required
                    @input="slugTouched = true"
                />
            </div>
            <InputError :message="form.errors.slug" />
            <HelpHint>
                Es tu dirección en Silvestre. Cuando compartas tu negocio por
                WhatsApp, tus clientes lo abrirán con este usuario. Usa
                minúsculas y números, sin espacios.
            </HelpHint>
        </div>

        <fieldset class="grid gap-3">
            <legend class="mb-1 text-sm font-medium">¿Qué tipo de negocio es?</legend>
            <div class="grid gap-3 sm:grid-cols-2">
                <ChoiceCard
                    v-for="kind in options.kinds"
                    :key="kind.value"
                    :selected="form.kind === kind.value"
                    :title="kind.label"
                    :description="kind.description"
                    :icon="kindIcons[kind.value]"
                    @select="form.kind = kind.value"
                />
            </div>
            <InputError :message="form.errors.kind" />
        </fieldset>

        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 -translate-y-1"
        >
            <div
                v-if="form.kind === 'empresa'"
                class="bg-muted/60 grid gap-5 rounded-xl p-4 sm:grid-cols-2"
            >
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="legal_name">Razón social</Label>
                    <Input
                        id="legal_name"
                        v-model="form.legal_name"
                        class="h-11"
                        placeholder="Como aparece en tu patente de comercio"
                    />
                    <InputError :message="form.errors.legal_name" />
                </div>
                <div class="grid gap-2">
                    <Label for="tax_id">NIT</Label>
                    <Input
                        id="tax_id"
                        v-model="form.tax_id"
                        class="h-11"
                        placeholder="1234567-8"
                        inputmode="text"
                    />
                    <InputError :message="form.errors.tax_id" />
                </div>
            </div>
        </Transition>

        <StepActions :back-href="backHref" :processing="form.processing" />
    </form>
</template>
