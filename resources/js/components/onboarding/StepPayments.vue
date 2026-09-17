<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    Banknote,
    CreditCard,
    Landmark,
    Link2,
    ShieldCheck,
    Smartphone,
    Store,
} from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import ChoiceCard from '@/components/onboarding/ChoiceCard.vue';
import StepActions from '@/components/onboarding/StepActions.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { selectClass } from '@/lib/formClasses';
import { update } from '@/routes/onboarding';
import type { PaymentRow, StepProps } from '@/types/onboarding';

const props = defineProps<StepProps>();

const form = useForm({
    payment_methods: (props.business?.payment_methods ?? []).map((row) => ({
        method: row.method,
        details: { ...row.details },
    })) as PaymentRow[],
});

const icons: Record<string, typeof Banknote> = {
    cash_on_delivery: Banknote,
    cash_on_pickup: Store,
    card_on_delivery: CreditCard,
    bank_transfer: Landmark,
    mobile_wallet: Smartphone,
    payment_link: Link2,
};

function rowFor(method: string): PaymentRow | undefined {
    return form.payment_methods.find((r) => r.method === method);
}

function toggle(method: string): void {
    const i = form.payment_methods.findIndex((r) => r.method === method);

    if (i === -1) {
        form.payment_methods.push({ method, details: {} });
    } else {
        form.payment_methods.splice(i, 1);
    }
}

function error(method: string, field: string): string | undefined {
    const i = form.payment_methods.findIndex((r) => r.method === method);

    return (form.errors as Record<string, string>)[
        `payment_methods.${i}.details.${field}`
    ];
}

function submit(): void {
    form.post(update('cobros').url, { preserveScroll: true });
}
</script>

<template>
    <form class="grid gap-6" @submit.prevent="submit">
        <div class="bg-brand-soft text-brand flex gap-3 rounded-xl p-4 text-sm">
            <ShieldCheck class="mt-0.5 size-5 flex-none" />
            <p>
                <strong>Tu dinero es tuyo.</strong> Tus clientes te pagan
                directo a ti. Silvestre no recibe ese dinero ni cobra comisión
                por tus ventas.
            </p>
        </div>

        <fieldset class="grid gap-3">
            <legend class="mb-1 text-sm font-medium">
                ¿Cómo te pueden pagar?
                <span class="text-muted-foreground font-normal"
                    >Elige todas las que aceptes.</span
                >
            </legend>

            <div
                v-for="option in options.paymentMethods"
                :key="option.value"
                class="grid gap-2"
            >
                <ChoiceCard
                    multiple
                    :selected="Boolean(rowFor(option.value))"
                    :title="option.label"
                    :description="option.description"
                    :icon="icons[option.value]"
                    @select="toggle(option.value)"
                />

                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="opacity-0 -translate-y-1"
                >
                    <div
                        v-if="rowFor(option.value) && option.details.length"
                        class="bg-muted/60 ml-4 grid gap-4 rounded-xl p-4 sm:grid-cols-2"
                    >
                        <template v-if="option.value === 'bank_transfer'">
                            <div class="grid gap-1.5">
                                <Label :for="`bank-${option.value}`"
                                    >Banco</Label
                                >
                                <select
                                    :id="`bank-${option.value}`"
                                    v-model="rowFor(option.value)!.details.bank"
                                    :class="selectClass"
                                >
                                    <option :value="undefined" disabled>
                                        Elige tu banco
                                    </option>
                                    <option
                                        v-for="bank in options.banks"
                                        :key="bank"
                                        :value="bank"
                                    >
                                        {{ bank }}
                                    </option>
                                </select>
                                <InputError
                                    :message="error(option.value, 'bank')"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label :for="`type-${option.value}`"
                                    >Tipo de cuenta</Label
                                >
                                <select
                                    :id="`type-${option.value}`"
                                    v-model="
                                        rowFor(option.value)!.details
                                            .account_type
                                    "
                                    :class="selectClass"
                                >
                                    <option :value="undefined" disabled>
                                        Elige
                                    </option>
                                    <option
                                        v-for="type in options.accountTypes"
                                        :key="type"
                                        :value="type"
                                    >
                                        {{ type }}
                                    </option>
                                </select>
                                <InputError
                                    :message="
                                        error(option.value, 'account_type')
                                    "
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label :for="`acct-${option.value}`"
                                    >Número de cuenta</Label
                                >
                                <Input
                                    :id="`acct-${option.value}`"
                                    v-model="
                                        rowFor(option.value)!.details
                                            .account_number
                                    "
                                    class="h-11"
                                    inputmode="numeric"
                                />
                                <InputError
                                    :message="
                                        error(option.value, 'account_number')
                                    "
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label :for="`holder-${option.value}`"
                                    >A nombre de</Label
                                >
                                <Input
                                    :id="`holder-${option.value}`"
                                    v-model="
                                        rowFor(option.value)!.details.holder
                                    "
                                    class="h-11"
                                />
                                <InputError
                                    :message="error(option.value, 'holder')"
                                />
                            </div>
                        </template>

                        <template v-else-if="option.value === 'mobile_wallet'">
                            <div class="grid gap-1.5">
                                <Label :for="`wallet-${option.value}`"
                                    >Billetera</Label
                                >
                                <select
                                    :id="`wallet-${option.value}`"
                                    v-model="
                                        rowFor(option.value)!.details.provider
                                    "
                                    :class="selectClass"
                                >
                                    <option :value="undefined" disabled>
                                        Elige
                                    </option>
                                    <option
                                        v-for="wallet in options.wallets"
                                        :key="wallet"
                                        :value="wallet"
                                    >
                                        {{ wallet }}
                                    </option>
                                </select>
                                <InputError
                                    :message="error(option.value, 'provider')"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label :for="`wnum-${option.value}`"
                                    >Número</Label
                                >
                                <Input
                                    :id="`wnum-${option.value}`"
                                    v-model="
                                        rowFor(option.value)!.details.number
                                    "
                                    class="h-11"
                                    inputmode="numeric"
                                />
                                <InputError
                                    :message="error(option.value, 'number')"
                                />
                            </div>
                        </template>

                        <template v-else-if="option.value === 'payment_link'">
                            <div class="grid gap-1.5 sm:col-span-2">
                                <Label :for="`url-${option.value}`"
                                    >Tu link de pago</Label
                                >
                                <Input
                                    :id="`url-${option.value}`"
                                    v-model="rowFor(option.value)!.details.url"
                                    type="url"
                                    class="h-11"
                                    placeholder="https://"
                                />
                                <InputError
                                    :message="error(option.value, 'url')"
                                />
                            </div>
                        </template>

                        <p
                            v-if="option.requiresProof"
                            class="text-muted-foreground text-xs sm:col-span-2"
                        >
                            El cliente verá estos datos al pedir, subirá su
                            comprobante y tú confirmarás que el dinero llegó.
                        </p>
                    </div>
                </Transition>
            </div>
            <InputError :message="form.errors.payment_methods" />
        </fieldset>

        <StepActions :back-href="backHref" :processing="form.processing" />
    </form>
</template>
