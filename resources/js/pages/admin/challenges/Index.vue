<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Archive, ExternalLink, Pencil, Plus, Rocket } from '@lucide/vue';
import { computed, reactive, watch } from 'vue';

type Status = 'draft' | 'review' | 'published' | 'archived';

type Row = {
    id: number;
    slug: string;
    title: string;
    category: string;
    difficulty: string;
    points: number;
    status: Status;
    flag_type: string;
    author: string | null;
    solve_count: number;
    files_count: number;
    locales: string[];
    updated_at: string | null;
    edit_url: string;
    public_url: string | null;
};

const props = defineProps<{
    challenges: {
        data: Row[];
        meta: { current_page: number; last_page: number; total: number };
        links: { prev: string | null; next: string | null };
    };
    filters: { status: string; category: string | null; q: string };
    options: { statuses: string[]; categories: string[] };
    create_url: string;
}>();

const page = usePage();
const canReview = computed(() => !!page.props.admin?.can.review);

const state = reactive({
    status: props.filters.status,
    category: props.filters.category ?? '',
    q: props.filters.q,
});

let debounce: ReturnType<typeof setTimeout> | undefined;

watch(
    state,
    (next) => {
        clearTimeout(debounce);
        debounce = setTimeout(() => {
            router.get(
                '/admin/challenges',
                {
                    status: next.status === 'all' ? undefined : next.status,
                    category: next.category || undefined,
                    q: next.q || undefined,
                },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 250);
    },
    { deep: true },
);

const statusStyle: Record<Status, string> = {
    draft: 'border-slate-500/30 bg-slate-500/10 text-muted-foreground',
    review: 'border-amber-500/30 bg-amber-500/10 text-amber-600 dark:text-amber-300',
    published: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-300',
    archived: 'border-zinc-500/30 bg-zinc-500/10 text-muted-foreground',
};

function lifecycle(slug: string, action: 'publish' | 'archive'): void {
    router.post(`/admin/challenges/${slug}/${action}`, {}, { preserveScroll: true });
}

const isEmpty = computed(() => props.challenges.data.length === 0);
</script>

<template>
    <Head title="Challenges · Admin" />

    <div class="mx-auto w-full max-w-6xl px-6 py-8">
        <header class="mb-6 flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Challenges</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ challenges.meta.total }} total
                </p>
            </div>
            <Link
                :href="create_url"
                class="inline-flex items-center gap-2 rounded-md bg-cyan-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-cyan-600"
            >
                <Plus class="h-4 w-4" /> New challenge
            </Link>
        </header>

        <div class="mb-6 grid gap-3 rounded-lg border border-border bg-card p-4 sm:grid-cols-3">
            <select
                v-model="state.status"
                class="rounded-md border border-border bg-background px-3 py-2 text-sm focus:border-cyan-400 focus:outline-none"
            >
                <option v-for="s in options.statuses" :key="s" :value="s">
                    {{ s === 'all' ? 'All statuses' : s }}
                </option>
            </select>
            <select
                v-model="state.category"
                class="rounded-md border border-border bg-background px-3 py-2 text-sm focus:border-cyan-400 focus:outline-none"
            >
                <option value="">All categories</option>
                <option v-for="c in options.categories" :key="c" :value="c">
                    {{ c }}
                </option>
            </select>
            <input
                v-model="state.q"
                type="search"
                placeholder="Search slug or title…"
                class="rounded-md border border-border bg-background px-3 py-2 text-sm focus:border-cyan-400 focus:outline-none"
            />
        </div>

        <div
            v-if="isEmpty"
            class="rounded-lg border border-border bg-card p-10 text-center text-muted-foreground"
        >
            No challenges match these filters.
        </div>

        <div v-else class="overflow-x-auto rounded-lg border border-border bg-card">
            <table class="w-full min-w-[720px] text-sm">
                <thead
                    class="border-b border-border text-left text-xs tracking-wide text-muted-foreground uppercase"
                >
                    <tr>
                        <th class="px-4 py-3 font-medium">Challenge</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Cat / Diff</th>
                        <th class="px-4 py-3 font-medium">Pts</th>
                        <th class="px-4 py-3 font-medium">Solves</th>
                        <th class="px-4 py-3 font-medium">Locales</th>
                        <th class="px-4 py-3 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr
                        v-for="row in challenges.data"
                        :key="row.id"
                        class="transition hover:bg-accent/50"
                    >
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ row.title }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{ row.slug }}
                                <span v-if="row.author"> · {{ row.author }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <span
                                class="rounded-full border px-2 py-0.5 text-xs font-medium capitalize"
                                :class="statusStyle[row.status]"
                            >
                                {{ row.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ row.category }} / {{ row.difficulty }}
                        </td>
                        <td class="px-4 py-3 tabular-nums">{{ row.points }}</td>
                        <td class="px-4 py-3 tabular-nums">{{ row.solve_count }}</td>
                        <td class="px-4 py-3 text-xs uppercase text-muted-foreground">
                            {{ row.locales.join(' ') || '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <Link
                                    :href="row.edit_url"
                                    title="Edit"
                                    class="rounded-md p-2 text-muted-foreground transition hover:bg-accent hover:text-foreground"
                                >
                                    <Pencil class="h-4 w-4" />
                                </Link>
                                <a
                                    v-if="row.public_url"
                                    :href="row.public_url"
                                    target="_blank"
                                    title="View public page"
                                    class="rounded-md p-2 text-muted-foreground transition hover:bg-accent hover:text-foreground"
                                >
                                    <ExternalLink class="h-4 w-4" />
                                </a>
                                <button
                                    v-if="canReview && row.status !== 'published'"
                                    type="button"
                                    title="Publish"
                                    class="rounded-md p-2 text-muted-foreground transition hover:bg-emerald-500/10 hover:text-emerald-500"
                                    @click="lifecycle(row.slug, 'publish')"
                                >
                                    <Rocket class="h-4 w-4" />
                                </button>
                                <button
                                    v-if="canReview && row.status !== 'archived'"
                                    type="button"
                                    title="Archive"
                                    class="rounded-md p-2 text-muted-foreground transition hover:bg-rose-500/10 hover:text-rose-500"
                                    @click="lifecycle(row.slug, 'archive')"
                                >
                                    <Archive class="h-4 w-4" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav
            v-if="challenges.meta.last_page > 1"
            class="mt-6 flex items-center justify-between text-sm text-muted-foreground"
        >
            <Link
                v-if="challenges.links.prev"
                :href="challenges.links.prev"
                preserve-scroll
                class="rounded-md border border-border px-3 py-1.5 hover:text-foreground"
            >
                Previous
            </Link>
            <span v-else />
            <span>Page {{ challenges.meta.current_page }} of {{ challenges.meta.last_page }}</span>
            <Link
                v-if="challenges.links.next"
                :href="challenges.links.next"
                preserve-scroll
                class="rounded-md border border-border px-3 py-1.5 hover:text-foreground"
            >
                Next
            </Link>
            <span v-else />
        </nav>
    </div>
</template>
