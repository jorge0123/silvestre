<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Camera,
    CircleCheck,
    Compass,
    ImagePlus,
    Store,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, useTemplateRef } from 'vue';
import BusinessAvatar from '@/components/feed/BusinessAvatar.vue';
import PostCard from '@/components/feed/PostCard.vue';
import PostDialog from '@/components/feed/PostDialog.vue';
import StoryViewer from '@/components/feed/StoryViewer.vue';
import SuggestedRow from '@/components/feed/SuggestedRow.vue';
import GuideCard from '@/components/guide/GuideCard.vue';
import { api } from '@/lib/http';
import { feed as feedRoute } from '@/routes';
import { publish } from '@/routes/business';
import { more } from '@/routes/feed';
import { show as onboarding } from '@/routes/onboarding';
import type { FeedItem, FeedPage, PostItem, StoryGroup } from '@/types/feed';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Inicio', href: feedRoute() }],
    },
});

const props = defineProps<{
    exploring: boolean;
    followingCount: number;
    stories: StoryGroup[];
    items: FeedItem[];
    page: number;
    hasMore: boolean;
    unfinished: { name: string; slug: string } | null;
}>();

const inertiaPage = usePage();
const mode = computed(() => inertiaPage.props.auth.mode);

// ---------------------------------------------------------- scroll infinito
const items = ref<FeedItem[]>([...props.items]);
const currentPage = ref(props.page);
const hasMore = ref(props.hasMore);
const loading = ref(false);
const failed = ref(false);
const sentinel = useTemplateRef<HTMLDivElement>('sentinel');
let observer: IntersectionObserver | null = null;

async function loadMore(): Promise<void> {
    if (loading.value || !hasMore.value) return;

    loading.value = true;
    failed.value = false;
    try {
        const next = await api<FeedPage>(
            'GET',
            more({ query: { page: currentPage.value + 1 } }).url,
        );
        const seen = new Set(items.value.map((i) => i.key));
        items.value.push(...next.items.filter((i) => !seen.has(i.key)));
        currentPage.value = next.page;
        hasMore.value = next.hasMore;
    } catch {
        failed.value = true;
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    // Empieza a cargar antes de llegar al final, para que nunca se vea vacío.
    observer = new IntersectionObserver(
        (entries) => entries[0]?.isIntersecting && void loadMore(),
        {
            rootMargin: '900px 0px',
        },
    );
    if (sentinel.value) observer.observe(sentinel.value);
});

onBeforeUnmount(() => observer?.disconnect());

// ----------------------------------------------------- publicación abierta
const dialogOpen = ref(false);
const dialogPost = ref<PostItem | null>(null);
const dialogFocus = ref(false);

function openPost(post: PostItem, focusComments: boolean): void {
    dialogPost.value = post;
    dialogFocus.value = focusComments;
    dialogOpen.value = true;
}

// --------------------------------------------------------------- historias
const viewerOpen = ref(false);
const viewerGroup = ref(0);

function openStories(index: number): void {
    viewerGroup.value = index;
    viewerOpen.value = true;
}

const hasPosts = computed(() => items.value.some((i) => i.kind === 'post'));
</script>

<template>
    <Head title="Inicio" />

    <div
        class="mx-auto flex w-full max-w-[640px] flex-col gap-3 pb-24 sm:gap-4 sm:px-4 sm:py-5"
    >
        <!-- Historias -->
        <section
            v-if="stories.length || mode?.type === 'business'"
            aria-label="Historias"
            class="bg-card border-y py-3 sm:rounded-2xl sm:border"
        >
            <ul
                class="flex [scrollbar-width:none] gap-3 overflow-x-auto px-4 [&::-webkit-scrollbar]:hidden"
            >
                <li v-if="mode?.type === 'business'" class="flex-none">
                    <Link
                        :href="publish({ query: { tab: 'historia' } })"
                        class="flex w-[72px] flex-col items-center gap-1.5 active:scale-95"
                    >
                        <span
                            class="border-primary/50 bg-primary/5 text-primary relative grid size-16 place-items-center rounded-full border-2 border-dashed"
                        >
                            <Camera class="size-6" />
                        </span>
                        <span
                            class="w-full truncate text-center text-xs font-bold"
                            >Tu historia</span
                        >
                    </Link>
                </li>
                <li
                    v-for="(group, i) in stories"
                    :key="group.business.slug"
                    class="flex-none"
                >
                    <button
                        type="button"
                        class="flex w-[72px] flex-col items-center gap-1.5 active:scale-95"
                        :aria-label="`Ver historias de ${group.business.name}`"
                        @click="openStories(i)"
                    >
                        <BusinessAvatar
                            :src="group.business.avatar"
                            :name="group.business.name"
                            size="lg"
                            ring
                        />
                        <span class="w-full truncate text-center text-xs">{{
                            group.business.name
                        }}</span>
                    </button>
                </li>
            </ul>
        </section>

        <!-- Publicar (modo negocio), como "¿Qué estás pensando?" -->
        <section
            v-if="mode?.type === 'business' && mode.business"
            class="bg-card flex items-center gap-3 border-y px-4 py-3 sm:rounded-2xl sm:border"
        >
            <Store class="text-muted-foreground size-5 flex-none" />
            <Link
                :href="publish()"
                class="bg-muted text-muted-foreground hover:bg-muted/70 flex h-11 flex-1 items-center rounded-full px-4 text-sm"
            >
                ¿Qué hay de nuevo en {{ mode.business.name }}?
            </Link>
            <Link
                :href="publish()"
                class="text-primary hover:bg-primary/10 grid size-11 flex-none place-items-center rounded-full"
                aria-label="Publicar fotos o videos"
            >
                <ImagePlus class="size-5" />
            </Link>
        </section>

        <div class="flex flex-col gap-3 px-4 sm:px-0">
            <GuideCard
                guide-key="feed.intro"
                title="Así funciona tu Inicio"
                :steps="[
                    {
                        title: 'Historias',
                        text: 'Arriba, lo que compartieron en las últimas 24 horas. Tócalas.',
                    },
                    {
                        title: 'Publicaciones',
                        text: 'Toca una foto para verla con sus comentarios. Dos toques rápidos = Me gusta.',
                    },
                    {
                        title: 'Sugeridos y Promocionado',
                        text: 'Al bajar aparecen negocios nuevos. Lo pagado siempre va marcado.',
                    },
                ]"
            >
                Como en otras redes, pero aquí todo es de negocios de tu ciudad.
            </GuideCard>

            <div
                v-if="unfinished"
                class="border-primary/30 bg-primary/[0.04] flex flex-wrap items-center gap-3 rounded-2xl border p-4"
            >
                <span
                    class="bg-primary text-primary-foreground grid size-10 place-items-center rounded-xl"
                    ><Store class="size-5"
                /></span>
                <p class="min-w-0 flex-1 text-sm">
                    <strong>{{ unfinished.name }}</strong> quedó a medias. Todo
                    está guardado.
                </p>
                <Link
                    :href="onboarding()"
                    class="bg-primary text-primary-foreground inline-flex h-10 items-center gap-2 rounded-full px-4 text-sm font-bold"
                >
                    Continuar <ArrowRight class="size-4" />
                </Link>
            </div>

            <div
                v-if="exploring"
                class="bg-brand-soft text-brand flex gap-3 rounded-2xl p-4 text-sm"
            >
                <Compass class="mt-0.5 size-5 flex-none" />
                <p>
                    <strong>Todavía no sigues ningún negocio.</strong> Te
                    mostramos lo más reciente de la ciudad. Toca
                    <strong>Seguir</strong> en los que te gusten.
                </p>
            </div>
        </div>

        <!-- El flujo -->
        <TransitionGroup
            tag="div"
            class="flex flex-col gap-3 sm:gap-4"
            enter-active-class="transition duration-500 ease-out"
            enter-from-class="opacity-0 translate-y-3"
        >
            <template v-for="item in items" :key="item.key">
                <PostCard
                    v-if="item.kind === 'post'"
                    :post="item.post"
                    :variant="item.variant"
                    @open="(focus) => openPost(item.post, focus)"
                />

                <SuggestedRow
                    v-else-if="item.kind === 'suggested'"
                    :title="item.title"
                    :businesses="item.businesses"
                />

                <div
                    v-else-if="item.kind === 'caught_up'"
                    class="flex flex-col items-center gap-1 px-6 py-6 text-center"
                >
                    <CircleCheck class="text-ok size-9" />
                    <p class="font-display text-lg font-bold">
                        Ya estás al día
                    </p>
                    <p class="text-muted-foreground text-sm">
                        Viste lo nuevo de los negocios que sigues. Aquí abajo,
                        otros que te pueden gustar.
                    </p>
                </div>
            </template>
        </TransitionGroup>

        <!-- Cargando más -->
        <div
            v-if="loading"
            class="flex flex-col gap-4"
            aria-busy="true"
            aria-label="Cargando más publicaciones"
        >
            <div
                v-for="n in 2"
                :key="n"
                class="bg-card border-y sm:rounded-2xl sm:border"
            >
                <div class="flex items-center gap-3 px-4 py-3">
                    <span class="bg-muted size-11 animate-pulse rounded-full" />
                    <span class="flex-1 space-y-2">
                        <span
                            class="bg-muted block h-3 w-1/3 animate-pulse rounded"
                        />
                        <span
                            class="bg-muted block h-2.5 w-1/4 animate-pulse rounded"
                        />
                    </span>
                </div>
                <span class="bg-muted block aspect-square animate-pulse" />
            </div>
        </div>

        <p
            v-if="failed"
            class="text-muted-foreground px-4 py-6 text-center text-sm"
        >
            No se pudieron cargar más publicaciones.
            <button
                type="button"
                class="text-primary font-bold"
                @click="loadMore"
            >
                Reintentar
            </button>
        </p>

        <div ref="sentinel" aria-hidden="true" class="h-1" />

        <div
            v-if="!hasMore && !loading && hasPosts"
            class="text-muted-foreground px-4 py-8 text-center text-sm"
        >
            Eso es todo por ahora. Vuelve más tarde para ver novedades.
        </div>
        <p
            v-else-if="!hasPosts && !loading"
            class="text-muted-foreground px-4 py-10 text-center"
        >
            Aún no hay publicaciones. Sigue algunos negocios para empezar.
        </p>
    </div>

    <!-- Publicar rápido en el celular (modo negocio) -->
    <Link
        v-if="mode?.type === 'business'"
        :href="publish()"
        class="bg-primary text-primary-foreground fixed right-4 bottom-[calc(1.25rem+env(safe-area-inset-bottom))] z-40 grid size-14 place-items-center rounded-full shadow-lg shadow-black/25 transition-transform active:scale-95 md:hidden"
        aria-label="Publicar"
    >
        <ImagePlus class="size-6" />
    </Link>

    <PostDialog
        v-model:open="dialogOpen"
        :post="dialogPost"
        :focus-comments="dialogFocus"
    />
    <StoryViewer
        v-if="stories.length"
        v-model:open="viewerOpen"
        :groups="stories"
        :start-group="viewerGroup"
    />
</template>
