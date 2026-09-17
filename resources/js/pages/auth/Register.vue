<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ShoppingBag, Store } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineProps<{
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: 'Crea tu cuenta',
        description:
            'Una sola cuenta sirve para comprar y para vender. Empezar es gratis.',
    },
});

// /register?tipo=negocio llega con "vender" ya elegido (botón de la portada).
const initial =
    typeof window !== 'undefined' &&
    new URLSearchParams(window.location.search).get('tipo') === 'negocio'
        ? 'business'
        : 'personal';

const accountType = ref<'personal' | 'business'>(initial);

const choices = [
    {
        value: 'personal' as const,
        icon: ShoppingBag,
        title: 'Quiero comprar',
        text: 'Encuentra negocios de tu zona.',
    },
    {
        value: 'business' as const,
        icon: Store,
        title: 'Tengo un negocio',
        text: 'Te guiamos para abrirlo en 7 pasos.',
    },
];
</script>

<template>
    <Head title="Crear cuenta" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <fieldset class="grid gap-2">
            <legend class="mb-2 text-sm font-medium">¿Para qué vienes?</legend>
            <div class="grid grid-cols-2 gap-3" role="radiogroup">
                <button
                    v-for="choice in choices"
                    :key="choice.value"
                    type="button"
                    role="radio"
                    :aria-checked="accountType === choice.value"
                    class="bg-card flex flex-col items-start gap-2 rounded-xl border p-3.5 text-left transition-all active:scale-[0.98]"
                    :class="
                        accountType === choice.value
                            ? 'border-primary ring-primary bg-primary/[0.04] ring-1'
                            : 'border-border hover:border-foreground/30'
                    "
                    @click="accountType = choice.value"
                >
                    <span
                        class="grid size-9 place-items-center rounded-lg transition-colors"
                        :class="
                            accountType === choice.value
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-muted text-muted-foreground'
                        "
                    >
                        <component :is="choice.icon" class="size-[18px]" />
                    </span>
                    <span class="text-sm leading-tight font-bold">{{ choice.title }}</span>
                    <span class="text-muted-foreground text-xs leading-snug">{{ choice.text }}</span>
                </button>
            </div>
            <input type="hidden" name="account_type" :value="accountType" />
            <p class="text-muted-foreground mt-1 text-xs">
                No es para siempre: puedes cambiar entre comprar y administrar
                tu negocio cuando quieras.
            </p>
            <InputError :message="errors.account_type" />
        </fieldset>

        <div class="grid gap-5">
            <div class="grid gap-2">
                <Label for="name">Tu nombre</Label>
                <Input
                    id="name"
                    type="text"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="name"
                    name="name"
                    placeholder="Tu nombre completo"
                    class="h-11"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Correo electrónico</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    :tabindex="2"
                    autocomplete="email"
                    name="email"
                    placeholder="tu@correo.com"
                    class="h-11"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Contraseña</Label>
                <PasswordInput
                    id="password"
                    required
                    :tabindex="3"
                    autocomplete="new-password"
                    name="password"
                    placeholder="Crea una contraseña"
                    :passwordrules="passwordRules"
                    class="h-11"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirma tu contraseña</Label>
                <PasswordInput
                    id="password_confirmation"
                    required
                    :tabindex="4"
                    autocomplete="new-password"
                    name="password_confirmation"
                    placeholder="Escríbela otra vez"
                    :passwordrules="passwordRules"
                    class="h-11"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-2 h-12 w-full rounded-full text-[15px] font-bold"
                :tabindex="5"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                {{ accountType === 'business' ? 'Crear cuenta y abrir mi negocio' : 'Crear cuenta' }}
            </Button>
        </div>

        <p class="text-muted-foreground text-center text-sm">
            ¿Ya tienes cuenta?
            <TextLink :href="login()" :tabindex="6" class="text-primary font-bold"
                >Entra aquí</TextLink
            >
        </p>
    </Form>
</template>
