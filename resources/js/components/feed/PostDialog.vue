<script setup lang="ts">
import { onBeforeUnmount, watch } from 'vue';
import PostDetail from '@/components/feed/PostDetail.vue';
import type { PostItem } from '@/types/feed';

/**
 * La publicación abierta encima del Inicio, sin cambiar de página: al cerrar,
 * sigues exactamente donde ibas. En el celular ocupa toda la pantalla.
 */
const props = defineProps<{ post: PostItem | null; focusComments?: boolean }>();
const open = defineModel<boolean>('open', { required: true });

function onKey(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        open.value = false;
    }
}

watch(
    () => open.value && props.post !== null,
    (isOpen) => {
        document.body.style.overflow = isOpen ? 'hidden' : '';
        if (isOpen) {
            window.addEventListener('keydown', onKey);
        } else {
            window.removeEventListener('keydown', onKey);
        }
    },
);

onBeforeUnmount(() => {
    document.body.style.overflow = '';
    window.removeEventListener('keydown', onKey);
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open && post"
                class="fixed inset-0 z-[60] bg-black/70 md:flex md:items-center md:justify-center md:p-6"
                @click.self="open = false"
            >
                <Transition
                    appear
                    enter-active-class="transition duration-300 ease-out"
                    enter-from-class="translate-y-6 opacity-0 md:translate-y-0 md:scale-[0.98]"
                >
                    <div
                        role="dialog"
                        aria-modal="true"
                        :aria-label="post.title"
                        class="h-dvh w-full overflow-hidden md:h-[min(88vh,820px)] md:rounded-2xl md:shadow-2xl"
                        :class="
                            post.media.length ? 'md:max-w-6xl' : 'md:max-w-xl'
                        "
                    >
                        <PostDetail
                            :key="post.id"
                            :post="post"
                            :focus-comments="focusComments"
                            closable
                            @close="open = false"
                        />
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
