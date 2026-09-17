<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

defineProps<{
    backHref: string | null;
    processing: boolean;
    label?: string;
}>();
</script>

<template>
    <div
        class="border-border mt-8 flex items-center justify-between gap-3 border-t pt-6"
    >
        <Link
            v-if="backHref"
            :href="backHref"
            class="text-muted-foreground hover:text-foreground inline-flex h-11 items-center gap-2 rounded-full px-3 text-sm font-bold"
        >
            <ArrowLeft class="size-4" />
            Atrás
        </Link>
        <span v-else />

        <Button
            type="submit"
            class="h-12 rounded-full px-7 text-[15px] font-bold"
            :disabled="processing"
        >
            <Spinner v-if="processing" />
            {{ label ?? 'Guardar y seguir' }}
            <ArrowRight v-if="!processing" class="size-4" />
        </Button>
    </div>
</template>
