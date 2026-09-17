<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Camera,
    ExternalLink,
    ImagePlus,
    Infinity as InfinityIcon,
    UserRound,
} from '@lucide/vue';
import { ref, watch } from 'vue';
import GuideCard from '@/components/guide/GuideCard.vue';
import PostComposer from '@/components/publish/PostComposer.vue';
import ProfileEditor from '@/components/publish/ProfileEditor.vue';
import StoryComposer from '@/components/publish/StoryComposer.vue';
import { publish, show as businessShow } from '@/routes/business';

type Tab = 'publicacion' | 'historia' | 'perfil';

const props = defineProps<{
    business: {
        name: string;
        slug: string;
        avatar: string | null;
        cover: string | null;
        intro: string | null;
        about: string | null;
    };
    limits: {
        postsLeftThisWeek: number | null;
        storiesLeftToday: number | null;
        mediaPerPost: number;
        storySeconds: number;
        postVideoSeconds: number;
        maxImageMb: number;
        maxVideoMb: number;
        plan: string;
        unlimited: 'founder' | 'free' | null;
    };
    highlights: { id: number; title: string }[];
    stories: {
        id: number;
        type: string;
        url: string | null;
        caption: string | null;
        ago: string;
    }[];
    tab: Tab;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Publicar', href: publish() }],
    },
});

const tab = ref<Tab>(props.tab);
watch(
    () => props.tab,
    (value) => (tab.value = value),
);

const tabs = [
    {
        key: 'publicacion' as const,
        label: 'Publicación',
        icon: ImagePlus,
        hint: 'Fotos y videos que se quedan en tu perfil',
    },
    {
        key: 'historia' as const,
        label: 'Historia',
        icon: Camera,
        hint: 'Algo rápido que dura 24 horas',
    },
    {
        key: 'perfil' as const,
        label: 'Perfil',
        icon: UserRound,
        hint: 'Foto, portada y presentación',
    },
];

function select(key: Tab): void {
    tab.value = key;
    // La pestaña queda en la URL: al recargar o volver, sigue ahí.
    window.history.replaceState(
        window.history.state,
        '',
        publish({ query: key === 'publicacion' ? {} : { tab: key } }).url,
    );
}
</script>

<template>
    <Head title="Publicar" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-5 px-4 py-5 md:py-8">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p
                    class="text-muted-foreground text-xs font-bold tracking-[0.14em] uppercase"
                >
                    {{ business.name }}
                </p>
                <h1 class="font-display mt-1 text-3xl font-extrabold">
                    Publicar
                </h1>
                <p
                    v-if="limits.unlimited"
                    class="bg-ok-soft text-ok mt-2 inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold"
                >
                    <InfinityIcon class="size-3.5" />
                    {{
                        limits.unlimited === 'founder'
                            ? 'Negocio fundador: sin límites, para siempre'
                            : 'Sin límites por ahora'
                    }}
                </p>
            </div>
            <Link
                :href="businessShow(business.slug)"
                class="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 text-sm font-bold"
            >
                <ExternalLink class="size-4" /> Ver mi perfil
            </Link>
        </div>

        <GuideCard guide-key="publish.intro" title="¿Publicación o historia?">
            Una <strong>publicación</strong> se queda en tu perfil y aparece en
            el Inicio de tus seguidores, con reacciones y comentarios. Una
            <strong>historia</strong> es algo del momento ("salió la tanda",
            "quedan 3") y desaparece en 24 horas, salvo que la guardes en
            destacadas.
        </GuideCard>

        <div
            class="bg-muted grid grid-cols-3 gap-1 rounded-2xl p-1"
            role="tablist"
            aria-label="Qué quieres publicar"
        >
            <button
                v-for="t in tabs"
                :key="t.key"
                type="button"
                role="tab"
                :aria-selected="tab === t.key"
                class="flex flex-col items-center gap-0.5 rounded-xl px-2 py-2.5 text-sm font-bold transition-all sm:flex-row sm:justify-center sm:gap-2"
                :class="
                    tab === t.key
                        ? 'bg-background text-foreground shadow-sm'
                        : 'text-muted-foreground hover:text-foreground'
                "
                @click="select(t.key)"
            >
                <component :is="t.icon" class="size-5" />
                {{ t.label }}
            </button>
        </div>
        <p class="text-muted-foreground -mt-2 text-center text-xs">
            {{ tabs.find((t) => t.key === tab)?.hint }}
        </p>

        <section class="bg-card rounded-2xl border p-4 sm:p-6">
            <KeepAlive>
                <PostComposer v-if="tab === 'publicacion'" :limits="limits" />
                <StoryComposer
                    v-else-if="tab === 'historia'"
                    :limits="limits"
                    :highlights="highlights"
                    :stories="stories"
                />
                <ProfileEditor v-else :business="business" />
            </KeepAlive>
        </section>
    </div>
</template>
