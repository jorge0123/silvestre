<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Copy } from '@lucide/vue';
import { computed, reactive } from 'vue';
import HelpHint from '@/components/guide/HelpHint.vue';
import InputError from '@/components/InputError.vue';
import StepActions from '@/components/onboarding/StepActions.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { selectClass } from '@/lib/formClasses';
import { update } from '@/routes/onboarding';
import type { HourRow, StepProps } from '@/types/onboarding';

const props = defineProps<StepProps>();

const form = useForm({
    zone_id: props.business?.zone_id ?? '',
    address: props.business?.address ?? '',
    address_notes: props.business?.address_notes ?? '',
    whatsapp: props.business?.whatsapp ?? '',
    hours: [] as HourRow[],
});

// Horario editable por día. Lunes a viernes de 9 a 18 como punto de partida.
type Day = { enabled: boolean; opens_at: string; closes_at: string };
const saved = props.business?.hours ?? [];

const days = reactive<Record<number, Day>>(
    Object.fromEntries(
        props.options.weekdays.map((d) => {
            const row = saved.find((h) => h.weekday === d.value);
            const defaultOn =
                saved.length === 0 && d.value >= 1 && d.value <= 5;

            return [
                d.value,
                {
                    enabled: Boolean(row) || defaultOn,
                    opens_at: row?.opens_at ?? '09:00',
                    closes_at: row?.closes_at ?? '18:00',
                },
            ];
        }),
    ),
);

// Lunes primero, domingo al final, como se piensa la semana en Guatemala.
const orderedDays = computed(() =>
    [...props.options.weekdays].sort(
        (a, b) => ((a.value + 6) % 7) - ((b.value + 6) % 7),
    ),
);

const hoursError = computed(
    () =>
        Object.entries(form.errors).find(([key]) =>
            key.startsWith('hours'),
        )?.[1],
);

function copyToAll(): void {
    const first = orderedDays.value
        .map((d) => days[d.value])
        .find((d) => d.enabled);

    if (!first) {
        return;
    }

    for (const day of Object.values(days)) {
        if (day.enabled) {
            day.opens_at = first.opens_at;
            day.closes_at = first.closes_at;
        }
    }
}

function submit(): void {
    form.transform((data) => ({
        ...data,
        hours: Object.entries(days)
            .filter(([, d]) => d.enabled)
            .map(([weekday, d]) => ({
                weekday: Number(weekday),
                opens_at: d.opens_at,
                closes_at: d.closes_at,
            })),
    })).post(update('ubicacion').url, { preserveScroll: true });
}
</script>

<template>
    <form class="grid gap-7" @submit.prevent="submit">
        <div class="grid gap-5 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="zone_id">Zona</Label>
                <select
                    id="zone_id"
                    v-model="form.zone_id"
                    :class="selectClass"
                    required
                >
                    <option value="" disabled>Elige tu zona</option>
                    <option
                        v-for="zone in options.zones"
                        :key="zone.id"
                        :value="zone.id"
                    >
                        {{ zone.name }}
                    </option>
                </select>
                <InputError :message="form.errors.zone_id" />
            </div>

            <div class="grid gap-2">
                <Label for="whatsapp">WhatsApp</Label>
                <div class="relative">
                    <span
                        class="text-muted-foreground pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm font-bold"
                        >{{ options.phonePrefix }}</span
                    >
                    <Input
                        id="whatsapp"
                        v-model="form.whatsapp"
                        class="h-11 pl-14"
                        inputmode="numeric"
                        autocomplete="tel-national"
                        placeholder="5555 1234"
                        maxlength="9"
                        required
                    />
                </div>
                <InputError :message="form.errors.whatsapp" />
            </div>

            <div class="grid gap-2 sm:col-span-2">
                <Label for="address">
                    Dirección
                    <span class="text-muted-foreground font-normal"
                        >(opcional por ahora)</span
                    >
                </Label>
                <Input
                    id="address"
                    v-model="form.address"
                    class="h-11"
                    placeholder="Ej. 12 calle 3-45"
                    autocomplete="street-address"
                />
                <InputError :message="form.errors.address" />
                <HelpHint label="¿Quién ve mi dirección?">
                    Solo aparece si los clientes recogen pedidos o si atiendes
                    en tu local. Si solo haces entregas, puedes dejarla vacía.
                </HelpHint>
            </div>
        </div>

        <fieldset class="grid gap-3">
            <div class="flex items-center justify-between gap-3">
                <legend class="text-sm font-medium">¿Qué días atiendes?</legend>
                <button
                    type="button"
                    class="text-primary inline-flex items-center gap-1.5 text-xs font-bold hover:underline"
                    @click="copyToAll"
                >
                    <Copy class="size-3.5" />
                    Mismo horario todos los días
                </button>
            </div>

            <div class="divide-border bg-card divide-y rounded-xl border">
                <div
                    v-for="weekday in orderedDays"
                    :key="weekday.value"
                    class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3"
                >
                    <label
                        class="flex min-w-32 cursor-pointer items-center gap-3"
                    >
                        <input
                            v-model="days[weekday.value].enabled"
                            type="checkbox"
                            class="accent-primary size-4"
                        />
                        <span
                            class="text-sm font-bold"
                            :class="
                                days[weekday.value].enabled
                                    ? ''
                                    : 'text-muted-foreground'
                            "
                            >{{ weekday.label }}</span
                        >
                    </label>

                    <div
                        v-if="days[weekday.value].enabled"
                        class="ml-auto flex items-center gap-2 text-sm"
                    >
                        <input
                            v-model="days[weekday.value].opens_at"
                            type="time"
                            class="border-input bg-background h-9 rounded-md border px-2 tabular-nums"
                            :aria-label="`${weekday.label}: abre`"
                        />
                        <span class="text-muted-foreground">a</span>
                        <input
                            v-model="days[weekday.value].closes_at"
                            type="time"
                            class="border-input bg-background h-9 rounded-md border px-2 tabular-nums"
                            :aria-label="`${weekday.label}: cierra`"
                        />
                    </div>
                    <span v-else class="text-muted-foreground ml-auto text-sm"
                        >Cerrado</span
                    >
                </div>
            </div>
            <InputError :message="hoursError" />
        </fieldset>

        <StepActions :back-href="backHref" :processing="form.processing" />
    </form>
</template>
