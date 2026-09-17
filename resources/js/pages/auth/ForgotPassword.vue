<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Recupera tu contraseña',
        description:
            'Escribe el correo con el que te registraste y te mandamos un enlace para crear una nueva.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Recuperar contraseña" />

    <div
        v-if="status"
        class="bg-ok-soft text-ok mb-6 rounded-lg px-4 py-3 text-sm font-bold"
    >
        {{ status }}
    </div>

    <div class="space-y-6">
        <Form v-bind="email.form()" v-slot="{ errors, processing }">
            <div class="grid gap-2">
                <Label for="email">Correo electrónico</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    autocomplete="off"
                    autofocus
                    placeholder="tu@correo.com"
                    class="h-11"
                />
                <InputError :message="errors.email" />
            </div>

            <Button
                class="mt-6 h-12 w-full rounded-full text-[15px] font-bold"
                :disabled="processing"
                data-test="email-password-reset-link-button"
            >
                <Spinner v-if="processing" />
                Enviarme el enlace
            </Button>
        </Form>

        <p class="text-muted-foreground text-center text-sm">
            ¿Ya la recordaste?
            <TextLink :href="login()" class="text-primary font-bold"
                >Vuelve a entrar</TextLink
            >
        </p>
    </div>
</template>
