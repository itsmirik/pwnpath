<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Check, ExternalLink, X } from '@lucide/vue';
import { reactive } from 'vue';

type Writeup = {
    id: number;
    status: 'pending' | 'approved' | 'rejected';
    locale: string;
    content_html: string;
    moderation_note: string | null;
    upvote_count: number;
    author: {
        username: string | null;
        display_name: string | null;
        avatar_color: string | null;
        url: string | null;
    };
    challenge: { title: string | null; url: string | null };
    moderator: string | null;
    created_at: string | null;
    moderated_at: string | null;
    approve_url: string;
    reject_url: string;
};

const props = defineProps<{
    writeups: {
        data: Writeup[];
        meta: { current_page: number; last_page: number; total: number };
        links: { prev: string | null; next: string | null };
    };
    filters: { status: string };
    options: { statuses: string[] };
}>();

const notes = reactive<Record<number, string>>({});

function setStatus(status: string): void {
    router.get(
        '/admin/writeups',
        { status: status === 'pending' ? undefined : status },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function approve(w: Writeup): void {
    router.post(w.approve_url, { note: notes[w.id] ?? '' }, { preserveScroll: true });
}

function reject(w: Writeup): void {
    const note = (notes[w.id] ?? '').trim();

    if (!note) {
        return;
    }

    router.post(w.reject_url, { note }, { preserveScroll: true });
}

const statusStyle: Record<string, string> = {
    pending: 'border-amber-500/30 bg-amber-500/10 text-amber-600 dark:text-amber-300',
    approved: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-300',
    rejected: 'border-rose-500/30 bg-rose-500/10 text-rose-600 dark:text-rose-300',
};

function formatDate(iso: string | null): string {
    return iso ? new Date(iso).toLocaleDateString() : '';
}

const activeStatus = props.filters.status;
</script>

<template>
    <Head title="Writeups · Admin" />

    <div class="mx-auto w-full max-w-4xl px-6 py-8">
        <header class="mb-6">
            <h1 class="text-2xl font-bold tracking-tight">Writeup moderation</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                {{ writeups.meta.total }} in this view
            </p>
        </header>

        <div class="mb-6 flex gap-1 rounded-md border border-border bg-card p-1">
            <button
                v-for="s in options.statuses"
                :key="s"
                type="button"
                class="flex-1 rounded px-3 py-1.5 text-sm font-medium capitalize transition"
                :class="
                    activeStatus === s
                        ? 'bg-accent text-foreground'
                        : 'text-muted-foreground hover:text-foreground'
                "
                @click="setStatus(s)"
            >
                {{ s }}
            </button>
        </div>

        <div
            v-if="writeups.data.length === 0"
            class="rounded-lg border border-border bg-card p-10 text-center text-muted-foreground"
        >
            Nothing to moderate here.
        </div>

        <div v-else class="space-y-6">
            <article
                v-for="w in writeups.data"
                :key="w.id"
                class="rounded-lg border border-border bg-card p-5"
            >
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2 text-sm">
                        <a
                            v-if="w.challenge.url"
                            :href="w.challenge.url"
                            target="_blank"
                            class="inline-flex items-center gap-1 font-semibold hover:text-cyan-500"
                        >
                            {{ w.challenge.title }}
                            <ExternalLink class="h-3.5 w-3.5" />
                        </a>
                        <span class="text-muted-foreground">
                            by
                            <Link
                                v-if="w.author.url"
                                :href="w.author.url"
                                class="hover:text-foreground"
                            >
                                {{ w.author.display_name ?? w.author.username }}
                            </Link>
                        </span>
                        <span class="text-xs text-muted-foreground uppercase">{{ w.locale }}</span>
                    </div>
                    <span
                        class="rounded-full border px-2 py-0.5 text-xs font-medium capitalize"
                        :class="statusStyle[w.status]"
                    >
                        {{ w.status }}
                    </span>
                </div>

                <div
                    class="prose prose-sm max-w-none rounded-md border border-border bg-background p-4 dark:prose-invert"
                    v-html="w.content_html"
                />

                <div
                    v-if="w.moderation_note"
                    class="mt-3 rounded-md border border-border bg-accent/40 px-3 py-2 text-sm"
                >
                    <span class="text-muted-foreground">Note:</span> {{ w.moderation_note }}
                    <span v-if="w.moderator" class="text-xs text-muted-foreground">
                        — {{ w.moderator }}
                    </span>
                </div>

                <div class="mt-4 space-y-2">
                    <textarea
                        v-model="notes[w.id]"
                        rows="2"
                        placeholder="Moderation note (required to reject)…"
                        class="w-full rounded-md border border-border bg-background px-3 py-2 text-sm focus:border-cyan-400 focus:outline-none"
                    />
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-muted-foreground">
                            submitted {{ formatDate(w.created_at) }}
                        </span>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                :disabled="!(notes[w.id] ?? '').trim()"
                                class="inline-flex items-center gap-1.5 rounded-md border border-border px-3 py-1.5 text-sm transition hover:border-rose-400/50 hover:text-rose-500 disabled:opacity-50"
                                @click="reject(w)"
                            >
                                <X class="h-4 w-4" /> Reject
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-md bg-emerald-500 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-emerald-600"
                                @click="approve(w)"
                            >
                                <Check class="h-4 w-4" /> Approve
                            </button>
                        </div>
                    </div>
                </div>
            </article>
        </div>

        <nav
            v-if="writeups.meta.last_page > 1"
            class="mt-6 flex items-center justify-between text-sm text-muted-foreground"
        >
            <Link
                v-if="writeups.links.prev"
                :href="writeups.links.prev"
                preserve-scroll
                class="rounded-md border border-border px-3 py-1.5 hover:text-foreground"
            >
                Previous
            </Link>
            <span v-else />
            <span>Page {{ writeups.meta.current_page }} of {{ writeups.meta.last_page }}</span>
            <Link
                v-if="writeups.links.next"
                :href="writeups.links.next"
                preserve-scroll
                class="rounded-md border border-border px-3 py-1.5 hover:text-foreground"
            >
                Next
            </Link>
            <span v-else />
        </nav>
    </div>
</template>
