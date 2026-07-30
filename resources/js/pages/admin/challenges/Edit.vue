<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Archive,
    ArrowLeft,
    ExternalLink,
    Rocket,
    Save,
    Send,
    Trash2,
    Undo2,
    UploadCloud,
} from '@lucide/vue';
import { computed, ref } from 'vue';

type Translation = {
    title: string;
    description: string;
    hint_1: string | null;
    hint_2: string | null;
};

type ChallengeFile = {
    id: number;
    filename: string;
    size_bytes: number;
    mime_type: string;
    delete_url: string;
};

type Challenge = {
    id: number;
    slug: string;
    category: string;
    difficulty: string;
    points: number;
    status: string;
    flag_type: string;
    static_flag: string | null;
    flag_format: string;
    author: string | null;
    solve_count: number;
    published_at: string | null;
    translations: Record<string, Translation>;
    files: ChallengeFile[];
    public_url: string | null;
    upload_url: string;
    actions: {
        submit_review: string | null;
        publish: string | null;
        unpublish: string | null;
        archive: string | null;
    };
};

const props = defineProps<{
    challenge: Challenge | null;
    store_url?: string;
    update_url?: string;
    options: {
        categories: string[];
        difficulties: string[];
        difficulty_points: Record<string, number>;
        flag_types: string[];
        locales: string[];
    };
}>();

const page = usePage();
const isEdit = computed(() => props.challenge !== null);

const statusError = computed(
    () => (page.props.errors as Record<string, string> | undefined)?.status,
);

function buildTranslations(): Record<string, Translation> {
    const out: Record<string, Translation> = {};

    for (const locale of props.options.locales) {
        const t = props.challenge?.translations?.[locale];
        out[locale] = {
            title: t?.title ?? '',
            description: t?.description ?? '',
            hint_1: t?.hint_1 ?? '',
            hint_2: t?.hint_2 ?? '',
        };
    }

    return out;
}

const form = useForm({
    slug: props.challenge?.slug ?? '',
    category: props.challenge?.category ?? props.options.categories[0],
    difficulty: props.challenge?.difficulty ?? props.options.difficulties[0],
    flag_type: props.challenge?.flag_type ?? 'static',
    static_flag: props.challenge?.static_flag ?? '',
    flag_format: props.challenge?.flag_format ?? '',
    translations: buildTranslations(),
});

const activeLocale = ref(
    props.options.locales.includes('ru') ? 'ru' : props.options.locales[0],
);

const derivedPoints = computed(
    () => props.options.difficulty_points[form.difficulty] ?? 0,
);

function submit(): void {
    if (isEdit.value && props.update_url) {
        form.put(props.update_url, { preserveScroll: true });
    } else if (props.store_url) {
        form.post(props.store_url);
    }
}

function transition(url: string | null): void {
    if (!url) {
        return;
    }

    router.post(url, {}, { preserveScroll: true });
}

// --- File upload (drag-drop) ---
const dragging = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

function onDrop(event: DragEvent): void {
    dragging.value = false;

    if (event.dataTransfer?.files?.length) {
        uploadFiles(event.dataTransfer.files);
    }
}

function onPick(event: Event): void {
    const target = event.target as HTMLInputElement;

    if (target.files?.length) {
        uploadFiles(target.files);
    }

    target.value = '';
}

function uploadFiles(files: FileList): void {
    if (!props.challenge) {
        return;
    }

    for (const file of Array.from(files)) {
        router.post(
            props.challenge.upload_url,
            { file },
            { forceFormData: true, preserveScroll: true },
        );
    }
}

function deleteFile(file: ChallengeFile): void {
    router.delete(file.delete_url, { preserveScroll: true });
}

function humanBytes(bytes: number): string {
    if (bytes >= 1024 * 1024) {
        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    }

    if (bytes >= 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${bytes} B`;
}

const statusStyle: Record<string, string> = {
    draft: 'border-slate-500/30 bg-slate-500/10 text-muted-foreground',
    review: 'border-amber-500/30 bg-amber-500/10 text-amber-600 dark:text-amber-300',
    published:
        'border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-300',
    archived: 'border-zinc-500/30 bg-zinc-500/10 text-muted-foreground',
};
</script>

<template>
    <Head
        :title="isEdit ? `Edit ${form.slug} · Admin` : 'New challenge · Admin'"
    />

    <div class="mx-auto w-full max-w-5xl px-6 py-8">
        <div class="mb-6 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <Link
                    href="/admin/challenges"
                    class="rounded-md p-2 text-muted-foreground transition hover:bg-accent hover:text-foreground"
                >
                    <ArrowLeft class="h-4 w-4" />
                </Link>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">
                        {{ isEdit ? 'Edit challenge' : 'New challenge' }}
                    </h1>
                    <div
                        v-if="challenge"
                        class="mt-1 flex items-center gap-2 text-sm text-muted-foreground"
                    >
                        <span
                            class="rounded-full border px-2 py-0.5 text-xs font-medium capitalize"
                            :class="statusStyle[challenge.status]"
                        >
                            {{ challenge.status }}
                        </span>
                        <span>{{ challenge.solve_count }} solves</span>
                        <a
                            v-if="challenge.public_url"
                            :href="challenge.public_url"
                            target="_blank"
                            class="inline-flex items-center gap-1 hover:text-foreground"
                        >
                            <ExternalLink class="h-3.5 w-3.5" /> public
                        </a>
                    </div>
                </div>
            </div>

            <div
                v-if="challenge"
                class="flex flex-wrap items-center justify-end gap-2"
            >
                <button
                    v-if="challenge.actions.submit_review"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-md border border-border px-3 py-2 text-sm transition hover:border-amber-400/50 hover:text-amber-500"
                    @click="transition(challenge.actions.submit_review)"
                >
                    <Send class="h-4 w-4" /> Submit for review
                </button>
                <button
                    v-if="challenge.actions.publish"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-md border border-border px-3 py-2 text-sm transition hover:border-emerald-400/50 hover:text-emerald-500"
                    @click="transition(challenge.actions.publish)"
                >
                    <Rocket class="h-4 w-4" /> Publish
                </button>
                <button
                    v-if="challenge.actions.unpublish"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-md border border-border px-3 py-2 text-sm transition hover:border-foreground/30"
                    @click="transition(challenge.actions.unpublish)"
                >
                    <Undo2 class="h-4 w-4" /> Unpublish
                </button>
                <button
                    v-if="challenge.actions.archive"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-md border border-border px-3 py-2 text-sm transition hover:border-rose-400/50 hover:text-rose-500"
                    @click="transition(challenge.actions.archive)"
                >
                    <Archive class="h-4 w-4" /> Archive
                </button>
            </div>
        </div>

        <div
            v-if="statusError"
            class="mb-6 rounded-md border border-rose-500/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-600 dark:text-rose-300"
        >
            {{ statusError }}
        </div>

        <form class="space-y-8" @submit.prevent="submit">
            <!-- Metadata -->
            <section class="rounded-lg border border-border bg-card p-5">
                <h2
                    class="mb-4 text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Metadata
                </h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="flex flex-col gap-1 text-sm">
                        <span class="text-muted-foreground">Slug</span>
                        <input
                            v-model="form.slug"
                            type="text"
                            class="rounded-md border border-border bg-background px-3 py-2 focus:border-cyan-400 focus:outline-none"
                        />
                        <span
                            v-if="form.errors.slug"
                            class="text-xs text-rose-500"
                        >
                            {{ form.errors.slug }}
                        </span>
                    </label>

                    <div class="grid grid-cols-2 gap-4">
                        <label class="flex flex-col gap-1 text-sm">
                            <span class="text-muted-foreground">Category</span>
                            <select
                                v-model="form.category"
                                class="rounded-md border border-border bg-background px-3 py-2 focus:border-cyan-400 focus:outline-none"
                            >
                                <option
                                    v-for="c in options.categories"
                                    :key="c"
                                    :value="c"
                                >
                                    {{ c }}
                                </option>
                            </select>
                        </label>
                        <label class="flex flex-col gap-1 text-sm">
                            <span class="text-muted-foreground"
                                >Difficulty</span
                            >
                            <select
                                v-model="form.difficulty"
                                class="rounded-md border border-border bg-background px-3 py-2 focus:border-cyan-400 focus:outline-none"
                            >
                                <option
                                    v-for="d in options.difficulties"
                                    :key="d"
                                    :value="d"
                                >
                                    {{ d }}
                                </option>
                            </select>
                        </label>
                    </div>

                    <div
                        class="flex items-center gap-2 text-sm text-muted-foreground"
                    >
                        Points (auto):
                        <span
                            class="font-semibold text-foreground tabular-nums"
                        >
                            {{ derivedPoints }}
                        </span>
                    </div>

                    <label class="flex flex-col gap-1 text-sm">
                        <span class="text-muted-foreground">Flag type</span>
                        <select
                            v-model="form.flag_type"
                            class="rounded-md border border-border bg-background px-3 py-2 focus:border-cyan-400 focus:outline-none"
                        >
                            <option
                                v-for="ft in options.flag_types"
                                :key="ft"
                                :value="ft"
                            >
                                {{ ft }}
                            </option>
                        </select>
                    </label>

                    <label
                        v-if="form.flag_type === 'static'"
                        class="flex flex-col gap-1 text-sm"
                    >
                        <span class="text-muted-foreground">Static flag</span>
                        <input
                            v-model="form.static_flag"
                            type="text"
                            placeholder="HTP{...}"
                            class="rounded-md border border-border bg-background px-3 py-2 font-mono focus:border-cyan-400 focus:outline-none"
                        />
                        <span
                            v-if="form.errors.static_flag"
                            class="text-xs text-rose-500"
                        >
                            {{ form.errors.static_flag }}
                        </span>
                    </label>
                    <div
                        v-else
                        class="rounded-md border border-dashed border-border px-3 py-2 text-xs text-muted-foreground"
                    >
                        Dynamic: a per-user flag is generated on submit (HMAC of
                        user + challenge).
                    </div>

                    <label class="flex flex-col gap-1 text-sm sm:col-span-2">
                        <span class="text-muted-foreground">
                            Flag format (regex, for client-side hint)
                        </span>
                        <input
                            v-model="form.flag_format"
                            type="text"
                            placeholder="HTP\{[a-zA-Z0-9_]+\}"
                            class="rounded-md border border-border bg-background px-3 py-2 font-mono focus:border-cyan-400 focus:outline-none"
                        />
                        <span
                            v-if="form.errors.flag_format"
                            class="text-xs text-rose-500"
                        >
                            {{ form.errors.flag_format }}
                        </span>
                    </label>
                </div>
            </section>

            <!-- Translations (edit mode only) -->
            <section
                v-if="isEdit"
                class="rounded-lg border border-border bg-card p-5"
            >
                <div class="mb-4 flex items-center justify-between">
                    <h2
                        class="text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Content
                    </h2>
                    <div
                        class="flex gap-1 rounded-md border border-border p-0.5"
                    >
                        <button
                            v-for="locale in options.locales"
                            :key="locale"
                            type="button"
                            class="rounded px-3 py-1 text-xs font-medium uppercase transition"
                            :class="
                                activeLocale === locale
                                    ? 'bg-accent text-foreground'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            @click="activeLocale = locale"
                        >
                            {{ locale }}
                        </button>
                    </div>
                </div>

                <div class="space-y-4">
                    <label class="flex flex-col gap-1 text-sm">
                        <span class="text-muted-foreground"
                            >Title ({{ activeLocale }})</span
                        >
                        <input
                            v-model="form.translations[activeLocale].title"
                            type="text"
                            class="rounded-md border border-border bg-background px-3 py-2 focus:border-cyan-400 focus:outline-none"
                        />
                    </label>
                    <label class="flex flex-col gap-1 text-sm">
                        <span class="text-muted-foreground">
                            Description ({{ activeLocale }}) — Markdown
                        </span>
                        <textarea
                            v-model="
                                form.translations[activeLocale].description
                            "
                            rows="10"
                            class="rounded-md border border-border bg-background px-3 py-2 font-mono text-sm focus:border-cyan-400 focus:outline-none"
                        />
                    </label>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="flex flex-col gap-1 text-sm">
                            <span class="text-muted-foreground"
                                >Hint 1 (soft)</span
                            >
                            <textarea
                                v-model="form.translations[activeLocale].hint_1"
                                rows="3"
                                class="rounded-md border border-border bg-background px-3 py-2 text-sm focus:border-cyan-400 focus:outline-none"
                            />
                        </label>
                        <label class="flex flex-col gap-1 text-sm">
                            <span class="text-muted-foreground"
                                >Hint 2 (strong)</span
                            >
                            <textarea
                                v-model="form.translations[activeLocale].hint_2"
                                rows="3"
                                class="rounded-md border border-border bg-background px-3 py-2 text-sm focus:border-cyan-400 focus:outline-none"
                            />
                        </label>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        A locale needs both a title and description to appear to
                        users. RU is the mandatory base locale.
                    </p>
                </div>
            </section>

            <!-- Files (edit mode only) -->
            <section
                v-if="isEdit && challenge"
                class="rounded-lg border border-border bg-card p-5"
            >
                <h2
                    class="mb-4 text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Files
                </h2>

                <div
                    class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed px-6 py-8 text-center text-sm transition"
                    :class="
                        dragging
                            ? 'border-cyan-400 bg-cyan-400/5 text-foreground'
                            : 'border-border text-muted-foreground hover:border-cyan-400/40'
                    "
                    @dragover.prevent="dragging = true"
                    @dragleave.prevent="dragging = false"
                    @drop.prevent="onDrop"
                    @click="fileInput?.click()"
                >
                    <UploadCloud class="h-6 w-6" />
                    <span>Drag &amp; drop files here, or click to browse</span>
                    <span class="text-xs">Up to 10 MB each</span>
                    <input
                        ref="fileInput"
                        type="file"
                        multiple
                        class="hidden"
                        @change="onPick"
                    />
                </div>

                <ul
                    v-if="challenge.files.length"
                    class="mt-4 divide-y divide-border"
                >
                    <li
                        v-for="file in challenge.files"
                        :key="file.id"
                        class="flex items-center justify-between gap-4 py-2 text-sm"
                    >
                        <div class="min-w-0">
                            <div class="truncate font-medium">
                                {{ file.filename }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ humanBytes(file.size_bytes) }} ·
                                {{ file.mime_type }}
                            </div>
                        </div>
                        <button
                            type="button"
                            title="Remove"
                            class="rounded-md p-2 text-muted-foreground transition hover:bg-rose-500/10 hover:text-rose-500"
                            @click="deleteFile(file)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </li>
                </ul>
            </section>

            <div class="flex items-center justify-end gap-3">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex items-center gap-2 rounded-md bg-cyan-500 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-cyan-600 disabled:opacity-60"
                >
                    <Save class="h-4 w-4" />
                    {{ isEdit ? 'Save changes' : 'Create draft' }}
                </button>
            </div>
        </form>
    </div>
</template>
