<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { MapPin } from '@lucide/vue';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import InputError from '@/components/InputError.vue';
import StepActions from '@/components/onboarding/StepActions.vue';
import { Label } from '@/components/ui/label';
import { textareaClass } from '@/lib/formClasses';
import { update } from '@/routes/onboarding';
import type { StepProps } from '@/types/onboarding';

const props = defineProps<StepProps>();

const form = useForm({
    intro: props.business?.intro ?? '',
    about: props.business?.about ?? '',
});

const zoneName = computed(
    () =>
        props.options.zones.find((z) => z.id === props.business?.zone_id)?.name,
);

function submit(): void {
    form.post(update('presentacion').url, { preserveScroll: true });
}
</script>

<template>
    <form class="grid gap-6" @submit.prevent="submit">
        <div class="grid gap-2">
            <div class="flex items-baseline justify-between">
                <Label for="intro">Tu frase de presentación</Label>
                <span
                    class="text-muted-foreground text-xs tabular-nums"
                    :class="{ 'text-destructive': form.intro.length > 160 }"
                    >{{ form.intro.length }}/160</span
                >
            </div>
            <textarea
                id="intro"
                v-model="form.intro"
                :class="textareaClass"
                rows="2"
                maxlength="160"
                placeholder="Ej. Galletas horneadas en casa, en tandas pequeñas, cada jueves."
                required
            />
            <InputError :message="form.errors.intro" />
        </div>

        <div class="grid gap-2">
            <div class="flex items-baseline justify-between">
                <Label for="about">
                    Tu historia
                    <span class="text-muted-foreground font-normal"
                        >(opcional)</span
                    >
                </Label>
                <span class="text-muted-foreground text-xs tabular-nums"
                    >{{ form.about.length }}/1500</span
                >
            </div>
            <textarea
                id="about"
                v-model="form.about"
                :class="textareaClass"
                rows="5"
                maxlength="1500"
                placeholder="Cómo empezaste, qué te importa, por qué tus clientes vuelven."
            />
            <InputError :message="form.errors.about" />
        </div>

        <!-- Vista previa en vivo: así te verán -->
        <div>
            <p
                class="text-muted-foreground mb-2 text-xs font-bold tracking-[0.12em] uppercase"
            >
                Así te verán
            </p>
            <div class="bg-card overflow-hidden rounded-xl border">
                <div
                    class="h-16 bg-[linear-gradient(120deg,#0e4534,#6b2a45)]"
                />
                <div class="-mt-7 px-4 pb-4">
                    <span
                        class="bg-card grid size-14 place-items-center rounded-2xl border-4 border-[var(--card)]"
                    >
                        <AppLogoIcon class="size-11" />
                    </span>
                    <p
                        class="font-display mt-2 text-lg leading-tight font-bold"
                    >
                        {{ business?.name }}
                    </p>
                    <p class="text-muted-foreground text-sm">
                        @{{ business?.slug }}
                        <template v-if="business?.category">
                            · {{ business.category.name }}</template
                        >
                    </p>
                    <p
                        v-if="zoneName"
                        class="text-muted-foreground mt-1 flex items-center gap-1 text-xs"
                    >
                        <MapPin class="size-3" /> {{ zoneName }}
                    </p>
                    <p
                        class="mt-3 text-[15px]"
                        :class="
                            form.intro ? '' : 'text-muted-foreground italic'
                        "
                    >
                        {{ form.intro || 'Tu frase aparecerá aquí.' }}
                    </p>
                </div>
            </div>
        </div>

        <StepActions :back-href="backHref" :processing="form.processing" />
    </form>
</template>
