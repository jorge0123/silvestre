<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { FileCheck, Layers, Package, Wrench } from '@lucide/vue';
import { computed, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import ChoiceCard from '@/components/onboarding/ChoiceCard.vue';
import StepActions from '@/components/onboarding/StepActions.vue';
import { update } from '@/routes/onboarding';
import type { StepProps } from '@/types/onboarding';

const props = defineProps<StepProps>();

const form = useForm({
    offering: props.business?.offering ?? '',
    category_id: props.business?.category_id ?? null,
});

const icons: Record<string, typeof Package> = {
    productos: Package,
    servicios: Wrench,
    ambos: Layers,
};

// Solo las categorías que corresponden a lo que ofrece.
const categories = computed(() =>
    props.options.categories.filter(
        (c) =>
            form.offering &&
            (c.offering === 'ambos' ||
                form.offering === 'ambos' ||
                c.offering === form.offering),
    ),
);

const selectedCategory = computed(() =>
    props.options.categories.find((c) => c.id === form.category_id),
);

watch(
    () => form.offering,
    () => {
        if (!categories.value.some((c) => c.id === form.category_id)) {
            form.category_id = null;
        }
    },
);

function submit(): void {
    form.post(update('oferta').url, { preserveScroll: true });
}
</script>

<template>
    <form class="grid gap-7" @submit.prevent="submit">
        <fieldset class="grid gap-3">
            <legend class="mb-1 text-sm font-medium">Tu negocio ofrece…</legend>
            <ChoiceCard
                v-for="offering in options.offerings"
                :key="offering.value"
                :selected="form.offering === offering.value"
                :title="offering.label"
                :description="offering.description"
                :icon="icons[offering.value]"
                @select="form.offering = offering.value as typeof form.offering"
            />
            <InputError :message="form.errors.offering" />
        </fieldset>

        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0 translate-y-2"
        >
            <fieldset v-if="form.offering" class="grid gap-3">
                <legend class="mb-1 text-sm font-medium">
                    ¿En qué categoría te buscarían?
                </legend>
                <div class="flex flex-wrap gap-2" role="radiogroup">
                    <button
                        v-for="category in categories"
                        :key="category.id"
                        type="button"
                        role="radio"
                        :aria-checked="form.category_id === category.id"
                        class="rounded-full border px-4 py-2 text-sm font-bold transition-colors"
                        :class="
                            form.category_id === category.id
                                ? 'border-foreground bg-foreground text-background'
                                : 'border-border bg-card text-muted-foreground hover:text-foreground'
                        "
                        @click="form.category_id = category.id"
                    >
                        {{ category.name }}
                    </button>
                </div>
                <InputError :message="form.errors.category_id" />

                <div
                    v-if="selectedCategory?.requires_verification"
                    class="bg-wait-soft text-wait mt-1 flex gap-3 rounded-xl p-4 text-sm"
                >
                    <FileCheck class="mt-0.5 size-5 flex-none" />
                    <p>
                        <strong>Esta categoría está regulada.</strong> Para
                        verificar tu negocio te vamos a pedir:
                        <strong>{{ selectedCategory.required_document }}</strong
                        >. Puedes terminar tu perfil ahora y subirlo después.
                    </p>
                </div>
            </fieldset>
        </Transition>

        <StepActions :back-href="backHref" :processing="form.processing" />
    </form>
</template>
