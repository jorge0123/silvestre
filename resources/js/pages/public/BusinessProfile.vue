<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    BadgeCheck,
    CalendarClock,
    Camera,
    Clock,
    Heart,
    ImagePlus,
    Layers,
    MapPin,
    MessageCircle,
    Pencil,
    Play,
    Share2,
    ShieldCheck,
    Truck,
    Wallet,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import BackButton from '@/components/BackButton.vue';
import BusinessAvatar from '@/components/feed/BusinessAvatar.vue';
import FollowButton from '@/components/feed/FollowButton.vue';
import PostDialog from '@/components/feed/PostDialog.vue';
import RatingStars from '@/components/feed/RatingStars.vue';
import StoryViewer from '@/components/feed/StoryViewer.vue';
import GuideCard from '@/components/guide/GuideCard.vue';
import SiteLayout from '@/layouts/SiteLayout.vue';
import { shareLink } from '@/lib/share';
import { dashboard, feed, home } from '@/routes';
import { publish, show as businessShow } from '@/routes/business';
import type { BusinessCard, PostItem, StoryGroup } from '@/types/feed';

type Product = {
    id: number;
    name: string;
    description: string | null;
    price: string;
    variant: string | null;
    stockMode: string;
    stockLabel: string;
    available: boolean;
    lowStock: boolean;
    leadDays: number | null;
    image: string | null;
    order: string | null;
};
type Service = {
    id: number;
    name: string;
    description: string | null;
    price: string;
    duration: number;
    modes: string[];
    image: string | null;
    book: string | null;
};
type Review = {
    id: number;
    author: string;
    initials: string;
    stars: number;
    body: string | null;
    ago: string;
    verified: boolean;
    reply: { body: string; ago: string } | null;
};

const props = defineProps<{
    business: BusinessCard & {
        about: string | null;
        kind: string;
        openNow: boolean;
        whatsapp: string | null;
        hours: { day: string; range: string }[];
        fulfillment: string[];
        serviceModes: string[];
        deliveryZones: { name: string; fee: string }[];
        paymentMethods: string[];
        distribution: { stars: number; count: number }[];
        minReviewsToShowAverage: number;
    };
    stories: StoryGroup | null;
    highlights: {
        id: number;
        title: string;
        cover: string | null;
        group: StoryGroup;
    }[];
    products: Product[];
    services: Service[];
    posts: PostItem[];
    reviews: Review[];
    viewer: { isGuest: boolean; isOwner: boolean; isFollowing: boolean };
}>();

// ---- pestañas: solo las que tienen sentido para este negocio
const tabs = computed(() => [
    ...(props.products.length
        ? [{ key: 'catalogo', label: 'Catálogo', count: props.products.length }]
        : []),
    ...(props.services.length
        ? [
              {
                  key: 'servicios',
                  label: 'Servicios',
                  count: props.services.length,
              },
          ]
        : []),
    { key: 'publicaciones', label: 'Publicaciones', count: props.posts.length },
    { key: 'resenas', label: 'Reseñas', count: props.business.ratingCount },
    { key: 'info', label: 'Info', count: null },
]);
const tab = ref(tabs.value[0].key);

// ---- historias: la del perfil y las destacadas comparten visor
const viewerOpen = ref(false);
const viewerGroups = ref<StoryGroup[]>([]);

function openGroup(group: StoryGroup): void {
    viewerGroups.value = [group];
    viewerOpen.value = true;
}

const aboutOpen = ref(false);
const signedIn = computed(() => Boolean(usePage().props.auth?.user));

function share(): void {
    void shareLink(businessShow(props.business.slug).url, props.business.name);
}

// ---- publicaciones: se abren aquí mismo, con reacciones y comentarios
const dialogOpen = ref(false);
const dialogPost = ref<PostItem | null>(null);

function openPost(post: PostItem): void {
    dialogPost.value = post;
    dialogOpen.value = true;
}

const maxCount = computed(() =>
    Math.max(1, ...props.business.distribution.map((d) => d.count)),
);
</script>

<template>
    <Head :title="business.name">
        <meta name="description" :content="business.intro ?? ''" />
    </Head>

    <SiteLayout
        :breadcrumbs="[
            { title: business.name, href: businessShow(business.slug) },
        ]"
    >
        <div class="mx-auto max-w-5xl px-2 pt-2 md:px-4">
            <BackButton :fallback="signedIn ? feed().url : home().url" />
        </div>

        <!-- Portada -->
        <div
            class="bg-muted relative h-40 md:mx-auto md:mt-2 md:h-64 md:max-w-5xl md:overflow-hidden md:rounded-2xl"
        >
            <img
                v-if="business.cover"
                :src="business.cover"
                alt=""
                class="size-full object-cover"
            />
            <div
                class="absolute inset-0 bg-gradient-to-t from-black/35 to-transparent"
            />
            <Link
                v-if="viewer.isOwner"
                :href="publish({ query: { tab: 'perfil' } })"
                class="absolute right-3 bottom-3 inline-flex h-9 items-center gap-2 rounded-full bg-black/55 px-3.5 text-xs font-bold text-white backdrop-blur hover:bg-black/70"
            >
                <Camera class="size-4" /> Cambiar portada
            </Link>
        </div>

        <div class="mx-auto max-w-5xl px-4">
            <!-- Encabezado del perfil -->
            <!-- Solo la foto se monta sobre la portada; el texto queda debajo,
                 sobre el fondo, para que siempre se lea. -->
            <section
                class="relative flex flex-col gap-4 md:flex-row md:items-start md:px-6"
            >
                <button
                    type="button"
                    class="relative -mt-12 self-start rounded-full disabled:cursor-default md:-mt-16"
                    :disabled="!stories"
                    :aria-label="
                        stories
                            ? `Ver historias de ${business.name}`
                            : undefined
                    "
                    @click="stories && openGroup(stories)"
                >
                    <span class="bg-background inline-block rounded-full p-1">
                        <BusinessAvatar
                            :src="business.avatar"
                            :name="business.name"
                            size="xl"
                            :ring="!!stories"
                        />
                    </span>
                    <Link
                        v-if="viewer.isOwner"
                        :href="publish({ query: { tab: 'perfil' } })"
                        class="bg-foreground text-background absolute right-1 bottom-1 grid size-9 place-items-center rounded-full border-2 border-[var(--background)]"
                        aria-label="Cambiar foto de perfil"
                        @click.stop
                    >
                        <Camera class="size-4" />
                    </Link>
                </button>

                <div class="min-w-0 flex-1 md:pt-4">
                    <h1
                        class="font-display flex flex-wrap items-center gap-2 text-2xl leading-tight font-extrabold md:text-3xl"
                    >
                        {{ business.name }}
                        <BadgeCheck
                            v-if="business.verified"
                            class="text-brand size-6"
                            aria-label="Negocio verificado"
                        />
                    </h1>
                    <p class="text-muted-foreground mt-0.5 text-sm">
                        @{{ business.slug }} · {{ business.category }}
                    </p>
                    <div
                        class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-sm"
                    >
                        <span class="inline-flex items-center gap-1"
                            ><MapPin class="size-4" /> {{ business.zone }}</span
                        >
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-bold"
                            :class="
                                business.openNow
                                    ? 'bg-ok-soft text-ok'
                                    : 'bg-muted text-muted-foreground'
                            "
                        >
                            <span class="size-1.5 rounded-full bg-current" />
                            {{
                                business.openNow
                                    ? 'Abierto ahora'
                                    : 'Cerrado ahora'
                            }}
                        </span>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5"
                            @click="tab = 'resenas'"
                        >
                            <template v-if="business.rating !== null">
                                <RatingStars :value="business.rating" />
                                <strong class="tabular-nums">{{
                                    business.rating.toFixed(1)
                                }}</strong>
                                <span class="text-muted-foreground"
                                    >({{ business.ratingCount }})</span
                                >
                            </template>
                            <span
                                v-else
                                class="bg-wait-soft text-wait rounded-full px-2 py-0.5 text-xs font-bold"
                                >Nuevo ·
                                {{ business.ratingCount }} reseñas</span
                            >
                        </button>
                        <span
                            ><strong class="tabular-nums">{{
                                business.followers
                            }}</strong>
                            seguidores</span
                        >
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 md:pt-5">
                    <a
                        v-if="business.whatsapp && !viewer.isOwner"
                        :href="business.whatsapp"
                        target="_blank"
                        rel="noopener"
                        class="bg-primary text-primary-foreground inline-flex h-11 items-center gap-2 rounded-full px-5 text-sm font-bold active:scale-[.97]"
                    >
                        <MessageCircle class="size-4" />
                        {{
                            business.offering === 'servicios'
                                ? 'Agendar por WhatsApp'
                                : 'Pedir por WhatsApp'
                        }}
                    </a>
                    <FollowButton
                        v-if="!viewer.isOwner"
                        :slug="business.slug"
                        :following="viewer.isFollowing"
                    />
                    <template v-else>
                        <Link
                            :href="publish()"
                            class="bg-primary text-primary-foreground inline-flex h-11 items-center gap-2 rounded-full px-5 text-sm font-bold"
                        >
                            <ImagePlus class="size-4" /> Publicar
                        </Link>
                        <Link
                            :href="publish({ query: { tab: 'perfil' } })"
                            class="bg-muted hover:bg-muted/70 inline-flex h-11 items-center gap-2 rounded-full px-4 text-sm font-bold"
                        >
                            <Pencil class="size-4" /> Editar perfil
                        </Link>
                        <Link
                            :href="dashboard()"
                            class="bg-muted hover:bg-muted/70 inline-flex h-11 items-center rounded-full px-4 text-sm font-bold"
                            >Mi panel</Link
                        >
                    </template>
                    <button
                        type="button"
                        class="bg-muted hover:bg-muted/70 inline-flex h-11 items-center gap-2 rounded-full px-4 text-sm font-bold"
                        @click="share"
                    >
                        <Share2 class="size-4" /> Compartir
                    </button>
                </div>
            </section>

            <div v-if="viewer.isOwner" class="mt-5 md:px-6">
                <GuideCard
                    guide-key="profile.owner"
                    title="Así te ven tus clientes"
                >
                    Esta es tu página pública. Compártela por WhatsApp: se abre
                    aunque la persona no tenga cuenta. Tus historias activas
                    aparecen en el aro de tu foto, y las destacadas se quedan
                    fijas debajo de tu presentación.
                </GuideCard>
            </div>

            <!-- Presentación -->
            <section class="mt-6 md:px-6">
                <p
                    class="font-display text-lg leading-snug font-bold md:text-xl"
                >
                    {{ business.intro }}
                </p>
                <p
                    v-if="business.about"
                    class="text-muted-foreground mt-2 max-w-3xl leading-relaxed"
                    :class="aboutOpen ? '' : 'line-clamp-2'"
                >
                    {{ business.about }}
                </p>
                <button
                    v-if="business.about && business.about.length > 140"
                    type="button"
                    class="mt-1 text-sm font-bold"
                    @click="aboutOpen = !aboutOpen"
                >
                    {{ aboutOpen ? 'Ver menos' : 'Leer más' }}
                </button>
            </section>

            <!-- Historias destacadas -->
            <section
                v-if="highlights.length"
                class="mt-6 md:px-6"
                aria-label="Historias destacadas"
            >
                <ul
                    class="flex [scrollbar-width:none] gap-5 overflow-x-auto pb-1 [&::-webkit-scrollbar]:hidden"
                >
                    <li v-for="h in highlights" :key="h.id" class="flex-none">
                        <button
                            type="button"
                            class="flex w-20 flex-col items-center gap-1.5 active:scale-95"
                            @click="openGroup(h.group)"
                        >
                            <span
                                class="border-border bg-muted grid size-[72px] place-items-center overflow-hidden rounded-full border-2 p-0.5"
                            >
                                <img
                                    v-if="h.cover"
                                    :src="h.cover"
                                    alt=""
                                    class="size-full rounded-full object-cover"
                                    loading="lazy"
                                />
                            </span>
                            <span
                                class="w-full truncate text-center text-xs font-bold"
                                >{{ h.title }}</span
                            >
                        </button>
                    </li>
                </ul>
            </section>

            <!-- Pestañas -->
            <nav
                class="bg-background/90 border-border sticky z-30 -mx-4 mt-6 [scrollbar-width:none] overflow-x-auto border-b px-4 backdrop-blur md:mx-0"
                :class="signedIn ? 'top-0' : 'top-14'"
                aria-label="Secciones del negocio"
            >
                <ul class="flex gap-6 md:px-6">
                    <li v-for="t in tabs" :key="t.key">
                        <button
                            type="button"
                            class="border-b-2 py-3 text-sm font-bold whitespace-nowrap transition-colors"
                            :class="
                                tab === t.key
                                    ? 'border-primary text-foreground'
                                    : 'text-muted-foreground hover:text-foreground border-transparent'
                            "
                            :aria-current="tab === t.key ? 'page' : undefined"
                            @click="tab = t.key"
                        >
                            {{ t.label
                            }}<span
                                v-if="t.count !== null"
                                class="text-muted-foreground ml-1 font-normal tabular-nums"
                                >{{ t.count }}</span
                            >
                        </button>
                    </li>
                </ul>
            </nav>

            <div class="pt-5 pb-16 md:px-6">
                <!-- Catálogo -->
                <div
                    v-if="tab === 'catalogo'"
                    class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3"
                >
                    <article
                        v-for="p in products"
                        :key="p.id"
                        class="bg-card flex flex-col overflow-hidden rounded-2xl border"
                    >
                        <div class="bg-muted relative aspect-square">
                            <img
                                v-if="p.image"
                                :src="p.image"
                                :alt="p.name"
                                class="size-full object-cover"
                                loading="lazy"
                            />
                            <span
                                v-if="!p.available"
                                class="bg-foreground text-background absolute top-2 left-2 rounded-full px-2.5 py-1 text-[11px] font-bold"
                                >Agotado</span
                            >
                            <span
                                v-else-if="p.stockMode === 'made_to_order'"
                                class="bg-wait-soft text-wait absolute top-2 left-2 rounded-full px-2.5 py-1 text-[11px] font-bold"
                                >Por encargo · {{ p.leadDays }}
                                {{ p.leadDays === 1 ? 'día' : 'días' }}</span
                            >
                            <span
                                v-else-if="p.lowStock"
                                class="bg-primary text-primary-foreground absolute top-2 left-2 rounded-full px-2.5 py-1 text-[11px] font-bold"
                                >Quedan pocos</span
                            >
                        </div>
                        <div class="flex flex-1 flex-col p-3">
                            <h3 class="text-sm leading-snug font-bold">
                                {{ p.name }}
                            </h3>
                            <p
                                v-if="p.variant"
                                class="text-muted-foreground text-xs"
                            >
                                {{ p.variant }}
                            </p>
                            <p
                                class="font-display mt-auto pt-2 text-lg font-extrabold tabular-nums"
                            >
                                {{ p.price }}
                            </p>
                            <a
                                v-if="p.order && p.available && !viewer.isOwner"
                                :href="p.order"
                                target="_blank"
                                rel="noopener"
                                class="bg-muted hover:bg-primary hover:text-primary-foreground mt-2 inline-flex h-9 items-center justify-center rounded-full text-xs font-bold transition-colors"
                            >
                                Pedir
                            </a>
                        </div>
                    </article>
                </div>

                <!-- Servicios -->
                <div
                    v-else-if="tab === 'servicios'"
                    class="grid gap-3 md:grid-cols-2"
                >
                    <article
                        v-for="s in services"
                        :key="s.id"
                        class="bg-card flex gap-3 overflow-hidden rounded-2xl border p-3"
                    >
                        <img
                            v-if="s.image"
                            :src="s.image"
                            :alt="s.name"
                            class="size-24 flex-none rounded-xl object-cover"
                            loading="lazy"
                        />
                        <div class="flex min-w-0 flex-1 flex-col">
                            <h3 class="font-bold">{{ s.name }}</h3>
                            <p
                                v-if="s.description"
                                class="text-muted-foreground line-clamp-2 text-sm"
                            >
                                {{ s.description }}
                            </p>
                            <p
                                class="text-muted-foreground mt-1 flex flex-wrap gap-x-3 text-xs"
                            >
                                <span class="inline-flex items-center gap-1"
                                    ><Clock class="size-3.5" />
                                    {{ s.duration }} min</span
                                >
                                <span>{{ s.modes.join(' · ') }}</span>
                            </p>
                            <div
                                class="mt-auto flex items-center justify-between gap-2 pt-2"
                            >
                                <span
                                    class="font-display text-lg font-extrabold"
                                    >{{ s.price }}</span
                                >
                                <a
                                    v-if="s.book && !viewer.isOwner"
                                    :href="s.book"
                                    target="_blank"
                                    rel="noopener"
                                    class="bg-primary text-primary-foreground inline-flex h-9 items-center gap-1.5 rounded-full px-4 text-xs font-bold"
                                >
                                    <CalendarClock class="size-3.5" /> Agendar
                                </a>
                            </div>
                        </div>
                    </article>
                </div>

                <!-- Publicaciones -->
                <div
                    v-else-if="tab === 'publicaciones'"
                    class="grid grid-cols-3 gap-1 sm:gap-2"
                >
                    <Link
                        v-if="viewer.isOwner"
                        :href="publish()"
                        class="border-border text-muted-foreground hover:border-primary hover:text-primary flex aspect-square flex-col items-center justify-center gap-1.5 border-2 border-dashed text-xs font-bold transition-colors sm:rounded-xl"
                    >
                        <ImagePlus class="size-6" /> Nueva publicación
                    </Link>
                    <button
                        v-for="p in posts"
                        :key="p.id"
                        type="button"
                        class="bg-muted group relative aspect-square overflow-hidden text-left sm:rounded-xl"
                        :aria-label="p.title"
                        @click="openPost(p)"
                    >
                        <img
                            v-if="p.media[0]"
                            :src="
                                p.media[0].type === 'video'
                                    ? (p.media[0].poster ?? p.media[0].url)
                                    : p.media[0].url
                            "
                            alt=""
                            class="size-full object-cover transition-transform duration-300 group-hover:scale-105"
                            loading="lazy"
                        />
                        <span
                            class="absolute top-2 right-2 flex gap-1 text-white drop-shadow"
                        >
                            <Play
                                v-if="p.media.some((m) => m.type === 'video')"
                                class="size-4 fill-current"
                            />
                            <Layers
                                v-else-if="p.media.length > 1"
                                class="size-4"
                            />
                        </span>
                        <span
                            class="absolute inset-0 hidden items-center justify-center gap-4 bg-black/45 text-sm font-bold text-white opacity-0 transition-opacity group-hover:opacity-100 md:flex"
                        >
                            <span class="inline-flex items-center gap-1.5"
                                ><Heart class="size-4 fill-current" />
                                {{ p.reactions }}</span
                            >
                            <span class="inline-flex items-center gap-1.5"
                                ><MessageCircle class="size-4 fill-current" />
                                {{ p.comments }}</span
                            >
                        </span>
                    </button>
                    <p
                        v-if="!posts.length"
                        class="text-muted-foreground col-span-3 py-10 text-center"
                    >
                        Todavía no hay publicaciones.
                    </p>
                </div>

                <!-- Reseñas -->
                <div
                    v-else-if="tab === 'resenas'"
                    class="grid gap-8 md:grid-cols-[260px_minmax(0,1fr)]"
                >
                    <div>
                        <template v-if="business.rating !== null">
                            <p
                                class="font-display text-5xl font-extrabold tabular-nums"
                            >
                                {{ business.rating.toFixed(1) }}
                            </p>
                            <RatingStars
                                :value="business.rating"
                                size="size-5"
                            />
                            <p class="text-muted-foreground mt-1 text-sm">
                                {{ business.ratingCount }} reseñas de pedidos
                                entregados
                            </p>
                        </template>
                        <div
                            v-else
                            class="bg-wait-soft text-wait rounded-xl p-4 text-sm"
                        >
                            <p class="font-bold">Negocio nuevo</p>
                            <p class="mt-1">
                                Mostramos el promedio a partir de
                                {{ business.minReviewsToShowAverage }} reseñas:
                                con pocas, un número engaña más de lo que
                                informa.
                            </p>
                        </div>
                        <ul class="mt-5 flex flex-col gap-1.5">
                            <li
                                v-for="d in business.distribution"
                                :key="d.stars"
                                class="flex items-center gap-2 text-xs"
                            >
                                <span class="w-3 tabular-nums">{{
                                    d.stars
                                }}</span>
                                <span
                                    class="bg-muted h-2 flex-1 overflow-hidden rounded-full"
                                >
                                    <span
                                        class="bg-star block h-full rounded-full"
                                        :style="{
                                            width: `${(d.count / maxCount) * 100}%`,
                                        }"
                                    />
                                </span>
                                <span
                                    class="text-muted-foreground w-5 text-right tabular-nums"
                                    >{{ d.count }}</span
                                >
                            </li>
                        </ul>
                        <p
                            class="text-muted-foreground mt-4 flex gap-2 text-xs"
                        >
                            <ShieldCheck class="size-4 flex-none" /> Solo
                            califica quien recibió su pedido. Las reseñas no se
                            pueden borrar.
                        </p>
                    </div>

                    <ul class="divide-border divide-y">
                        <li
                            v-for="r in reviews"
                            :key="r.id"
                            class="py-4 first:pt-0"
                        >
                            <div class="flex items-center gap-3">
                                <span
                                    class="bg-muted grid size-10 place-items-center rounded-full text-sm font-bold"
                                    >{{ r.initials }}</span
                                >
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-bold">
                                        {{ r.author }}
                                    </p>
                                    <p class="text-muted-foreground text-xs">
                                        {{ r.ago
                                        }}<template v-if="r.verified">
                                            ·
                                            <span class="text-ok font-bold"
                                                >Compra verificada</span
                                            ></template
                                        >
                                    </p>
                                </div>
                                <RatingStars :value="r.stars" />
                            </div>
                            <p
                                v-if="r.body"
                                class="mt-2 text-[15px] leading-relaxed"
                            >
                                {{ r.body }}
                            </p>
                            <div
                                v-if="r.reply"
                                class="border-brand bg-brand-soft mt-3 ml-4 rounded-r-lg border-l-2 px-3 py-2 text-sm"
                            >
                                <p class="text-brand text-xs font-bold">
                                    Respuesta de {{ business.name }} ·
                                    {{ r.reply.ago }}
                                </p>
                                <p class="mt-0.5">{{ r.reply.body }}</p>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- Información -->
                <div v-else class="grid gap-4 md:grid-cols-2">
                    <section class="bg-card rounded-2xl border p-5">
                        <h3
                            class="font-display flex items-center gap-2 font-bold"
                        >
                            <Clock class="size-4" /> Horario
                        </h3>
                        <ul class="mt-3 flex flex-col gap-1.5 text-sm">
                            <li
                                v-for="h in business.hours"
                                :key="h.day"
                                class="flex justify-between"
                            >
                                <span>{{ h.day }}</span
                                ><span class="tabular-nums">{{ h.range }}</span>
                            </li>
                        </ul>
                    </section>

                    <section class="bg-card rounded-2xl border p-5">
                        <h3
                            class="font-display flex items-center gap-2 font-bold"
                        >
                            <Truck class="size-4" />
                            {{
                                business.fulfillment.length
                                    ? 'Entregas'
                                    : 'Dónde atiende'
                            }}
                        </h3>
                        <p class="mt-3 text-sm">
                            {{
                                [
                                    ...business.fulfillment,
                                    ...business.serviceModes,
                                ].join(' · ')
                            }}
                        </p>
                        <ul
                            v-if="business.deliveryZones.length"
                            class="mt-3 flex flex-col gap-1.5 text-sm"
                        >
                            <li
                                v-for="z in business.deliveryZones"
                                :key="z.name"
                                class="flex justify-between"
                            >
                                <span>{{ z.name }}</span
                                ><span class="tabular-nums">{{ z.fee }}</span>
                            </li>
                        </ul>
                    </section>

                    <section
                        class="bg-card rounded-2xl border p-5 md:col-span-2"
                    >
                        <h3
                            class="font-display flex items-center gap-2 font-bold"
                        >
                            <Wallet class="size-4" /> Formas de pago
                        </h3>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span
                                v-for="m in business.paymentMethods"
                                :key="m"
                                class="bg-muted rounded-full px-3 py-1 text-sm"
                                >{{ m }}</span
                            >
                        </div>
                        <p class="text-muted-foreground mt-3 text-xs">
                            Le pagas directo a {{ business.name }}. Silvestre no
                            recibe ese dinero ni cobra comisión.
                        </p>
                    </section>
                </div>
            </div>
        </div>

        <StoryViewer
            v-model:open="viewerOpen"
            :groups="viewerGroups"
            :start-group="0"
        />
        <PostDialog v-model:open="dialogOpen" :post="dialogPost" />
    </SiteLayout>
</template>
