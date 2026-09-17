<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    Home,
    Laptop,
    MapPin,
    Package,
    Plus,
    Store,
    Trash2,
    Truck,
} from '@lucide/vue';
import { computed } from 'vue';
import HelpHint from '@/components/guide/HelpHint.vue';
import InputError from '@/components/InputError.vue';
import ChoiceCard from '@/components/onboarding/ChoiceCard.vue';
import StepActions from '@/components/onboarding/StepActions.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { selectClass } from '@/lib/formClasses';
import { update } from '@/routes/onboarding';
import type { StepProps, ZoneFee } from '@/types/onboarding';

const props = defineProps<StepProps>();

const offering = computed(() => props.business?.offering ?? 'productos');
const sellsProducts = computed(() => offering.value !== 'servicios');
const sellsServices = computed(() => offering.value !== 'productos');

const form = useForm({
    fulfillment: [...(props.business?.fulfillment ?? [])] as string[],
    shipping_rate: props.business?.shipping_rate ?? '',
    free_shipping_from: props.business?.free_shipping_from ?? '',
    service_modes: [...(props.business?.service_modes ?? [])] as string[],
    travel_fee: props.business?.travel_fee ?? '',
    address: props.business?.address ?? '',
    delivery_zones: (props.business?.delivery_zones?.length
        ? props.business.delivery_zones
        : []) as ZoneFee[],
});

const fulfillmentIcons: Record<string, typeof Store> = {
    pickup: Store,
    local_delivery: Truck,
    shipping: Package,
};
const modeIcons: Record<string, typeof Store> = {
    at_business: Store,
    at_customer: Home,
    remote: Laptop,
};

function toggle(list: string[], value: string): void {
    const i = list.indexOf(value);
    if (i === -1) {
        list.push(value);
    } else {
        list.splice(i, 1);
    }
}

const needsAddress = computed(
    () =>
        form.fulfillment.includes('pickup') ||
        form.service_modes.includes('at_business'),
);
const needsZones = computed(
    () =>
        form.fulfillment.includes('local_delivery') ||
        form.service_modes.includes('at_customer'),
);

const availableZones = (current: number | null) =>
    props.options.zones.filter(
        (z) =>
            z.id === current ||
            !form.delivery_zones.some((row) => row.zone_id === z.id),
    );

function addZone(): void {
    form.delivery_zones.push({ zone_id: null, fee: '', min_order: '' });
}

function zoneError(i: number): string | undefined {
    const errors = form.errors as Record<string, string>;

    return (
        errors[`delivery_zones.${i}.zone_id`] ??
        errors[`delivery_zones.${i}.fee`] ??
        errors[`delivery_zones.${i}.min_order`]
    );
}

function submit(): void {
    form.post(update('entrega').url, { preserveScroll: true });
}
</script>

<template>
    <form class="grid gap-8" @submit.prevent="submit">
        <fieldset v-if="sellsProducts" class="grid gap-3">
            <legend class="mb-1 text-sm font-medium">
                ¿Cómo entregas tus productos?
                <span class="text-muted-foreground font-normal"
                    >Elige todas las que uses.</span
                >
            </legend>
            <ChoiceCard
                v-for="method in options.fulfillmentMethods"
                :key="method.value"
                multiple
                :selected="form.fulfillment.includes(method.value)"
                :title="method.label"
                :description="method.description"
                :icon="fulfillmentIcons[method.value]"
                @select="toggle(form.fulfillment, method.value)"
            />
            <InputError :message="form.errors.fulfillment" />

            <div
                v-if="form.fulfillment.includes('shipping')"
                class="bg-muted/60 grid gap-4 rounded-xl p-4 sm:grid-cols-2"
            >
                <div class="grid gap-2">
                    <Label for="shipping_rate"
                        >Costo de envío por paquetería</Label
                    >
                    <div class="relative">
                        <span
                            class="text-muted-foreground pointer-events-none absolute inset-y-0 left-3 flex items-center font-bold"
                            >{{ options.currency }}</span
                        >
                        <Input
                            id="shipping_rate"
                            v-model="form.shipping_rate"
                            type="number"
                            min="0"
                            step="0.01"
                            inputmode="decimal"
                            class="h-11 pl-8"
                            placeholder="35"
                        />
                    </div>
                    <InputError :message="form.errors.shipping_rate" />
                </div>
                <div class="grid gap-2">
                    <Label for="free_shipping_from">
                        Envío gratis desde
                        <span class="text-muted-foreground font-normal"
                            >(opcional)</span
                        >
                    </Label>
                    <div class="relative">
                        <span
                            class="text-muted-foreground pointer-events-none absolute inset-y-0 left-3 flex items-center font-bold"
                            >{{ options.currency }}</span
                        >
                        <Input
                            id="free_shipping_from"
                            v-model="form.free_shipping_from"
                            type="number"
                            min="0"
                            step="0.01"
                            inputmode="decimal"
                            class="h-11 pl-8"
                            placeholder="300"
                        />
                    </div>
                </div>
            </div>
        </fieldset>

        <fieldset v-if="sellsServices" class="grid gap-3">
            <legend class="mb-1 text-sm font-medium">
                ¿Dónde das tus servicios?
                <span class="text-muted-foreground font-normal"
                    >Elige todas las que apliquen.</span
                >
            </legend>
            <ChoiceCard
                v-for="mode in options.serviceModes"
                :key="mode.value"
                multiple
                :selected="form.service_modes.includes(mode.value)"
                :title="mode.label"
                :description="mode.description"
                :icon="modeIcons[mode.value]"
                @select="toggle(form.service_modes, mode.value)"
            />
            <InputError :message="form.errors.service_modes" />

            <div
                v-if="form.service_modes.includes('at_customer')"
                class="bg-muted/60 grid gap-2 rounded-xl p-4"
            >
                <Label for="travel_fee">Costo por ir a domicilio</Label>
                <div class="relative max-w-xs">
                    <span
                        class="text-muted-foreground pointer-events-none absolute inset-y-0 left-3 flex items-center font-bold"
                        >{{ options.currency }}</span
                    >
                    <Input
                        id="travel_fee"
                        v-model="form.travel_fee"
                        type="number"
                        min="0"
                        step="0.01"
                        inputmode="decimal"
                        class="h-11 pl-8"
                        placeholder="0"
                    />
                </div>
                <p class="text-muted-foreground text-xs">
                    Escribe 0 si no cobras el traslado.
                </p>
            </div>
        </fieldset>

        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 -translate-y-1"
        >
            <div v-if="needsAddress" class="grid gap-2">
                <Label for="address"
                    >Dirección de tu local o punto de entrega</Label
                >
                <Input
                    id="address"
                    v-model="form.address"
                    class="h-11"
                    placeholder="Ej. 12 calle 3-45, zona 10"
                    autocomplete="street-address"
                />
                <InputError :message="form.errors.address" />
                <p class="text-muted-foreground text-xs">
                    La necesitamos porque elegiste que te visiten o recojan
                    pedidos.
                </p>
            </div>
        </Transition>

        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 -translate-y-1"
        >
            <fieldset v-if="needsZones" class="grid gap-3">
                <legend class="flex items-center gap-2 text-sm font-medium">
                    <MapPin class="size-4" />
                    ¿A qué zonas llegas?
                </legend>
                <HelpHint label="¿Cómo lleno esto?">
                    Agrega cada zona a la que llevas pedidos o vas a domicilio.
                    En <strong>costo</strong> pon cuánto cobras por llegar ahí
                    (0 si es gratis). En <strong>pedido mínimo</strong>, el
                    monto más bajo que aceptas para esa zona (0 si no hay).
                </HelpHint>

                <div
                    v-for="(row, i) in form.delivery_zones"
                    :key="i"
                    class="bg-card grid grid-cols-2 items-end gap-3 rounded-xl border p-3 sm:grid-cols-[1.4fr_1fr_1fr_auto]"
                >
                    <div class="col-span-2 grid gap-1.5 sm:col-span-1">
                        <Label :for="`zone-${i}`" class="text-xs">Zona</Label>
                        <select
                            :id="`zone-${i}`"
                            v-model="row.zone_id"
                            :class="selectClass"
                        >
                            <option :value="null" disabled>Elige</option>
                            <option
                                v-for="zone in availableZones(row.zone_id)"
                                :key="zone.id"
                                :value="zone.id"
                            >
                                {{ zone.name }}
                            </option>
                        </select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label :for="`fee-${i}`" class="text-xs"
                            >Costo ({{ options.currency }})</Label
                        >
                        <Input
                            :id="`fee-${i}`"
                            v-model="row.fee"
                            type="number"
                            min="0"
                            step="0.01"
                            inputmode="decimal"
                            class="h-11"
                            placeholder="0"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label :for="`min-${i}`" class="text-xs"
                            >Pedido mínimo</Label
                        >
                        <Input
                            :id="`min-${i}`"
                            v-model="row.min_order"
                            type="number"
                            min="0"
                            step="0.01"
                            inputmode="decimal"
                            class="h-11"
                            placeholder="0"
                        />
                    </div>
                    <button
                        type="button"
                        class="text-muted-foreground hover:bg-destructive/10 hover:text-destructive col-span-2 grid h-11 place-items-center rounded-md sm:col-span-1 sm:w-11"
                        :aria-label="`Quitar zona ${i + 1}`"
                        @click="form.delivery_zones.splice(i, 1)"
                    >
                        <Trash2 class="size-4" />
                    </button>
                    <InputError
                        class="col-span-2 sm:col-span-4"
                        :message="zoneError(i)"
                    />
                </div>

                <button
                    type="button"
                    class="border-border text-primary hover:bg-primary/5 flex h-12 items-center justify-center gap-2 rounded-xl border border-dashed text-sm font-bold"
                    @click="addZone"
                >
                    <Plus class="size-4" />
                    Agregar zona
                </button>
                <InputError :message="form.errors.delivery_zones" />
            </fieldset>
        </Transition>

        <StepActions :back-href="backHref" :processing="form.processing" />
    </form>
</template>
