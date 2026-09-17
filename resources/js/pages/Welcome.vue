<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { MapPin, PackageCheck, ShieldCheck, Star } from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import { feed, login, register } from '@/routes';

const pillars = [
    {
        icon: MapPin,
        title: 'Para quien compra',
        text: 'Encuentra quién entrega en tu colonia, mira su catálogo y pide sin perderte entre historias que desaparecen.',
    },
    {
        icon: PackageCheck,
        title: 'Para quien vende',
        text: 'Publica, recibe pedidos, cobra y lleva tu inventario. Al final del mes sabes cuánto ganaste de verdad.',
    },
    {
        icon: ShieldCheck,
        title: 'Reputación que vale',
        text: 'Solo puede calificar quien recibió su pedido. Por eso estas estrellas significan algo.',
    },
];
</script>

<template>
    <Head title="Comercio de barrio" />

    <div class="bg-background text-foreground min-h-svh">
        <header
            class="mx-auto flex max-w-6xl items-center gap-3 px-5 py-5 sm:px-8"
        >
            <Link
                href="/"
                class="mr-auto inline-flex items-center gap-2.5 rounded-lg"
            >
                <AppLogoIcon class="size-10" />
                <span class="font-display text-xl font-extrabold tracking-tight"
                    >Silvestre</span
                >
            </Link>

            <ThemeToggle />

            <Link
                v-if="$page.props.auth.user"
                :href="feed()"
                class="bg-primary text-primary-foreground inline-flex h-10 items-center rounded-full px-5 text-sm font-bold transition-transform active:scale-[.97]"
            >
                Ir a Inicio
            </Link>
            <template v-else>
                <Link
                    :href="login()"
                    class="hover:bg-muted hidden h-10 items-center rounded-full px-4 text-sm font-bold sm:inline-flex"
                >
                    Entrar
                </Link>
                <Link
                    :href="register()"
                    class="bg-primary text-primary-foreground inline-flex h-10 items-center rounded-full px-5 text-sm font-bold transition-transform active:scale-[.97]"
                >
                    Crear cuenta
                </Link>
            </template>
        </header>

        <main>
            <section
                class="animate-in fade-in slide-in-from-bottom-2 mx-auto max-w-6xl px-5 pt-10 pb-16 duration-500 sm:px-8 md:pt-20"
            >
                <p
                    class="text-muted-foreground inline-flex items-center gap-2 text-xs font-bold tracking-[0.16em] uppercase"
                >
                    <span class="bg-primary size-1.5 rounded-full" />
                    Comercio de barrio
                </p>
                <h1
                    class="font-display mt-5 max-w-[15ch] text-[clamp(2.6rem,7vw,5rem)] leading-[0.98] font-extrabold"
                >
                    Donde tu colonia compra y vende.
                </h1>
                <p
                    class="text-muted-foreground mt-6 max-w-[54ch] text-lg leading-relaxed md:text-xl"
                >
                    Silvestre es escaparate, punto de venta e inventario para
                    negocios pequeños. La repostera publica, vende, cobra y sabe
                    cuánto ganó. Tú encuentras lo que hay cerca.
                </p>
                <div class="mt-9 flex flex-wrap gap-3">
                    <Link
                        :href="register()"
                        class="bg-primary text-primary-foreground inline-flex h-12 items-center rounded-full px-7 text-[15px] font-bold transition-transform active:scale-[.97]"
                    >
                        Abrir mi negocio gratis
                    </Link>
                    <Link
                        :href="login()"
                        class="border-border bg-card hover:bg-muted inline-flex h-12 items-center rounded-full border px-7 text-[15px] font-bold"
                    >
                        Ya tengo cuenta
                    </Link>
                </div>
                <p class="text-muted-foreground mt-4 text-sm">
                    Tres meses de Pro gratis al activar tu perfil.
                </p>
            </section>

            <section class="border-border bg-card border-y">
                <div
                    class="mx-auto grid max-w-6xl gap-px px-5 sm:px-8 md:grid-cols-3"
                >
                    <article
                        v-for="item in pillars"
                        :key="item.title"
                        class="py-10 md:px-6 md:first:pl-0 md:last:pr-0"
                    >
                        <span
                            class="bg-brand-soft text-brand grid size-11 place-items-center rounded-xl"
                        >
                            <component :is="item.icon" class="size-5" />
                        </span>
                        <h2 class="font-display mt-5 text-xl font-bold">
                            {{ item.title }}
                        </h2>
                        <p
                            class="text-muted-foreground mt-2 text-[15px] leading-relaxed"
                        >
                            {{ item.text }}
                        </p>
                    </article>
                </div>
            </section>

            <section
                class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 gap-y-3 px-5 py-10 sm:px-8"
            >
                <span class="text-star flex gap-0.5">
                    <Star v-for="n in 5" :key="n" class="size-4 fill-current" />
                </span>
                <p class="text-muted-foreground text-sm">
                    Una reseña por persona, solo tras un pedido entregado.
                    Pagar da visibilidad, nunca estrellas.
                </p>
            </section>
        </main>

        <footer
            class="border-border text-muted-foreground mx-auto max-w-6xl border-t px-5 py-8 text-sm sm:px-8"
        >
            © {{ new Date().getFullYear() }} Silvestre · Hecho para negocios
            pequeños
        </footer>
    </div>
</template>
