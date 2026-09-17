<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { MailCheck } from '@lucide/vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'Confirma tu correo',
        description:
            'Te mandamos un enlace. Ábrelo desde tu correo para activar tu cuenta.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Confirma tu correo" />

    <div
        class="bg-brand-soft text-brand mb-6 flex items-start gap-3 rounded-lg px-4 py-3.5 text-sm"
    >
        <MailCheck class="mt-0.5 size-5 flex-none" />
        <p>
            ¿No llegó? Revisa la carpeta de spam o promociones antes de pedir
            otro.
        </p>
    </div>

    <div
        v-if="status === 'verification-link-sent'"
        class="bg-ok-soft text-ok mb-6 rounded-lg px-4 py-3 text-sm font-bold"
    >
        Listo: te enviamos un enlace nuevo al correo con el que te registraste.
    </div>

    <Form
        v-bind="send.form()"
        class="space-y-5 text-center"
        v-slot="{ processing }"
    >
        <Button
            :disabled="processing"
            variant="secondary"
            class="h-12 w-full rounded-full text-[15px] font-bold"
        >
            <Spinner v-if="processing" />
            Reenviar el correo
        </Button>

        <TextLink
            :href="logout()"
            as="button"
            class="text-muted-foreground mx-auto block text-sm"
        >
            Cerrar sesión
        </TextLink>
    </Form>
</template>
