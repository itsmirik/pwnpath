<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

type Category = 'web' | 'crypto' | 'forensics' | 'osint' | 'llm' | 'misc';
type Difficulty = 'easy' | 'medium' | 'hard';

type ChallengeFile = {
    id: number;
    filename: string;
    size_bytes: number;
    mime_type: string;
    download_url: string | null;
};

type SubmitStatus = 'solved' | 'already_solved' | 'wrong' | 'rate_limited';

type SubmitResult = {
    status: SubmitStatus;
    points?: number;
    streak_count?: number;
    error?: string;
};

type Props = {
    challenge: {
        slug: string;
        title: string;
        description_html: string;
        category: Category;
        difficulty: Difficulty;
        points: number;
        solve_count: number;
        flag_format: string;
        published_at: string | null;
        is_solved: boolean;
        hints: string[];
        hints_locked: boolean;
        files: ChallengeFile[];
        can_submit: boolean;
        submit_url: string;
    };
};

const props = defineProps<Props>();
const { t } = useI18n();

const flag = ref('');
const submitting = ref(false);
const submitError = ref<string | null>(null);
const submitSuccess = ref<string | null>(null);

function formatSize(bytes: number): string {
    if (bytes < 1024) {
        return t('challenges.show.files.size_bytes', { n: bytes });
    }

    if (bytes < 1024 * 1024) {
        return t('challenges.show.files.size_kb', {
            n: (bytes / 1024).toFixed(1),
        });
    }

    return t('challenges.show.files.size_mb', {
        n: (bytes / 1024 / 1024).toFixed(2),
    });
}

async function submitFlag(): Promise<void> {
    if (submitting.value || !flag.value.trim()) {
        return;
    }

    submitting.value = true;
    submitError.value = null;
    submitSuccess.value = null;

    try {
        const res = await fetch(props.challenge.submit_url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie.match(
                        /(?:^|;\s*)XSRF-TOKEN=([^;]+)/,
                    )?.[1] ?? '',
                ),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ flag: flag.value.trim() }),
        });

        const data = (await res
            .json()
            .catch(() => null)) as SubmitResult | null;

        if (res.status === 429 || data?.status === 'rate_limited') {
            submitError.value = t('challenges.show.submit.rate_limited');

            return;
        }

        if (!res.ok || data === null) {
            submitError.value = t('challenges.show.submit.error_generic');

            return;
        }

        if (data.status === 'wrong') {
            submitError.value = t('challenges.show.submit.wrong');

            return;
        }

        if (data.status === 'already_solved') {
            submitSuccess.value = t('challenges.show.submit.already_solved');

            return;
        }

        if (data.status === 'solved') {
            submitSuccess.value = t('challenges.show.submit.success', {
                points: data.points ?? props.challenge.points,
                streak: data.streak_count ?? 1,
            });
            flag.value = '';
            router.reload({ only: ['challenge'] });

            return;
        }

        submitError.value = t('challenges.show.submit.error_generic');
    } catch {
        submitError.value = t('challenges.show.submit.error_generic');
    } finally {
        submitting.value = false;
    }
}

const difficultyColor: Record<Difficulty, string> = {
    easy: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300',
    medium: 'border-amber-500/30 bg-amber-500/10 text-amber-300',
    hard: 'border-rose-500/30 bg-rose-500/10 text-rose-300',
};

const categoryColor: Record<Category, string> = {
    web: 'border-cyan-500/30 bg-cyan-500/10 text-cyan-300',
    crypto: 'border-indigo-500/30 bg-indigo-500/10 text-indigo-300',
    forensics: 'border-violet-500/30 bg-violet-500/10 text-violet-300',
    osint: 'border-lime-500/30 bg-lime-500/10 text-lime-300',
    llm: 'border-pink-500/30 bg-pink-500/10 text-pink-300',
    misc: 'border-slate-500/30 bg-slate-500/10 text-slate-300',
};

const hasHints = computed(() => props.challenge.hints.length > 0);
</script>

<template>
    <Head :title="challenge.title" />

    <article class="mx-auto max-w-4xl px-6 py-12">
        <Link
            href="/challenges"
            class="inline-flex items-center gap-1 text-sm text-slate-400 hover:text-white"
        >
            ← {{ t('challenges.show.back') }}
        </Link>

        <header class="mt-6 flex flex-col gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <span
                    class="rounded-full border px-2 py-0.5 text-xs font-medium"
                    :class="categoryColor[challenge.category]"
                >
                    {{ t(`challenges.browse.category.${challenge.category}`) }}
                </span>
                <span
                    class="rounded-full border px-2 py-0.5 text-xs font-medium"
                    :class="difficultyColor[challenge.difficulty]"
                >
                    {{
                        t(
                            `challenges.browse.difficulty.${challenge.difficulty}`,
                        )
                    }}
                </span>
                <span
                    class="rounded-full border border-slate-700 px-2 py-0.5 text-xs text-slate-300"
                >
                    {{
                        t('challenges.show.meta.points', {
                            n: challenge.points,
                        })
                    }}
                </span>
                <span
                    class="rounded-full border border-slate-700 px-2 py-0.5 text-xs text-slate-300"
                >
                    {{
                        t('challenges.show.meta.solves', {
                            n: challenge.solve_count,
                        })
                    }}
                </span>
            </div>
            <h1
                class="text-3xl font-bold tracking-tight text-white sm:text-4xl"
            >
                {{ challenge.title }}
            </h1>
        </header>

        <section
            class="prose mt-8 max-w-none prose-invert prose-headings:text-white prose-a:text-cyan-300 prose-code:text-cyan-200 prose-pre:border prose-pre:border-slate-800 prose-pre:bg-slate-900/70"
        >
            <div v-html="challenge.description_html" />
        </section>

        <section
            class="mt-10 rounded-lg border border-slate-800 bg-slate-900/40 p-6"
        >
            <h2 class="text-lg font-semibold text-white">
                {{ t('challenges.show.hints.heading') }}
            </h2>

            <div
                v-if="challenge.hints_locked"
                class="mt-3 text-sm text-slate-400"
            >
                <p class="font-medium text-slate-200">
                    {{ t('challenges.show.hints.locked_title') }}
                </p>
                <p class="mt-1">
                    {{ t('challenges.show.hints.locked_body') }}
                </p>
            </div>
            <div v-else-if="hasHints" class="mt-3 space-y-3">
                <details
                    v-for="(hint, i) in challenge.hints"
                    :key="i"
                    class="group rounded-md border border-slate-800 bg-slate-950/60 p-3"
                >
                    <summary
                        class="cursor-pointer text-sm font-medium text-slate-200 group-open:text-cyan-300"
                    >
                        {{ t('challenges.show.hints.heading') }} #{{ i + 1 }}
                    </summary>
                    <p class="mt-2 text-sm whitespace-pre-line text-slate-300">
                        {{ hint }}
                    </p>
                </details>
            </div>
            <p v-else class="mt-3 text-sm text-slate-500">
                {{ t('challenges.show.hints.empty') }}
            </p>
        </section>

        <section
            class="mt-6 rounded-lg border border-slate-800 bg-slate-900/40 p-6"
        >
            <h2 class="text-lg font-semibold text-white">
                {{ t('challenges.show.files.heading') }}
            </h2>

            <p
                v-if="!challenge.can_submit && challenge.files.length"
                class="mt-3 text-sm text-slate-400"
            >
                {{ t('challenges.show.files.locked') }}
            </p>

            <ul
                v-if="challenge.files.length"
                class="mt-3 divide-y divide-slate-800"
            >
                <li
                    v-for="file in challenge.files"
                    :key="file.id"
                    class="flex items-center justify-between gap-3 py-2 text-sm"
                >
                    <div class="min-w-0">
                        <p class="truncate font-medium text-slate-100">
                            {{ file.filename }}
                        </p>
                        <p class="text-xs text-slate-500">
                            {{ formatSize(file.size_bytes) }} ·
                            {{ file.mime_type }}
                        </p>
                    </div>
                    <a
                        v-if="file.download_url"
                        :href="file.download_url"
                        class="rounded-md border border-slate-700 px-3 py-1.5 text-xs font-medium text-slate-200 hover:border-cyan-400 hover:text-cyan-300"
                    >
                        ⤓
                    </a>
                    <span v-else class="text-xs text-slate-600">🔒</span>
                </li>
            </ul>
            <p v-else class="mt-3 text-sm text-slate-500">
                {{ t('challenges.show.files.empty') }}
            </p>
        </section>

        <section
            class="mt-6 rounded-lg border border-slate-800 bg-slate-900/40 p-6"
        >
            <h2 class="text-lg font-semibold text-white">
                {{ t('challenges.show.submit.heading') }}
            </h2>

            <template v-if="challenge.is_solved">
                <div
                    class="mt-3 rounded-md border border-emerald-500/40 bg-emerald-500/10 p-3 text-sm text-emerald-200"
                >
                    <p class="font-medium">
                        {{ t('challenges.show.submit.solved_title') }}
                    </p>
                    <p class="mt-1">
                        {{
                            t('challenges.show.submit.solved_body', {
                                points: challenge.points,
                            })
                        }}
                    </p>
                </div>
            </template>
            <template v-else-if="challenge.can_submit">
                <form
                    class="mt-3 flex flex-col gap-3 sm:flex-row"
                    @submit.prevent="submitFlag"
                >
                    <input
                        v-model="flag"
                        type="text"
                        :placeholder="
                            t('challenges.show.submit.placeholder', {
                                example: 'HTP{...}',
                            })
                        "
                        class="flex-1 rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:border-cyan-400 focus:outline-none"
                        autocomplete="off"
                        spellcheck="false"
                    />
                    <button
                        type="submit"
                        :disabled="submitting || !flag.trim()"
                        class="rounded-md bg-cyan-400 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-cyan-300 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {{
                            submitting
                                ? t('challenges.show.submit.submitting')
                                : t('challenges.show.submit.button')
                        }}
                    </button>
                </form>
                <p class="mt-2 text-xs text-slate-500">
                    {{
                        t('challenges.show.submit.help', {
                            format: challenge.flag_format,
                        })
                    }}
                </p>

                <p
                    v-if="submitSuccess"
                    class="mt-3 rounded-md border border-emerald-500/40 bg-emerald-500/10 p-3 text-sm text-emerald-200"
                >
                    {{ submitSuccess }}
                </p>
                <p
                    v-if="submitError"
                    class="mt-3 rounded-md border border-rose-500/40 bg-rose-500/10 p-3 text-sm text-rose-200"
                >
                    {{ submitError }}
                </p>
            </template>
            <div v-else class="mt-3 text-sm text-slate-400">
                <p class="font-medium text-slate-200">
                    {{ t('challenges.show.submit.locked_title') }}
                </p>
                <p class="mt-1">
                    {{ t('challenges.show.submit.locked_body') }}
                </p>
            </div>
        </section>
    </article>
</template>
