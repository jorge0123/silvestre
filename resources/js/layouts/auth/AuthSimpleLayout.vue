<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { MapPin, PackageCheck, Star } from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const promises = [
    {
        icon: PackageCheck,
        text: 'Tu catálogo no desaparece a las 24 horas.',
    },
    {
        icon: Star,
        text: 'Solo califica quien recibió su pedido.',
    },
    {
        icon: MapPin,
        text: 'Te encuentran los vecinos de tu zona.',
    },
];
</script>

<template>
    <div
        class="bg-background grid min-h-svh lg:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)]"
    >
        <!-- Panel de marca: solo en pantallas grandes. Siempre oscuro, en
             ambos temas, porque es la "portada" de Silvestre. -->
        <aside
            class="relative hidden overflow-hidden bg-[#0e4534] text-[#f1f6f2] lg:flex dark:bg-[#0c2b21]"
        >
            <div
                class="pointer-events-none absolute inset-0"
                aria-hidden="true"
            >
                <div
                    class="absolute -top-40 -left-32 size-[520px] rounded-full bg-[#d8a33a] opacity-[0.16] blur-3xl"
                />
                <div
                    class="absolute -right-40 -bottom-48 size-[560px] rounded-full bg-[#c21a52] opacity-[0.22] blur-3xl"
                />
                <div
                    class="absolute inset-0 [background-image:radial-gradient(rgba(255,255,255,.35)_1px,transparent_1.4px)] [mask-image:linear-gradient(to_bottom,black,transparent_75%)] [background-size:16px_16px] opacity-[0.22]"
                />
            </div>

            <div class="relative z-10 flex w-full flex-col p-10 xl:p-14">
                <Link
                    :href="home()"
                    class="inline-flex items-center gap-3 self-start rounded-lg focus-visible:ring-2 focus-visible:ring-white/60 focus-visible:outline-none"
                >
                    <AppLogoIcon class="size-11 shadow-lg shadow-black/20" />
                    <span
                        class="font-display text-2xl font-extrabold tracking-tight"
                        >Silvestre</span
                    >
                </Link>

                <div class="my-auto max-w-[30rem] py-12">
                    <p
                        class="inline-flex items-center gap-2 text-xs font-bold tracking-[0.16em] text-[#f1f6f2]/70 uppercase"
                    >
                        <span class="size-1.5 rounded-full bg-[#ff6e9c]" />
                        Comercio de barrio
                    </p>
                    <h2
                        class="font-display mt-5 text-[clamp(2.4rem,3.6vw,3.4rem)] leading-[1.02] font-extrabold"
                    >
                        Tu negocio, a la vista de tu colonia.
                    </h2>
                    <p class="mt-5 text-lg leading-relaxed text-[#f1f6f2]/75">
                        Publica, vende, cobra y lleva tu inventario en un solo
                        lugar. Y si solo vienes a comprar, aquí están los
                        negocios de tu zona.
                    </p>

                    <ul class="mt-9 flex flex-col gap-3.5">
                        <li
                            v-for="item in promises"
                            :key="item.text"
                            class="flex items-center gap-3 text-[15px] text-[#f1f6f2]/90"
                        >
                            <span
                                class="grid size-8 flex-none place-items-center rounded-full bg-white/10"
                            >
                                <component
                                    :is="item.icon"
                                    class="size-4 text-[#ffb3cb]"
                                />
                            </span>
                            {{ item.text }}
                        </li>
                    </ul>
                </div>

                <!-- Viñeta del producto: cómo se ve un negocio en Silvestre -->
                <div
                    class="animate-in fade-in slide-in-from-bottom-3 flex flex-wrap items-end gap-3 duration-700"
                    aria-hidden="true"
                >
                    <div
                        class="w-[19rem] rounded-2xl bg-[#f1f6f2] p-4 text-[#17231e] shadow-2xl shadow-black/30"
                    >
                        <div class="flex items-center gap-3">
                            <span
                                class="grid size-10 place-items-center rounded-xl bg-[#fae0e8] text-lg"
                                >🍪</span
                            >
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold">
                                    Galletas de la Abuela Chela
                                </p>
                                <p class="text-xs text-[#5b6a62]">
                                    Repostería · Centro
                                </p>
                            </div>
                        </div>
                        <div class="mt-3 flex items-center gap-2">
                            <span
                                class="font-display text-xl font-extrabold tabular-nums"
                                >4.8</span
                            >
                            <span class="flex gap-0.5 text-[#b8791a]">
                                <Star
                                    v-for="n in 5"
                                    :key="n"
                                    class="size-3.5 fill-current"
                                />
                            </span>
                            <span class="text-xs text-[#5b6a62]"
                                >37 reseñas</span
                            >
                        </div>
                    </div>
                    <div
                        class="mb-3 inline-flex items-center gap-2 rounded-full bg-[#efe6f7] px-3.5 py-2 text-xs font-bold text-[#6d4494] shadow-xl shadow-black/25"
                    >
                        <span
                            class="size-1.5 animate-pulse rounded-full bg-current"
                        />
                        Pedido SV-2418 · En camino
                    </div>
                </div>
            </div>
        </aside>

        <!-- Formulario -->
        <main class="flex min-h-svh flex-col px-5 py-5 sm:px-10">
            <div class="flex items-center justify-between">
                <Link
                    :href="home()"
                    class="inline-flex items-center gap-2.5 rounded-lg lg:invisible"
                >
                    <AppLogoIcon class="size-10" />
                    <span
                        class="font-display text-xl font-extrabold tracking-tight"
                        >Silvestre</span
                    >
                </Link>
                <ThemeToggle />
            </div>

            <div
                class="animate-in fade-in slide-in-from-bottom-2 mx-auto flex w-full max-w-[400px] flex-1 flex-col justify-center py-10 duration-500"
            >
                <h1
                    class="font-display text-[clamp(1.9rem,4vw,2.25rem)] leading-[1.08] font-extrabold"
                >
                    {{ title }}
                </h1>
                <p
                    v-if="description"
                    class="text-muted-foreground mt-2.5 text-[15px] leading-relaxed"
                >
                    {{ description }}
                </p>

                <div class="mt-8">
                    <slot />
                </div>
            </div>

            <p class="text-muted-foreground text-center text-xs lg:text-left">
                © {{ new Date().getFullYear() }} Silvestre · Hecho para negocios
                pequeños
            </p>
        </main>
    </div>
</template>
