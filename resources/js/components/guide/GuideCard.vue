<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Lightbulb, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import { dismiss } from '@/routes/guides';

/**
 * Guía dentro de la app: explica qué hace una pantalla y cómo se usa.
 * Se cierra una vez y no vuelve a aparecer (se recuerda en la cuenta).
 */
const props = defineProps<{
    guideKey: string;
    title: string;
    steps?: { title: string; text: string }[];
}>();

const page = usePage();
const hiddenNow = ref(false);

const visible = computed(
    () =>
        !hiddenNow.value &&
        !page.props.guides.dismissed.includes(props.guideKey),
);

function close(): void {
    hiddenNow.value = true;
    router.post(
        dismiss(props.guideKey).url,
        {},
        { preserveScroll: true, preserveState: true },
    );
}
</script>

<template>
    <Transition
        leave-active-class="transition duration-200 ease-in"
        leave-to-class="opacity-0 -translate-y-1"
    >
        <section
            v-if="visible"
            class="border-brand/20 bg-brand-soft relative rounded-xl border p-5"
            :aria-label="title"
        >
            <button
                type="button"
                class="text-brand/70 hover:bg-brand/10 hover:text-brand absolute top-3 right-3 grid size-8 place-items-center rounded-full"
                aria-label="Cerrar guía"
                @click="close"
            >
                <X class="size-4" />
            </button>

            <div class="flex items-start gap-3 pr-8">
                <span
                    class="bg-brand text-brand-foreground grid size-9 flex-none place-items-center rounded-full"
                >
                    <Lightbulb class="size-[18px]" />
                </span>
                <div class="min-w-0">
                    <p
                        class="text-brand text-[11px] font-bold tracking-[0.14em] uppercase"
                    >
                        Guía
                    </p>
                    <h3 class="font-display text-foreground text-lg font-bold">
                        {{ title }}
                    </h3>
                    <div class="text-foreground/80 mt-1 text-[15px]">
                        <slot />
                    </div>
                </div>
            </div>

            <ol
                v-if="steps?.length"
                class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
            >
                <li
                    v-for="(step, i) in steps"
                    :key="step.title"
                    class="bg-card flex gap-3 rounded-lg p-3.5"
                >
                    <span
                        class="bg-brand/10 text-brand grid size-7 flex-none place-items-center rounded-full text-sm font-bold"
                        >{{ i + 1 }}</span
                    >
                    <div>
                        <p class="text-sm font-bold">{{ step.title }}</p>
                        <p class="text-muted-foreground mt-0.5 text-sm">
                            {{ step.text }}
                        </p>
                    </div>
                </li>
            </ol>

            <button
                type="button"
                class="text-brand mt-4 text-sm font-bold hover:underline"
                @click="close"
            >
                Entendido
            </button>
        </section>
    </Transition>
</template>
