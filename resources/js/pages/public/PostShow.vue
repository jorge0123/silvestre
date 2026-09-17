<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { Layers, MessageCircle, Play } from '@lucide/vue';
import { computed, ref } from 'vue';
import BackButton from '@/components/BackButton.vue';
import PostDetail from '@/components/feed/PostDetail.vue';
import PostDialog from '@/components/feed/PostDialog.vue';
import SiteLayout from '@/layouts/SiteLayout.vue';
import { feed, home } from '@/routes';
import { show as businessShow } from '@/routes/business';
import type { PostItem } from '@/types/feed';

/** La página propia de una publicación: es el enlace que se comparte. */
const props = defineProps<{
    post: PostItem;
    more: PostItem[];
    whatsapp: string | null;
    viewer: { isGuest: boolean; isFollowing: boolean; isOwner: boolean };
}>();

const signedIn = computed(() => Boolean(usePage().props.auth?.user));
const dialogOpen = ref(false);
const dialogPost = ref<PostItem | null>(null);

function openPost(post: PostItem): void {
    dialogPost.value = post;
    dialogOpen.value = true;
}
</script>

<template>
    <Head :title="`${post.title} · ${post.business.name}`">
        <meta name="description" :content="post.body.slice(0, 150)" />
    </Head>

    <SiteLayout :breadcrumbs="[{ title: post.business.name, href: businessShow(post.business.slug) }, { title: 'Publicación', href: '#' }]">
        <div class="mx-auto max-w-6xl px-0 pb-12 sm:px-4">
            <div class="px-2 py-2 sm:px-0">
                <BackButton :fallback="signedIn ? feed().url : businessShow(post.business.slug).url" />
            </div>

            <div class="bg-card overflow-hidden border-y sm:rounded-2xl sm:border md:h-[min(84vh,820px)]">
                <PostDetail :post="post" :contained="false" />
            </div>

            <a
                v-if="whatsapp && !viewer.isOwner"
                :href="whatsapp"
                target="_blank"
                rel="noopener"
                class="bg-primary text-primary-foreground mx-4 mt-4 flex h-12 items-center justify-center gap-2 rounded-full text-sm font-bold sm:mx-0 sm:inline-flex sm:px-6"
            >
                <MessageCircle class="size-4" /> Preguntar por WhatsApp
            </a>

            <section v-if="more.length" class="mt-10 px-4 sm:px-0">
                <h2 class="font-display mb-4 text-lg font-bold">Más de {{ props.post.business.name }}</h2>
                <div class="grid grid-cols-3 gap-1 sm:gap-2">
                    <button
                        v-for="p in more"
                        :key="p.id"
                        type="button"
                        class="bg-muted group relative aspect-square overflow-hidden sm:rounded-xl"
                        :aria-label="p.title"
                        @click="openPost(p)"
                    >
                        <img
                            v-if="p.media[0]"
                            :src="p.media[0].type === 'video' ? (p.media[0].poster ?? p.media[0].url) : p.media[0].url"
                            alt=""
                            class="size-full object-cover transition-transform duration-300 group-hover:scale-105"
                            loading="lazy"
                        />
                        <span class="absolute top-2 right-2 text-white drop-shadow">
                            <Play v-if="p.media.some((m) => m.type === 'video')" class="size-4 fill-current" />
                            <Layers v-else-if="p.media.length > 1" class="size-4" />
                        </span>
                    </button>
                </div>
            </section>
        </div>

        <PostDialog v-model:open="dialogOpen" :post="dialogPost" />
    </SiteLayout>
</template>
