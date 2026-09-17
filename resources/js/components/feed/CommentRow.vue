<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BadgeCheck,
    EllipsisVertical,
    Flag,
    Pencil,
    Trash2,
} from '@lucide/vue';
import { nextTick, ref, useTemplateRef } from 'vue';
import { toast } from 'vue-sonner';
import BusinessAvatar from '@/components/feed/BusinessAvatar.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Spinner } from '@/components/ui/spinner';
import { ApiError, api } from '@/lib/http';
import { show as businessShow } from '@/routes/business';
import { destroy, report, update } from '@/routes/comments';
import type { CommentItem } from '@/types/feed';

const props = defineProps<{
    comment: CommentItem;
    isReply?: boolean;
    canReply: boolean;
}>();

const emit = defineEmits<{
    reply: [comment: CommentItem];
    updated: [comment: CommentItem];
    deleted: [payload: { id: number; count: number }];
}>();

const editing = ref(false);
const draft = ref('');
const saving = ref(false);
const confirmingDelete = ref(false);
const deleting = ref(false);
const editor = useTemplateRef<HTMLTextAreaElement>('editor');

const reasons = [
    { value: 'harassment', label: 'Acoso u ofensas' },
    { value: 'scam', label: 'Estafa o fraude' },
    { value: 'illegal_item', label: 'Algo ilegal' },
    { value: 'sexual_content', label: 'Contenido sexual' },
    { value: 'violence', label: 'Violencia o amenazas' },
    { value: 'other', label: 'Otro motivo' },
];

async function startEdit(): Promise<void> {
    draft.value = props.comment.body;
    editing.value = true;
    await nextTick();
    editor.value?.focus();
    editor.value?.setSelectionRange(draft.value.length, draft.value.length);
}

async function saveEdit(): Promise<void> {
    const body = draft.value.trim();
    if (!body || saving.value) return;
    if (body === props.comment.body) {
        editing.value = false;
        return;
    }

    saving.value = true;
    try {
        const res = await api<{ comment: CommentItem }>(
            'PATCH',
            update(props.comment.id).url,
            { body },
        );
        emit('updated', { ...res.comment, replies: props.comment.replies });
        editing.value = false;
    } catch (error) {
        toast.error(
            error instanceof ApiError
                ? error.firstError()
                : 'No se pudo guardar.',
        );
    } finally {
        saving.value = false;
    }
}

async function confirmDelete(): Promise<void> {
    deleting.value = true;
    try {
        const res = await api<{ count: number }>(
            'DELETE',
            destroy(props.comment.id).url,
        );
        emit('deleted', { id: props.comment.id, count: res.count });
        toast.success('Comentario eliminado.');
    } catch (error) {
        toast.error(
            error instanceof ApiError ? error.message : 'No se pudo eliminar.',
        );
        deleting.value = false;
        confirmingDelete.value = false;
    }
}

async function sendReport(reason: string): Promise<void> {
    try {
        const res = await api<{ message: string }>(
            'POST',
            report(props.comment.id).url,
            { reason },
        );
        toast.success(res.message);
    } catch (error) {
        toast.error(
            error instanceof ApiError
                ? error.message
                : 'No se pudo enviar el reporte.',
        );
    }
}

function onEditKey(event: KeyboardEvent): void {
    if (event.key === 'Escape') editing.value = false;
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        void saveEdit();
    }
}
</script>

<template>
    <div
        class="group/comment flex gap-2.5"
        :class="{ 'opacity-50 transition-opacity': deleting }"
    >
        <component
            :is="comment.author.slug ? Link : 'span'"
            :href="
                comment.author.slug
                    ? businessShow(comment.author.slug).url
                    : undefined
            "
            class="flex-none"
        >
            <BusinessAvatar
                :src="comment.author.avatar"
                :name="comment.author.name"
                :size="isReply ? 'sm' : 'sm'"
            />
        </component>

        <div class="min-w-0 flex-1">
            <div v-if="!editing" class="flex items-start gap-1">
                <div
                    class="bg-muted inline-block max-w-full rounded-2xl px-3.5 py-2"
                >
                    <p
                        class="flex flex-wrap items-center gap-x-1.5 text-[13px] font-bold"
                    >
                        <component
                            :is="comment.author.slug ? Link : 'span'"
                            :href="
                                comment.author.slug
                                    ? businessShow(comment.author.slug).url
                                    : undefined
                            "
                            :class="
                                comment.author.slug ? 'hover:underline' : ''
                            "
                        >
                            {{ comment.author.name }}
                        </component>
                        <BadgeCheck
                            v-if="comment.author.isBusiness"
                            class="text-brand size-3.5"
                            aria-hidden="true"
                        />
                        <span
                            v-if="comment.author.isPostOwner"
                            class="bg-brand-soft text-brand rounded-full px-1.5 text-[10px]"
                            >Autor</span
                        >
                    </p>
                    <p
                        class="text-[15px] leading-snug break-words whitespace-pre-line"
                    >
                        {{ comment.body }}
                    </p>
                </div>

                <DropdownMenu
                    v-if="
                        comment.can.edit ||
                        comment.can.delete ||
                        comment.can.report
                    "
                >
                    <DropdownMenuTrigger as-child>
                        <button
                            type="button"
                            class="text-muted-foreground hover:bg-muted mt-1 grid size-8 flex-none place-items-center rounded-full opacity-100 transition-opacity md:opacity-0 md:group-hover/comment:opacity-100 md:focus-visible:opacity-100 md:data-[state=open]:opacity-100"
                            aria-label="Opciones del comentario"
                        >
                            <EllipsisVertical class="size-4" />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        align="end"
                        class="min-w-44 rounded-xl"
                    >
                        <DropdownMenuItem
                            v-if="comment.can.edit"
                            class="cursor-pointer gap-2"
                            @select="startEdit"
                        >
                            <Pencil class="size-4" /> Editar
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            v-if="comment.can.delete"
                            class="text-destructive cursor-pointer gap-2"
                            @select="confirmingDelete = true"
                        >
                            <Trash2 class="size-4" /> Eliminar
                        </DropdownMenuItem>
                        <template v-if="comment.can.report">
                            <DropdownMenuSeparator
                                v-if="comment.can.edit || comment.can.delete"
                            />
                            <DropdownMenuLabel
                                class="text-muted-foreground flex items-center gap-2 text-xs font-normal"
                            >
                                <Flag class="size-3.5" /> Reportar por…
                            </DropdownMenuLabel>
                            <DropdownMenuItem
                                v-for="r in reasons"
                                :key="r.value"
                                class="cursor-pointer pl-8"
                                @select="sendReport(r.value)"
                            >
                                {{ r.label }}
                            </DropdownMenuItem>
                        </template>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            <!-- Edición en el mismo lugar -->
            <div v-else class="bg-muted rounded-2xl p-2">
                <textarea
                    ref="editor"
                    v-model="draft"
                    rows="2"
                    maxlength="1000"
                    class="bg-background focus-visible:ring-ring/50 w-full resize-none rounded-xl px-3 py-2 text-[15px] outline-none focus-visible:ring-2"
                    aria-label="Editar comentario"
                    @keydown="onEditKey"
                />
                <div class="mt-1.5 flex items-center justify-end gap-2 text-xs">
                    <span class="text-muted-foreground mr-auto pl-1"
                        >Esc para cancelar</span
                    >
                    <button
                        type="button"
                        class="hover:bg-background rounded-full px-3 py-1.5 font-bold"
                        @click="editing = false"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        class="bg-primary text-primary-foreground inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 font-bold disabled:opacity-50"
                        :disabled="saving || !draft.trim()"
                        @click="saveEdit"
                    >
                        <Spinner v-if="saving" class="size-3" /> Guardar
                    </button>
                </div>
            </div>

            <!-- Confirmación de borrado, en línea -->
            <div
                v-if="confirmingDelete"
                class="border-destructive/30 bg-destructive/[0.06] mt-1.5 flex flex-wrap items-center gap-2 rounded-xl border px-3 py-2 text-sm"
            >
                <span class="mr-auto">
                    ¿Eliminar este comentario<template
                        v-if="comment.replies.length"
                    >
                        y sus {{ comment.replies.length }} respuestas</template
                    >?
                </span>
                <button
                    type="button"
                    class="hover:bg-background rounded-full px-3 py-1 font-bold"
                    :disabled="deleting"
                    @click="confirmingDelete = false"
                >
                    No
                </button>
                <button
                    type="button"
                    class="bg-destructive inline-flex items-center gap-1.5 rounded-full px-3 py-1 font-bold text-white"
                    :disabled="deleting"
                    @click="confirmDelete"
                >
                    <Spinner v-if="deleting" class="size-3" /> Sí, eliminar
                </button>
            </div>

            <p
                v-if="!editing"
                class="text-muted-foreground mt-1 flex items-center gap-3 pl-3 text-xs"
            >
                <span>{{ comment.ago }}</span>
                <button
                    v-if="canReply"
                    type="button"
                    class="hover:text-foreground font-bold"
                    @click="emit('reply', comment)"
                >
                    Responder
                </button>
                <span v-if="comment.edited">Editado</span>
            </p>

            <slot />
        </div>
    </div>
</template>
