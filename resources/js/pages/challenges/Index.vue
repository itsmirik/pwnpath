<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';
import { useI18n } from 'vue-i18n';

type Category = 'web' | 'crypto' | 'forensics' | 'osint' | 'llm' | 'misc';
type Difficulty = 'easy' | 'medium' | 'hard';
type SolvedFilter = 'all' | 'solved' | 'unsolved';
type SortOption =
    'newest' | 'oldest' | 'points_desc' | 'points_asc' | 'solves_desc';

type ChallengeCard = {
    slug: string;
    title: string;
    category: Category;
    difficulty: Difficulty;
    points: number;
    solve_count: number;
    url: string;
};

type Props = {
    challenges: {
        data: ChallengeCard[];
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            per_page: number;
        };
        links: { prev: string | null; next: string | null };
    };
    filters: {
        category: Category | null;
        difficulty: Difficulty | null;
        solved: SolvedFilter;
        sort: SortOption;
    };
    options: {
        categories: Category[];
        difficulties: Difficulty[];
        solved: SolvedFilter[];
        sort: SortOption[];
    };
};

const props = defineProps<Props>();
const { t } = useI18n();

const state = reactive({
    category: props.filters.category ?? '',
    difficulty: props.filters.difficulty ?? '',
    solved: props.filters.solved,
    sort: props.filters.sort,
});

watch(
    state,
    (next) => {
        router.get(
            '/challenges',
            {
                category: next.category || undefined,
                difficulty: next.difficulty || undefined,
                solved: next.solved === 'all' ? undefined : next.solved,
                sort: next.sort === 'newest' ? undefined : next.sort,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    },
    { deep: true },
);

function resetFilters(): void {
    state.category = '';
    state.difficulty = '';
    state.solved = 'all';
    state.sort = 'newest';
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

const isEmpty = computed(() => props.challenges.data.length === 0);
</script>

<template>
    <Head :title="t('challenges.browse.title')" />

    <div class="mx-auto max-w-6xl px-6 py-12">
        <header class="mb-8">
            <h1
                class="text-3xl font-bold tracking-tight text-white sm:text-4xl"
            >
                {{ t('challenges.browse.title') }}
            </h1>
            <p class="mt-2 max-w-2xl text-slate-400">
                {{ t('challenges.browse.subtitle') }}
            </p>
        </header>

        <div
            class="mb-8 grid gap-4 rounded-lg border border-slate-800 bg-slate-900/40 p-4 sm:grid-cols-2 lg:grid-cols-5"
        >
            <label
                class="flex flex-col gap-1 text-xs tracking-wider text-slate-500 uppercase"
            >
                {{ t('challenges.browse.filters.category') }}
                <select
                    v-model="state.category"
                    class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:border-cyan-400 focus:outline-none"
                >
                    <option value="">
                        {{ t('challenges.browse.filters.any') }}
                    </option>
                    <option
                        v-for="cat in options.categories"
                        :key="cat"
                        :value="cat"
                    >
                        {{ t(`challenges.browse.category.${cat}`) }}
                    </option>
                </select>
            </label>

            <label
                class="flex flex-col gap-1 text-xs tracking-wider text-slate-500 uppercase"
            >
                {{ t('challenges.browse.filters.difficulty') }}
                <select
                    v-model="state.difficulty"
                    class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:border-cyan-400 focus:outline-none"
                >
                    <option value="">
                        {{ t('challenges.browse.filters.any') }}
                    </option>
                    <option
                        v-for="diff in options.difficulties"
                        :key="diff"
                        :value="diff"
                    >
                        {{ t(`challenges.browse.difficulty.${diff}`) }}
                    </option>
                </select>
            </label>

            <label
                class="flex flex-col gap-1 text-xs tracking-wider text-slate-500 uppercase"
            >
                {{ t('challenges.browse.filters.solved') }}
                <select
                    v-model="state.solved"
                    class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:border-cyan-400 focus:outline-none"
                >
                    <option
                        v-for="opt in options.solved"
                        :key="opt"
                        :value="opt"
                    >
                        {{ t(`challenges.browse.solved.${opt}`) }}
                    </option>
                </select>
            </label>

            <label
                class="flex flex-col gap-1 text-xs tracking-wider text-slate-500 uppercase"
            >
                {{ t('challenges.browse.filters.sort') }}
                <select
                    v-model="state.sort"
                    class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:border-cyan-400 focus:outline-none"
                >
                    <option v-for="opt in options.sort" :key="opt" :value="opt">
                        {{ t(`challenges.browse.sort.${opt}`) }}
                    </option>
                </select>
            </label>

            <div class="flex items-end">
                <button
                    type="button"
                    class="w-full rounded-md border border-slate-700 px-3 py-2 text-sm text-slate-200 hover:border-slate-500 hover:text-white"
                    @click="resetFilters"
                >
                    {{ t('challenges.browse.filters.clear') }}
                </button>
            </div>
        </div>

        <div
            v-if="isEmpty"
            class="rounded-lg border border-slate-800 bg-slate-900/40 p-10 text-center"
        >
            <p class="text-lg font-medium text-white">
                {{ t('challenges.browse.empty') }}
            </p>
            <p class="mt-2 text-sm text-slate-400">
                {{ t('challenges.browse.empty_hint') }}
            </p>
        </div>

        <ul v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <li
                v-for="c in challenges.data"
                :key="c.slug"
                class="group rounded-lg border border-slate-800 bg-slate-900/40 p-5 transition hover:border-cyan-400/50 hover:bg-slate-900/70"
            >
                <Link :href="c.url" class="block">
                    <div class="flex items-center gap-2">
                        <span
                            class="rounded-full border px-2 py-0.5 text-xs font-medium"
                            :class="categoryColor[c.category]"
                        >
                            {{ t(`challenges.browse.category.${c.category}`) }}
                        </span>
                        <span
                            class="rounded-full border px-2 py-0.5 text-xs font-medium"
                            :class="difficultyColor[c.difficulty]"
                        >
                            {{
                                t(
                                    `challenges.browse.difficulty.${c.difficulty}`,
                                )
                            }}
                        </span>
                    </div>
                    <h2
                        class="mt-3 text-lg font-semibold text-white group-hover:text-cyan-300"
                    >
                        {{ c.title }}
                    </h2>
                    <div
                        class="mt-4 flex items-center justify-between text-xs text-slate-400"
                    >
                        <span>{{
                            t('challenges.browse.card.points', {
                                n: c.points,
                            })
                        }}</span>
                        <span>{{
                            t('challenges.browse.card.solves', {
                                n: c.solve_count,
                            })
                        }}</span>
                    </div>
                </Link>
            </li>
        </ul>

        <nav
            v-if="challenges.meta.last_page > 1"
            class="mt-8 flex items-center justify-between text-sm text-slate-400"
        >
            <Link
                v-if="challenges.links.prev"
                :href="challenges.links.prev"
                preserve-scroll
                class="rounded-md border border-slate-700 px-3 py-1.5 text-slate-200 hover:border-slate-500 hover:text-white"
            >
                {{ t('challenges.browse.pagination.prev') }}
            </Link>
            <span v-else />
            <span>
                {{
                    t('challenges.browse.pagination.count', {
                        current: challenges.meta.current_page,
                        last: challenges.meta.last_page,
                    })
                }}
            </span>
            <Link
                v-if="challenges.links.next"
                :href="challenges.links.next"
                preserve-scroll
                class="rounded-md border border-slate-700 px-3 py-1.5 text-slate-200 hover:border-slate-500 hover:text-white"
            >
                {{ t('challenges.browse.pagination.next') }}
            </Link>
            <span v-else />
        </nav>
    </div>
</template>
