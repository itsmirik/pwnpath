<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import MarkdownEditor from '@/components/MarkdownEditor.vue';

type WriteupStatus = 'pending' | 'approved' | 'rejected';

type Props = {
    challenge: { slug: string; title: string; url: string };
    existing: {
        content: string;
        locale: string;
        status: WriteupStatus;
        moderation_note: string | null;
    } | null;
    store_url: string;
    image_upload_url: string;
    locales: string[];
    default_locale: string;
    max_content_chars: number;
};

const props = defineProps<Props>();
const { t } = useI18n();

const form = useForm({
    content: props.existing?.content ?? '',
    locale: props.default_locale,
});

const isEditing = computed(() => props.existing !== null);
const tooLong = computed(() => form.content.length > props.max_content_chars);
const canSubmit = computed(
    () => form.content.trim().length > 0 && !tooLong.value && !form.processing,
);

function submit(): void {
    if (!canSubmit.value) {
        return;
    }

    form.post(props.store_url, { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('writeups.create.title', { challenge: challenge.title })" />

    <div class="mx-auto w-full max-w-3xl px-6 py-8">
        <Link
            :href="challenge.url"
            class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
        >
            ← {{ t('writeups.create.back', { challenge: challenge.title }) }}
        </Link>

        <header class="mt-6">
            <h1 class="text-2xl font-bold tracking-tight text-foreground">
                {{
                    isEditing
                        ? t('writeups.create.heading_edit')
                        : t('writeups.create.heading_new')
                }}
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                {{ t('writeups.create.subtitle') }}
            </p>
        </header>

        <!-- Moderation state of an existing writeup -->
        <div
            v-if="existing"
            class="mt-4 rounded-md border p-3 text-sm"
            :class="{
                'border-amber-500/40 bg-amber-500/10 text-amber-800 dark:text-amber-200':
                    existing.status === 'pending',
                'border-emerald-500/40 bg-emerald-500/10 text-emerald-800 dark:text-emerald-200':
                    existing.status === 'approved',
                'border-rose-500/40 bg-rose-500/10 text-rose-800 dark:text-rose-200':
                    existing.status === 'rejected',
            }"
        >
            <p class="font-medium">
                {{ t(`writeups.status.${existing.status}`) }}
            </p>
            <p v-if="existing.status === 'approved'" class="mt-1">
                {{ t('writeups.create.requeue_notice') }}
            </p>
            <p
                v-if="
                    existing.status === 'rejected' && existing.moderation_note
                "
                class="mt-1"
            >
                {{ t('writeups.create.mod_note') }}:
                {{ existing.moderation_note }}
            </p>
        </div>

        <form class="mt-6 flex flex-col gap-4" @submit.prevent="submit">
            <div>
                <label
                    class="mb-1 block text-sm font-medium text-foreground"
                    for="writeup-locale"
                >
                    {{ t('writeups.create.language') }}
                </label>
                <select
                    id="writeup-locale"
                    v-model="form.locale"
                    class="rounded-md border border-border bg-background px-3 py-2 text-sm text-foreground focus:border-cyan-400 focus:outline-none"
                >
                    <option v-for="loc in locales" :key="loc" :value="loc">
                        {{ t(`writeups.locale.${loc}`) }}
                    </option>
                </select>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-foreground">
                    {{ t('writeups.create.body') }}
                </label>
                <MarkdownEditor
                    v-model="form.content"
                    :upload-url="image_upload_url"
                    :placeholder="t('writeups.create.placeholder')"
                />
                <div class="mt-1 flex items-center justify-between text-xs">
                    <span
                        :class="
                            tooLong
                                ? 'text-rose-600 dark:text-rose-400'
                                : 'text-muted-foreground'
                        "
                    >
                        {{ form.content.length }} / {{ max_content_chars }}
                    </span>
                    <span class="text-muted-foreground">
                        {{ t('writeups.create.image_hint') }}
                    </span>
                </div>
                <p
                    v-if="form.errors.content"
                    class="mt-2 rounded-md border border-rose-500/40 bg-rose-500/10 p-2 text-sm text-rose-800 dark:text-rose-200"
                >
                    {{ form.errors.content }}
                </p>
            </div>

            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    :disabled="!canSubmit"
                    class="rounded-md bg-cyan-400 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-cyan-300 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {{
                        form.processing
                            ? t('writeups.create.submitting')
                            : t('writeups.create.submit')
                    }}
                </button>
                <Link
                    :href="challenge.url"
                    class="text-sm text-muted-foreground hover:text-foreground"
                >
                    {{ t('writeups.create.cancel') }}
                </Link>
            </div>
        </form>
    </div>
</template>
