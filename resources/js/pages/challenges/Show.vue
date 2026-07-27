<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import CommentThread from '@/components/CommentThread.vue';
import { getInitials } from '@/composables/useInitials';

type Category = 'web' | 'crypto' | 'forensics' | 'osint' | 'llm' | 'misc';
type Difficulty = 'easy' | 'medium' | 'hard';

type ChallengeFile = {
    id: number;
    filename: string;
    size_bytes: number;
    mime_type: string;
    download_url: string | null;
};

type Author = {
    username: string | null;
    display_name: string | null;
    avatar_color: string | null;
    url: string | null;
};

type WriteupStatus = 'pending' | 'approved' | 'rejected';

type WriteupItem = {
    id: number;
    author: Author;
    content_html: string;
    upvote_count: number;
    has_upvoted: boolean;
    can_upvote: boolean;
    upvote_url: string;
    created_at: string | null;
};

type Writeups = {
    items: WriteupItem[];
    can_write: boolean;
    create_url: string;
    mine: {
        status: WriteupStatus;
        moderation_note: string | null;
        updated_at: string | null;
    } | null;
};

type CommentItem = {
    id: number;
    author: Author;
    content: string;
    created_at: string | null;
    is_own: boolean;
    can_report: boolean;
    has_reported: boolean;
    report_url: string;
    replies?: CommentItem[];
};

type Comments = {
    locked: boolean;
    can_post: boolean;
    post_url: string;
    items: CommentItem[];
};

type SubmitStatus = 'solved' | 'already_solved' | 'wrong' | 'rate_limited';

type AwardedBadge = { slug: string; name: string; icon: string };

type SubmitResult = {
    status: SubmitStatus;
    points?: number;
    streak_count?: number;
    badges?: AwardedBadge[];
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
    writeups: Writeups;
    comments: Comments;
};

const props = defineProps<Props>();
const { t } = useI18n();

function toggleUpvote(writeup: WriteupItem): void {
    const options = { preserveScroll: true, preserveState: false };

    if (writeup.has_upvoted) {
        router.delete(writeup.upvote_url, options);
    } else {
        router.post(writeup.upvote_url, {}, options);
    }
}

const flag = ref('');
const submitting = ref(false);
const submitError = ref<string | null>(null);
const submitSuccess = ref<string | null>(null);
const awardedBadges = ref<AwardedBadge[]>([]);

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
            awardedBadges.value = data.badges ?? [];
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
    easy: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    medium: 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300',
    hard: 'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-300',
};

const categoryColor: Record<Category, string> = {
    web: 'border-cyan-500/30 bg-cyan-500/10 text-cyan-700 dark:text-cyan-300',
    crypto: 'border-indigo-500/30 bg-indigo-500/10 text-indigo-700 dark:text-indigo-300',
    forensics:
        'border-violet-500/30 bg-violet-500/10 text-violet-700 dark:text-violet-300',
    osint: 'border-lime-500/30 bg-lime-500/10 text-lime-700 dark:text-lime-300',
    llm: 'border-pink-500/30 bg-pink-500/10 text-pink-700 dark:text-pink-300',
    misc: 'border-slate-500/30 bg-slate-500/10 text-muted-foreground',
};

const hasHints = computed(() => props.challenge.hints.length > 0);
</script>

<template>
    <Head :title="challenge.title" />

    <article class="w-full px-6 py-8">
        <Link
            href="/challenges"
            class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
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
                    class="rounded-full border border-border px-2 py-0.5 text-xs text-muted-foreground"
                >
                    {{
                        t('challenges.show.meta.points', {
                            n: challenge.points,
                        })
                    }}
                </span>
                <span
                    class="rounded-full border border-border px-2 py-0.5 text-xs text-muted-foreground"
                >
                    {{
                        t('challenges.show.meta.solves', {
                            n: challenge.solve_count,
                        })
                    }}
                </span>
            </div>
            <h1
                class="text-3xl font-bold tracking-tight text-foreground sm:text-4xl"
            >
                {{ challenge.title }}
            </h1>
        </header>

        <section
            class="prose mt-8 max-w-3xl dark:prose-invert prose-headings:text-foreground prose-a:text-cyan-600 dark:prose-a:text-cyan-300 prose-code:text-cyan-700 dark:prose-code:text-cyan-200 prose-pre:border prose-pre:border-border prose-pre:bg-accent"
        >
            <div v-html="challenge.description_html" />
        </section>

        <section class="mt-10 rounded-lg border border-border bg-card p-6">
            <h2 class="text-lg font-semibold text-foreground">
                {{ t('challenges.show.hints.heading') }}
            </h2>

            <div
                v-if="challenge.hints_locked"
                class="mt-3 text-sm text-muted-foreground"
            >
                <p class="font-medium text-foreground">
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
                    class="group rounded-md border border-border bg-muted/30 p-3"
                >
                    <summary
                        class="cursor-pointer text-sm font-medium text-foreground group-open:text-cyan-600 dark:group-open:text-cyan-300"
                    >
                        {{ t('challenges.show.hints.heading') }} #{{ i + 1 }}
                    </summary>
                    <p
                        class="mt-2 text-sm whitespace-pre-line text-muted-foreground"
                    >
                        {{ hint }}
                    </p>
                </details>
            </div>
            <p v-else class="mt-3 text-sm text-muted-foreground">
                {{ t('challenges.show.hints.empty') }}
            </p>
        </section>

        <section class="mt-6 rounded-lg border border-border bg-card p-6">
            <h2 class="text-lg font-semibold text-foreground">
                {{ t('challenges.show.files.heading') }}
            </h2>

            <p
                v-if="!challenge.can_submit && challenge.files.length"
                class="mt-3 text-sm text-muted-foreground"
            >
                {{ t('challenges.show.files.locked') }}
            </p>

            <ul
                v-if="challenge.files.length"
                class="mt-3 divide-y divide-border"
            >
                <li
                    v-for="file in challenge.files"
                    :key="file.id"
                    class="flex items-center justify-between gap-3 py-2 text-sm"
                >
                    <div class="min-w-0">
                        <p class="truncate font-medium text-foreground">
                            {{ file.filename }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ formatSize(file.size_bytes) }} ·
                            {{ file.mime_type }}
                        </p>
                    </div>
                    <a
                        v-if="file.download_url"
                        :href="file.download_url"
                        class="rounded-md border border-border px-3 py-1.5 text-xs font-medium text-foreground hover:border-cyan-400 hover:text-cyan-600 dark:hover:text-cyan-300"
                    >
                        ⤓
                    </a>
                    <span v-else class="text-xs text-muted-foreground">🔒</span>
                </li>
            </ul>
            <p v-else class="mt-3 text-sm text-muted-foreground">
                {{ t('challenges.show.files.empty') }}
            </p>
        </section>

        <section class="mt-6 rounded-lg border border-border bg-card p-6">
            <h2 class="text-lg font-semibold text-foreground">
                {{ t('challenges.show.submit.heading') }}
            </h2>

            <template v-if="challenge.is_solved">
                <div
                    class="mt-3 rounded-md border border-emerald-500/40 bg-emerald-500/10 p-3 text-sm text-emerald-800 dark:text-emerald-200"
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
                        class="flex-1 rounded-md border border-border bg-background px-3 py-2 text-sm text-foreground focus:border-cyan-400 focus:outline-none"
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
                <p class="mt-2 text-xs text-muted-foreground">
                    {{
                        t('challenges.show.submit.help', {
                            format: challenge.flag_format,
                        })
                    }}
                </p>

                <p
                    v-if="submitSuccess"
                    class="mt-3 rounded-md border border-emerald-500/40 bg-emerald-500/10 p-3 text-sm text-emerald-800 dark:text-emerald-200"
                >
                    {{ submitSuccess }}
                </p>
                <div
                    v-if="awardedBadges.length"
                    class="mt-3 rounded-md border border-cyan-400/40 bg-cyan-400/10 p-3 text-sm text-cyan-800 dark:text-cyan-100"
                >
                    <p class="font-medium">
                        {{ t('challenges.show.submit.badges_earned') }}
                    </p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <span
                            v-for="badge in awardedBadges"
                            :key="badge.slug"
                            class="inline-flex items-center gap-1 rounded-full border border-cyan-400/30 bg-cyan-400/10 px-2 py-0.5 text-xs"
                        >
                            <span>{{ badge.icon }}</span
                            >{{ badge.name }}
                        </span>
                    </div>
                </div>
                <p
                    v-if="submitError"
                    class="mt-3 rounded-md border border-rose-500/40 bg-rose-500/10 p-3 text-sm text-rose-800 dark:text-rose-200"
                >
                    {{ submitError }}
                </p>
            </template>
            <div v-else class="mt-3 text-sm text-muted-foreground">
                <p class="font-medium text-foreground">
                    {{ t('challenges.show.submit.locked_title') }}
                </p>
                <p class="mt-1">
                    {{ t('challenges.show.submit.locked_body') }}
                </p>
            </div>
        </section>

        <!-- Writeups -->
        <section class="mt-6 rounded-lg border border-border bg-card p-6">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-lg font-semibold text-foreground">
                    {{ t('challenges.show.writeups.heading') }}
                </h2>
                <Link
                    v-if="writeups.can_write"
                    :href="writeups.create_url"
                    class="rounded-md border border-cyan-400/40 bg-cyan-400/10 px-3 py-1.5 text-sm font-medium text-cyan-700 hover:bg-cyan-400/20 dark:text-cyan-300"
                >
                    {{
                        writeups.mine
                            ? t('challenges.show.writeups.edit')
                            : t('challenges.show.writeups.write')
                    }}
                </Link>
            </div>

            <p v-if="writeups.mine" class="mt-2 text-xs text-muted-foreground">
                {{
                    t('challenges.show.writeups.mine_status', {
                        status: t(`writeups.status.${writeups.mine.status}`),
                    })
                }}
            </p>

            <div v-if="writeups.items.length" class="mt-4 space-y-4">
                <article
                    v-for="w in writeups.items"
                    :key="w.id"
                    class="rounded-md border border-border bg-background/40 p-4"
                >
                    <header class="flex items-center justify-between gap-3">
                        <component
                            :is="w.author.url ? 'a' : 'div'"
                            :href="w.author.url ?? undefined"
                            class="flex items-center gap-2"
                        >
                            <span
                                class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-semibold text-slate-950"
                                :style="{
                                    backgroundColor:
                                        w.author.avatar_color ?? '#64748b',
                                }"
                            >
                                {{ getInitials(w.author.display_name ?? '?') }}
                            </span>
                            <span class="text-sm font-medium text-foreground">
                                {{ w.author.display_name ?? '—' }}
                            </span>
                        </component>
                        <button
                            type="button"
                            :disabled="!w.can_upvote && !w.has_upvoted"
                            class="inline-flex items-center gap-1 rounded-full border px-3 py-1 text-xs font-medium disabled:cursor-not-allowed disabled:opacity-60"
                            :class="
                                w.has_upvoted
                                    ? 'border-cyan-400/50 bg-cyan-400/15 text-cyan-700 dark:text-cyan-300'
                                    : 'border-border text-muted-foreground hover:border-cyan-400 hover:text-cyan-600 dark:hover:text-cyan-300'
                            "
                            :title="t('challenges.show.writeups.upvote')"
                            @click="toggleUpvote(w)"
                        >
                            ▲ {{ w.upvote_count }}
                        </button>
                    </header>
                    <div
                        class="prose prose-sm mt-3 max-w-none dark:prose-invert prose-headings:text-foreground prose-a:text-cyan-600 dark:prose-a:text-cyan-300 prose-code:text-cyan-700 dark:prose-code:text-cyan-200 prose-pre:border prose-pre:border-border prose-pre:bg-accent"
                        v-html="w.content_html"
                    />
                </article>
            </div>
            <p v-else class="mt-3 text-sm text-muted-foreground">
                {{
                    writeups.can_write
                        ? t('challenges.show.writeups.empty_solver')
                        : t('challenges.show.writeups.empty')
                }}
            </p>
        </section>

        <!-- Discussion -->
        <CommentThread :comments="comments" />
    </article>
</template>
