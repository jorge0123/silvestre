<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    ImagePlus,
    Play,
    Trash2,
    UploadCloud,
} from '@lucide/vue';
import { computed, onBeforeUnmount, ref, useTemplateRef } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { ACCEPT, formatSeconds, inspect, type Picked } from '@/lib/mediaCheck';
import { textareaClass } from '@/lib/formClasses';
import { store } from '@/routes/business/posts';

type Limits = {
    postsLeftThisWeek: number | null;
    mediaPerPost: number;
    postVideoSeconds: number;
    maxImageMb: number;
    maxVideoMb: number;
    plan: string;
};

const props = defineProps<{ limits: Limits }>();

const picked = ref<Picked[]>([]);
const problems = ref<string[]>([]);
const dragging = ref(false);
const input = useTemplateRef<HTMLInputElement>('input');

const form = useForm({
    title: '',
    body: '',
    media: [] as File[],
    also_story: true,
});

const blocked = computed(() => props.limits.postsLeftThisWeek === 0);
const slotsLeft = computed(
    () => props.limits.mediaPerPost - picked.value.length,
);

async function add(files: FileList | File[]): Promise<void> {
    problems.value = [];
    const list = Array.from(files);

    if (list.length > slotsLeft.value) {
        problems.value.push(
            `Puedes agregar ${slotsLeft.value} más (máximo ${props.limits.mediaPerPost} por publicación).`,
        );
    }

    for (const file of list.slice(0, Math.max(0, slotsLeft.value))) {
        const { item, error } = await inspect(file, {
            maxImageMb: props.limits.maxImageMb,
            maxVideoMb: props.limits.maxVideoMb,
            maxVideoSeconds: props.limits.postVideoSeconds,
        });
        if (item) picked.value.push(item);
        if (error) problems.value.push(error);
    }

    if (input.value) input.value.value = '';
}

function remove(id: string): void {
    const item = picked.value.find((p) => p.id === id);
    if (item) URL.revokeObjectURL(item.url);
    picked.value = picked.value.filter((p) => p.id !== id);
}

function move(index: number, delta: number): void {
    const target = index + delta;
    if (target < 0 || target >= picked.value.length) return;
    const list = [...picked.value];
    [list[index], list[target]] = [list[target], list[index]];
    picked.value = list;
}

function onDrop(event: DragEvent): void {
    dragging.value = false;
    if (event.dataTransfer?.files.length) void add(event.dataTransfer.files);
}

function submit(): void {
    form.media = picked.value.map((p) => p.file);
    form.post(store().url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            picked.value.forEach((p) => URL.revokeObjectURL(p.url));
            picked.value = [];
        },
    });
}

const mediaError = computed(() => {
    const errors = form.errors as Record<string, string>;

    return (
        errors.media ??
        Object.entries(errors).find(([k]) => k.startsWith('media.'))?.[1]
    );
});

onBeforeUnmount(() => picked.value.forEach((p) => URL.revokeObjectURL(p.url)));
</script>

<template>
    <form class="grid gap-5" @submit.prevent="submit">
        <div
            v-if="blocked"
            class="bg-wait-soft text-wait rounded-xl p-4 text-sm"
        >
            <strong>Ya usaste tus publicaciones de esta semana.</strong> Se
            reinician el lunes. Mientras tanto puedes subir historias.
        </div>
        <p
            v-else-if="limits.postsLeftThisWeek !== null"
            class="text-muted-foreground text-sm"
        >
            Te quedan
            <strong class="text-foreground">{{
                limits.postsLeftThisWeek
            }}</strong>
            publicaciones esta semana.
        </p>

        <!-- Fotos y videos -->
        <div>
            <div class="mb-2 flex items-baseline justify-between">
                <Label>Fotos y videos</Label>
                <span class="text-muted-foreground text-xs tabular-nums"
                    >{{ picked.length }}/{{ limits.mediaPerPost }}</span
                >
            </div>

            <div
                v-if="!picked.length"
                class="border-border hover:border-primary hover:bg-primary/[0.03] flex cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed px-6 py-10 text-center transition-colors"
                :class="dragging ? 'border-primary bg-primary/[0.05]' : ''"
                role="button"
                tabindex="0"
                @click="input?.click()"
                @keydown.enter.prevent="input?.click()"
                @dragover.prevent="dragging = true"
                @dragleave="dragging = false"
                @drop.prevent="onDrop"
            >
                <span
                    class="bg-primary/10 text-primary grid size-14 place-items-center rounded-full"
                    ><UploadCloud class="size-7"
                /></span>
                <p class="font-bold">Elige fotos o videos</p>
                <p class="text-muted-foreground text-sm">
                    Hasta {{ limits.mediaPerPost }} · fotos de
                    {{ limits.maxImageMb }} MB · videos de hasta
                    {{ Math.floor(limits.postVideoSeconds / 60) }} min
                </p>
            </div>

            <TransitionGroup
                v-else
                tag="ul"
                class="grid grid-cols-3 gap-2 sm:grid-cols-4"
                move-class="transition-transform duration-300"
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0 scale-95"
            >
                <li
                    v-for="(item, i) in picked"
                    :key="item.id"
                    class="bg-muted group relative aspect-square overflow-hidden rounded-xl"
                >
                    <video
                        v-if="item.type === 'video'"
                        :src="item.url"
                        class="size-full object-cover"
                        muted
                        playsinline
                        preload="metadata"
                    />
                    <img
                        v-else
                        :src="item.url"
                        alt=""
                        class="size-full object-cover"
                    />
                    <span
                        v-if="i === 0"
                        class="bg-foreground text-background absolute top-1.5 left-1.5 rounded-full px-2 py-0.5 text-[10px] font-bold"
                        >Portada</span
                    >
                    <span
                        v-if="item.type === 'video'"
                        class="absolute bottom-1.5 left-1.5 inline-flex items-center gap-1 rounded-full bg-black/60 px-1.5 py-0.5 text-[10px] font-bold text-white"
                    >
                        <Play class="size-2.5 fill-current" />
                        {{ formatSeconds(item.seconds) }}
                    </span>
                    <div
                        class="absolute inset-x-1.5 bottom-1.5 flex justify-end gap-1"
                    >
                        <button
                            v-if="i > 0"
                            type="button"
                            class="grid size-7 place-items-center rounded-full bg-black/60 text-white"
                            :aria-label="`Mover ${i + 1} a la izquierda`"
                            @click="move(i, -1)"
                        >
                            <ArrowLeft class="size-3.5" />
                        </button>
                        <button
                            v-if="i < picked.length - 1"
                            type="button"
                            class="grid size-7 place-items-center rounded-full bg-black/60 text-white"
                            :aria-label="`Mover ${i + 1} a la derecha`"
                            @click="move(i, 1)"
                        >
                            <ArrowRight class="size-3.5" />
                        </button>
                    </div>
                    <button
                        type="button"
                        class="absolute top-1.5 right-1.5 grid size-7 place-items-center rounded-full bg-black/60 text-white hover:bg-black/80"
                        :aria-label="`Quitar archivo ${i + 1}`"
                        @click="remove(item.id)"
                    >
                        <Trash2 class="size-3.5" />
                    </button>
                </li>
                <li v-if="slotsLeft > 0" key="add">
                    <button
                        type="button"
                        class="border-border text-muted-foreground hover:border-primary hover:text-primary flex aspect-square w-full flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed text-xs font-bold"
                        @click="input?.click()"
                    >
                        <ImagePlus class="size-6" /> Agregar
                    </button>
                </li>
            </TransitionGroup>

            <input
                ref="input"
                type="file"
                class="hidden"
                multiple
                :accept="ACCEPT"
                @change="(e) => add((e.target as HTMLInputElement).files ?? [])"
            />

            <ul
                v-if="problems.length"
                class="text-destructive mt-2 space-y-1 text-sm"
            >
                <li v-for="p in problems" :key="p">{{ p }}</li>
            </ul>
            <InputError :message="mediaError" />
        </div>

        <div class="grid gap-2">
            <div class="flex items-baseline justify-between">
                <Label for="post-title">Título</Label>
                <span class="text-muted-foreground text-xs tabular-nums"
                    >{{ form.title.length }}/120</span
                >
            </div>
            <Input
                id="post-title"
                v-model="form.title"
                class="h-11"
                maxlength="120"
                placeholder="Ej. Horneada del jueves: avena y pasas"
                required
            />
            <InputError :message="form.errors.title" />
        </div>

        <div class="grid gap-2">
            <div class="flex items-baseline justify-between">
                <Label for="post-body"
                    >Cuéntales más
                    <span class="text-muted-foreground font-normal"
                        >(opcional)</span
                    ></Label
                >
                <span class="text-muted-foreground text-xs tabular-nums"
                    >{{ form.body.length }}/3000</span
                >
            </div>
            <textarea
                id="post-body"
                v-model="form.body"
                :class="textareaClass"
                rows="4"
                maxlength="3000"
                placeholder="Precio, cómo pedir, hasta cuándo hay…"
            />
            <InputError :message="form.errors.body" />
        </div>

        <label
            class="bg-muted/60 flex cursor-pointer items-center gap-3 rounded-xl p-3.5"
        >
            <input
                v-model="form.also_story"
                type="checkbox"
                class="accent-primary size-5"
            />
            <span class="text-sm">
                <strong>Compartir también como historia</strong>
                <span class="text-muted-foreground block"
                    >La primera foto o video sale 24 horas arriba del Inicio de
                    tus seguidores.</span
                >
            </span>
        </label>

        <!-- Progreso real de la subida -->
        <div v-if="form.progress" class="grid gap-1.5" aria-live="polite">
            <div class="bg-muted h-2 overflow-hidden rounded-full">
                <div
                    class="bg-primary h-full rounded-full transition-[width] duration-200"
                    :style="{ width: `${form.progress.percentage}%` }"
                />
            </div>
            <p class="text-muted-foreground text-xs tabular-nums">
                {{
                    form.progress.percentage === 100
                        ? 'Procesando fotos y videos…'
                        : `Subiendo… ${form.progress.percentage}%`
                }}
            </p>
        </div>

        <Button
            type="submit"
            class="h-12 rounded-full text-[15px] font-bold"
            :disabled="
                form.processing ||
                blocked ||
                !picked.length ||
                !form.title.trim()
            "
        >
            <Spinner v-if="form.processing" />
            {{ form.processing ? 'Publicando…' : 'Publicar' }}
        </Button>
    </form>
</template>
