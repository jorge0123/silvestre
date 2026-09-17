<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { computed, ref, watchEffect } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { store } from '@/routes/two-factor/login';
import type { TwoFactorConfigContent } from '@/types';

const showRecoveryInput = ref<boolean>(false);
const code = ref<string>('');

const authConfigContent = computed<TwoFactorConfigContent>(() => {
    if (showRecoveryInput.value) {
        return {
            title: 'Código de recuperación',
            description:
                'Escribe uno de los códigos de emergencia que guardaste al activar la verificación en dos pasos.',
            buttonText: 'usar el código de mi app',
        };
    }

    return {
        title: 'Verificación en dos pasos',
        description:
            'Escribe el código de 6 dígitos que muestra tu app de autenticación.',
        buttonText: 'usar un código de recuperación',
    };
});

watchEffect(() => {
    setLayoutProps({
        title: authConfigContent.value.title,
        description: authConfigContent.value.description,
    });
});

const toggleRecoveryMode = (clearErrors: () => void): void => {
    showRecoveryInput.value = !showRecoveryInput.value;
    clearErrors();
    code.value = '';
};
</script>

<template>
    <Head title="Verificación en dos pasos" />

    <div class="space-y-6">
        <template v-if="!showRecoveryInput">
            <Form
                v-bind="store.form()"
                class="space-y-5"
                reset-on-error
                @error="code = ''"
                #default="{ errors, processing, clearErrors }"
            >
                <input type="hidden" name="code" :value="code" />
                <div class="flex flex-col items-center gap-3 text-center">
                    <InputOTP
                        id="otp"
                        v-model="code"
                        :maxlength="6"
                        :disabled="processing"
                        autofocus
                    >
                        <InputOTPGroup>
                            <InputOTPSlot
                                v-for="index in 6"
                                :key="index"
                                :index="index - 1"
                                class="size-12 text-lg"
                            />
                        </InputOTPGroup>
                    </InputOTP>
                    <InputError :message="errors.code" />
                </div>
                <Button
                    type="submit"
                    class="h-12 w-full rounded-full text-[15px] font-bold"
                    :disabled="processing"
                    >Continuar</Button
                >
                <p class="text-muted-foreground text-center text-sm">
                    O puedes
                    <button
                        type="button"
                        class="text-primary font-bold hover:underline"
                        @click="() => toggleRecoveryMode(clearErrors)"
                    >
                        {{ authConfigContent.buttonText }}
                    </button>
                </p>
            </Form>
        </template>

        <template v-else>
            <Form
                v-bind="store.form()"
                class="space-y-5"
                reset-on-error
                #default="{ errors, processing, clearErrors }"
            >
                <Input
                    name="recovery_code"
                    type="text"
                    placeholder="Código de recuperación"
                    :autofocus="showRecoveryInput"
                    required
                    class="h-11"
                />
                <InputError :message="errors.recovery_code" />
                <Button
                    type="submit"
                    class="h-12 w-full rounded-full text-[15px] font-bold"
                    :disabled="processing"
                    >Continuar</Button
                >
                <p class="text-muted-foreground text-center text-sm">
                    O puedes
                    <button
                        type="button"
                        class="text-primary font-bold hover:underline"
                        @click="() => toggleRecoveryMode(clearErrors)"
                    >
                        {{ authConfigContent.buttonText }}
                    </button>
                </p>
            </Form>
        </template>
    </div>
</template>
