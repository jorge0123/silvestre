<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Camera } from '@lucide/vue';
import { onBeforeUnmount, ref, useTemplateRef } from 'vue';
import BusinessAvatar from '@/components/feed/BusinessAvatar.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { textareaClass } from '@/lib/formClasses';
import { update as updateAvatar } from '@/routes/business/avatar';
import { update as updateCover } from '@/routes/business/cover';
import { update as updateProfile } from '@/routes/business/profile';

const props = defineProps<{
    business: {
        name: string;
        avatar: string | null;
        cover: string | null;
        intro: string | null;
        about: string | null;
    };
}>();

// ---- foto de perfil y portada: vista previa antes de guardar
const avatarPreview = ref<string | null>(null);
const coverPreview = ref<string | null>(null);
const avatarInput = useTemplateRef<HTMLInputElement>('avatarInput');
const coverInput = useTemplateRef<HTMLInputElement>('coverInput');

const avatarForm = useForm({ image: null as File | null });
const coverForm = useForm({ image: null as File | null });

function pick(kind: 'avatar' | 'cover', files: FileList | null): void {
    const file = files?.[0];
    if (!file) return;

    const form = kind === 'avatar' ? avatarForm : coverForm;
    const preview = kind === 'avatar' ? avatarPreview : coverPreview;

    form.clearErrors();
    if (file.size > 10 * 1024 * 1024) {
        form.setError('image', 'La foto puede pesar hasta 10 MB.');
        return;
    }
    if (preview.value) URL.revokeObjectURL(preview.value);
    preview.value = URL.createObjectURL(file);
    form.image = file;
}

function save(kind: 'avatar' | 'cover'): void {
    const form = kind === 'avatar' ? avatarForm : coverForm;
    const url = kind === 'avatar' ? updateAvatar().url : updateCover().url;

    form.post(url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            const preview = kind === 'avatar' ? avatarPreview : coverPreview;
            if (preview.value) URL.revokeObjectURL(preview.value);
            preview.value = null;
            form.reset();
        },
    });
}

function cancel(kind: 'avatar' | 'cover'): void {
    const preview = kind === 'avatar' ? avatarPreview : coverPreview;
    if (preview.value) URL.revokeObjectURL(preview.value);
    preview.value = null;
    (kind === 'avatar' ? avatarForm : coverForm).reset();
}

// ---- presentación
const textForm = useForm({
    intro: props.business.intro ?? '',
    about: props.business.about ?? '',
});

onBeforeUnmount(() => {
    if (avatarPreview.value) URL.revokeObjectURL(avatarPreview.value);
    if (coverPreview.value) URL.revokeObjectURL(coverPreview.value);
});
</script>

<template>
    <div class="grid gap-8">
        <!-- Portada + foto, tal como se ven en el perfil -->
        <section>
            <div
                class="bg-muted relative h-36 overflow-hidden rounded-2xl sm:h-48"
            >
                <img
                    v-if="coverPreview ?? business.cover"
                    :src="coverPreview ?? business.cover ?? ''"
                    alt=""
                    class="size-full object-cover"
                />
                <button
                    type="button"
                    class="absolute right-3 bottom-3 inline-flex h-9 items-center gap-2 rounded-full bg-black/60 px-3.5 text-xs font-bold text-white backdrop-blur hover:bg-black/75"
                    @click="coverInput?.click()"
                >
                    <Camera class="size-4" />
                    {{ business.cover ? 'Cambiar portada' : 'Agregar portada' }}
                </button>
            </div>
            <input
                ref="coverInput"
                type="file"
                class="hidden"
                accept="image/jpeg,image/png,image/webp"
                @change="
                    (e) => pick('cover', (e.target as HTMLInputElement).files)
                "
            />

            <div class="-mt-10 flex items-end gap-4 px-4">
                <button
                    type="button"
                    class="relative rounded-full"
                    aria-label="Cambiar foto de perfil"
                    @click="avatarInput?.click()"
                >
                    <span class="bg-background inline-block rounded-full p-1">
                        <BusinessAvatar
                            :src="avatarPreview ?? business.avatar"
                            :name="business.name"
                            size="xl"
                        />
                    </span>
                    <span
                        class="bg-foreground text-background absolute right-1 bottom-1 grid size-9 place-items-center rounded-full border-2 border-[var(--background)]"
                    >
                        <Camera class="size-4" />
                    </span>
                </button>
                <input
                    ref="avatarInput"
                    type="file"
                    class="hidden"
                    accept="image/jpeg,image/png,image/webp"
                    @change="
                        (e) =>
                            pick('avatar', (e.target as HTMLInputElement).files)
                    "
                />
                <p class="text-muted-foreground pb-2 text-xs">
                    Foto de perfil: cuadrada, tu logo o lo que vendes.<br />Portada:
                    horizontal, se recorta a 1600 × 640.
                </p>
            </div>

            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0 translate-y-1"
            >
                <div
                    v-if="avatarPreview || coverPreview"
                    class="bg-primary/[0.05] mt-4 flex flex-wrap items-center gap-2 rounded-xl p-3"
                >
                    <p class="mr-auto text-sm font-bold">
                        Así se verá. ¿Guardamos?
                    </p>
                    <template v-if="coverPreview">
                        <Button
                            variant="ghost"
                            size="sm"
                            class="rounded-full"
                            @click="cancel('cover')"
                            >Descartar portada</Button
                        >
                        <Button
                            size="sm"
                            class="rounded-full"
                            :disabled="coverForm.processing"
                            @click="save('cover')"
                        >
                            <Spinner v-if="coverForm.processing" /> Guardar
                            portada
                        </Button>
                    </template>
                    <template v-if="avatarPreview">
                        <Button
                            variant="ghost"
                            size="sm"
                            class="rounded-full"
                            @click="cancel('avatar')"
                            >Descartar foto</Button
                        >
                        <Button
                            size="sm"
                            class="rounded-full"
                            :disabled="avatarForm.processing"
                            @click="save('avatar')"
                        >
                            <Spinner v-if="avatarForm.processing" /> Guardar
                            foto
                        </Button>
                    </template>
                </div>
            </Transition>
            <InputError
                :message="avatarForm.errors.image ?? coverForm.errors.image"
                class="mt-2"
            />
        </section>

        <!-- Presentación -->
        <form
            class="grid gap-5"
            @submit.prevent="
                textForm.patch(updateProfile().url, { preserveScroll: true })
            "
        >
            <div class="grid gap-2">
                <div class="flex items-baseline justify-between">
                    <Label for="intro">Frase de presentación</Label>
                    <span class="text-muted-foreground text-xs tabular-nums"
                        >{{ textForm.intro.length }}/160</span
                    >
                </div>
                <textarea
                    id="intro"
                    v-model="textForm.intro"
                    :class="textareaClass"
                    rows="2"
                    maxlength="160"
                    required
                />
                <InputError :message="textForm.errors.intro" />
            </div>
            <div class="grid gap-2">
                <div class="flex items-baseline justify-between">
                    <Label for="about">Tu historia</Label>
                    <span class="text-muted-foreground text-xs tabular-nums"
                        >{{ textForm.about.length }}/1500</span
                    >
                </div>
                <textarea
                    id="about"
                    v-model="textForm.about"
                    :class="textareaClass"
                    rows="5"
                    maxlength="1500"
                />
                <InputError :message="textForm.errors.about" />
            </div>
            <Button
                type="submit"
                class="h-11 rounded-full font-bold sm:justify-self-start sm:px-8"
                :disabled="textForm.processing || !textForm.isDirty"
            >
                <Spinner v-if="textForm.processing" /> Guardar presentación
            </Button>
        </form>
    </div>
</template>
