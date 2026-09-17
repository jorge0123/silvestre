<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password/confirm';

defineOptions({
    layout: {
        title: 'Confirma que eres tú',
        description:
            'Esta es una zona protegida. Escribe tu contraseña para continuar.',
    },
});
</script>

<template>
    <Head title="Confirmar contraseña" />

    <PasskeyVerify
        :routes="{
            options: confirmOptions(),
            submit: confirmStore(),
        }"
        label="Confirmar con llave de acceso"
        loading-label="Confirmando…"
        separator="O confirma con tu contraseña"
    />

    <Form
        v-bind="store.form()"
        reset-on-success
        v-slot="{ errors, processing }"
    >
        <div class="space-y-5">
            <div class="grid gap-2">
                <Label for="password">Contraseña</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    autofocus
                    class="h-11"
                />
                <InputError :message="errors.password" />
            </div>

            <Button
                class="h-12 w-full rounded-full text-[15px] font-bold"
                :disabled="processing"
                data-test="confirm-password-button"
            >
                <Spinner v-if="processing" />
                Confirmar
            </Button>
        </div>
    </Form>
</template>
