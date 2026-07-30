<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { EyeOff, ExternalLink, X } from '@lucide/vue';

type Report = {
    id: number;
    reason: string;
    status: 'open' | 'resolved';
    reporter: string | null;
    created_at: string | null;
    comment: {
        id: number;
        content: string;
        is_hidden: boolean;
        is_reply: boolean;
        author: {
            username: string | null;
            display_name: string | null;
            avatar_color: string | null;
        };
        challenge: { title: string | null; url: string | null };
        hide_url: string;
    } | null;
    dismiss_url: string;
};

const props = defineProps<{
    reports: {
        data: Report[];
        meta: { current_page: number; last_page: number; total: number };
        links: { prev: string | null; next: string | null };
    };
    filters: { status: string };
    options: { statuses: string[] };
}>();

function setStatus(status: string): void {
    router.get(
        '/admin/reports',
        { status: status === 'open' ? undefined : status },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function hide(url: string): void {
    router.post(url, {}, { preserveScroll: true });
}

function dismiss(url: string): void {
    router.post(url, {}, { preserveScroll: true });
}

function formatDate(iso: string | null): string {
    return iso ? new Date(iso).toLocaleDateString() : '';
}

const activeStatus = props.filters.status;
</script>

<template>
    <Head title="Reports · Admin" />

    <div class="mx-auto w-full max-w-4xl px-6 py-8">
        <header class="mb-6">
            <h1 class="text-2xl font-bold tracking-tight">Comment reports</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                {{ reports.meta.total }} in this view
            </p>
        </header>

        <div
            class="mb-6 flex gap-1 rounded-md border border-border bg-card p-1"
        >
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
            v-if="reports.data.length === 0"
            class="rounded-lg border border-border bg-card p-10 text-center text-muted-foreground"
        >
            No reports here.
        </div>

        <div v-else class="space-y-4">
            <article
                v-for="r in reports.data"
                :key="r.id"
                class="rounded-lg border border-border bg-card p-5"
            >
                <div
                    class="mb-3 flex flex-wrap items-center justify-between gap-2 text-sm"
                >
                    <div class="flex items-center gap-2">
                        <span
                            class="rounded-full border border-rose-500/30 bg-rose-500/10 px-2 py-0.5 text-xs font-medium text-rose-600 dark:text-rose-300"
                        >
                            {{ r.reason }}
                        </span>
                        <span class="text-muted-foreground">
                            reported by {{ r.reporter ?? '—' }} ·
                            {{ formatDate(r.created_at) }}
                        </span>
                    </div>
                    <span
                        v-if="r.status === 'resolved'"
                        class="text-xs text-muted-foreground uppercase"
                    >
                        resolved
                    </span>
                </div>

                <div
                    v-if="r.comment"
                    class="rounded-md border border-border bg-background p-4"
                >
                    <div
                        class="mb-2 flex items-center justify-between gap-2 text-xs text-muted-foreground"
                    >
                        <span>
                            {{
                                r.comment.author.display_name ??
                                r.comment.author.username
                            }}
                            <span v-if="r.comment.is_reply"> · reply</span>
                            <span
                                v-if="r.comment.is_hidden"
                                class="text-rose-500"
                            >
                                · hidden</span
                            >
                        </span>
                        <a
                            v-if="r.comment.challenge.url"
                            :href="r.comment.challenge.url"
                            target="_blank"
                            class="inline-flex items-center gap-1 hover:text-foreground"
                        >
                            {{ r.comment.challenge.title }}
                            <ExternalLink class="h-3 w-3" />
                        </a>
                    </div>
                    <p class="text-sm whitespace-pre-wrap">
                        {{ r.comment.content }}
                    </p>
                </div>
                <div v-else class="text-sm text-muted-foreground">
                    Comment was deleted.
                </div>

                <div
                    v-if="r.status === 'open'"
                    class="mt-4 flex justify-end gap-2"
                >
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md border border-border px-3 py-1.5 text-sm transition hover:border-foreground/30"
                        @click="dismiss(r.dismiss_url)"
                    >
                        <X class="h-4 w-4" /> Dismiss
                    </button>
                    <button
                        v-if="r.comment && !r.comment.is_hidden"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md bg-rose-500 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-rose-600"
                        @click="hide(r.comment.hide_url)"
                    >
                        <EyeOff class="h-4 w-4" /> Hide comment
                    </button>
                </div>
            </article>
        </div>

        <nav
            v-if="reports.meta.last_page > 1"
            class="mt-6 flex items-center justify-between text-sm text-muted-foreground"
        >
            <Link
                v-if="reports.links.prev"
                :href="reports.links.prev"
                preserve-scroll
                class="rounded-md border border-border px-3 py-1.5 hover:text-foreground"
            >
                Previous
            </Link>
            <span v-else />
            <span
                >Page {{ reports.meta.current_page }} of
                {{ reports.meta.last_page }}</span
            >
            <Link
                v-if="reports.links.next"
                :href="reports.links.next"
                preserve-scroll
                class="rounded-md border border-border px-3 py-1.5 hover:text-foreground"
            >
                Next
            </Link>
            <span v-else />
        </nav>
    </div>
</template>
