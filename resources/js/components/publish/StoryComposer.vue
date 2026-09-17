<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Camera, Play, RefreshCw, Trash2 } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, useTemplateRef } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { selectClass } from '@/lib/formClasses';
import { ACCEPT, inspect, type Picked } from '@/lib/mediaCheck';
import { destroy, store } from '@/routes/business/stories';

type Limits = { storiesLeftToday: number | null; storySeconds: number; maxImageMb: number; maxVideoMb: number };
type LiveStory = { id: number; type: string; url: string | null; caption: string | null; ago: string };

const props = defineProps<{
    limits: Limits;
    highlights: { id: number; title: string }[];
    stories: LiveStory[];
}>();

const picked = ref<Picked | null>(null);
const problem = ref<string | null>(null);
const confirmingId = ref<number | null>(null);
const input = useTemplateRef<HTMLInputElement>('input');

const form = useForm({
    media: null as File | null,
    caption: '',
    highlight_id: '' as number | '' | 'new',
    new_highlight: '',
});

const blocked = computed(() => props.limits.storiesLeftToday === 0);

async function choose(files: FileList | null): Promise<void> {
    const file = files?.[0];
    if (!file) return;

    problem.value = null;
    const { item, error } = await inspect(file, {
        maxImageMb: props.limits.maxImageMb,
        maxVideoMb: props.limits.maxVideoMb,
        maxVideoSeconds: props.limits.storySeconds,
    });

    if (error) {
        problem.value = error;
    } else if (item) {
        if (picked.value) URL.revokeObjectURL(picked.value.url);
        picked.value = item;
    }
    if (input.value) input.value.value = '';
}

function submit(): void {
    if (!picked.value) return;

    form
        .transform((data) => ({
            media: picked.value?.file ?? null,
            caption: data.caption,
            highlight_id: typeof data.highlight_id === 'number' ? data.highlight_id : null,
            new_highlight: data.highlight_id === 'new' ? data.new_highlight : null,
        }))
        .post(store().url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                if (picked.value) URL.revokeObjectURL(picked.value.url);
                picked.value = null;
                form.reset();
            },
        });
}

function remove(id: number): void {
    router.delete(destroy(id).url, { preserveScroll: true, onFinish: () => (confirmingId.value = null) });
}

onBeforeUnmount(() => picked.value && URL.revokeObjectURL(picked.value.url));
</script>

<template>
    <div class="grid gap-8 md:grid-cols-[260px_minmax(0,1fr)]">
        <!-- Vista previa vertical, como se verá -->
        <div>
            <button
                type="button"
                class="bg-muted border-border group relative mx-auto flex aspect-[9/16] w-full max-w-[260px] flex-col items-center justify-center gap-2 overflow-hidden rounded-2xl border-2 border-dashed"
                :class="picked ? 'border-transparent' : 'hover:border-primary'"
                :disabled="blocked"
                @click="input?.click()"
            >
                <template v-if="picked">
                    <video v-if="picked.type === 'video'" :src="picked.url" class="absolute inset-0 size-full object-cover" autoplay muted loop playsinline />
                    <img v-else :src="picked.url" alt="" class="absolute inset-0 size-full object-cover" />
                    <span class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/75 to-transparent px-3 pt-10 pb-4 text-left">
                        <span v-if="form.caption" class="font-display block text-lg leading-snug font-bold text-white">{{ form.caption }}</span>
                    </span>
                    <span class="absolute top-2 right-2 inline-flex items-center gap-1 rounded-full bg-black/60 px-2.5 py-1 text-xs font-bold text-white opacity-0 transition-opacity group-hover:opacity-100">
                        <RefreshCw class="size-3" /> Cambiar
                    </span>
                </template>
                <template v-else>
                    <span class="bg-primary/10 text-primary grid size-14 place-items-center rounded-full"><Camera class="size-7" /></span>
                    <span class="font-bold">Elegir foto o video</span>
                    <span class="text-muted-foreground px-6 text-center text-xs">Vertical se ve mejor · video de hasta {{ limits.storySeconds }} s</span>
                </template>
            </button>
            <input ref="input" type="file" class="hidden" :accept="ACCEPT" @change="(e) => choose((e.target as HTMLInputElement).files)" />
            <p v-if="problem" class="text-destructive mt-2 text-sm">{{ problem }}</p>
            <InputError :message="form.errors.media" />
        </div>

        <form class="grid content-start gap-5" @submit.prevent="submit">
            <div v-if="blocked" class="bg-wait-soft text-wait rounded-xl p-4 text-sm">
                <strong>Ya subiste las historias de hoy.</strong> Mañana puedes subir más.
            </div>
            <p v-else-if="limits.storiesLeftToday !== null" class="text-muted-foreground text-sm">
                Te quedan <strong class="text-foreground">{{ limits.storiesLeftToday }}</strong> historias hoy. Cada una dura 24 horas.
            </p>

            <div class="grid gap-2">
                <div class="flex items-baseline justify-between">
                    <Label for="story-caption">Texto <span class="text-muted-foreground font-normal">(opcional)</span></Label>
                    <span class="text-muted-foreground text-xs tabular-nums">{{ form.caption.length }}/120</span>
                </div>
                <Input id="story-caption" v-model="form.caption" class="h-11" maxlength="120" placeholder="Ej. Saliendo del horno" />
                <InputError :message="form.errors.caption" />
            </div>

            <div class="grid gap-2">
                <Label for="story-highlight">Guardar en destacadas</Label>
                <select id="story-highlight" v-model="form.highlight_id" :class="selectClass">
                    <option value="">No, solo 24 horas</option>
                    <option v-for="h in highlights" :key="h.id" :value="h.id">{{ h.title }}</option>
                    <option value="new">+ Nueva destacada…</option>
                </select>
                <Input v-if="form.highlight_id === 'new'" v-model="form.new_highlight" class="h-11" maxlength="40" placeholder="Nombre, ej. Pedidos especiales" />
                <p class="text-muted-foreground text-xs">Las destacadas se quedan fijas en tu perfil aunque pasen las 24 horas.</p>
                <InputError :message="form.errors.highlight_id ?? form.errors.new_highlight" />
            </div>

            <div v-if="form.progress" class="bg-muted h-2 overflow-hidden rounded-full" aria-live="polite">
                <div class="bg-primary h-full rounded-full transition-[width]" :style="{ width: `${form.progress.percentage}%` }" />
            </div>

            <Button type="submit" class="h-12 rounded-full text-[15px] font-bold" :disabled="form.processing || blocked || !picked">
                <Spinner v-if="form.processing" /> {{ form.processing ? 'Publicando…' : 'Publicar historia' }}
            </Button>

            <!-- Historias activas -->
            <section v-if="stories.length" class="mt-4">
                <h3 class="font-display mb-3 font-bold">Tus historias activas</h3>
                <ul class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                    <li v-for="s in stories" :key="s.id" class="bg-muted relative aspect-[9/16] overflow-hidden rounded-xl">
                        <img v-if="s.url" :src="s.url" alt="" class="size-full object-cover" />
                        <Play v-if="s.type === 'video'" class="absolute top-2 left-2 size-4 fill-white text-white drop-shadow" />
                        <span class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent p-1.5 text-[10px] text-white">{{ s.ago }}</span>
                        <div v-if="confirmingId === s.id" class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-black/70 p-2 text-center text-xs text-white">
                            ¿Eliminar?
                            <span class="flex gap-1.5">
                                <button type="button" class="rounded-full bg-white/20 px-2.5 py-1 font-bold" @click="confirmingId = null">No</button>
                                <button type="button" class="bg-destructive rounded-full px-2.5 py-1 font-bold" @click="remove(s.id)">Sí</button>
                            </span>
                        </div>
                        <button v-else type="button" class="absolute top-1.5 right-1.5 grid size-7 place-items-center rounded-full bg-black/60 text-white" aria-label="Eliminar historia" @click="confirmingId = s.id">
                            <Trash2 class="size-3.5" />
                        </button>
                    </li>
                </ul>
            </section>
        </form>
    </div>
</template>
