<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    FileClock,
    FilePlus2,
    FileText,
    RefreshCw,
    ShieldAlert,
    UserPlus,
    Zap,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import type { LucideIcon } from '@lucide/vue';

type Stats = {
    pending_writeups: number;
    open_reports: number;
    draft_challenges: number;
    review_challenges: number;
    signups_this_week: number;
    solves_this_week: number;
    flagged_accounts: number;
};

type Activity = {
    id: number;
    action: string;
    actor: string | null;
    entity_type: string | null;
    entity_id: number | null;
    created_at: string | null;
};

const props = defineProps<{ stats: Stats; recent_activity: Activity[] }>();

const page = usePage();
const canSync = computed(() => !!page.props.admin?.can.review);

const syncing = ref(false);

function runSync(): void {
    if (syncing.value) {
        return;
    }
    syncing.value = true;
    router.post(
        '/admin/sync',
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                syncing.value = false;
            },
        },
    );
}

type Tile = {
    label: string;
    value: number;
    href: string;
    icon: LucideIcon;
    accent: string;
};

const tiles = computed<Tile[]>(() => [
    { label: 'Pending writeups', value: props.stats.pending_writeups, href: '/admin/writeups', icon: FileText, accent: 'text-amber-500' },
    { label: 'Open reports', value: props.stats.open_reports, href: '/admin/reports', icon: ShieldAlert, accent: 'text-rose-500' },
    { label: 'Draft challenges', value: props.stats.draft_challenges, href: '/admin/challenges?status=draft', icon: FilePlus2, accent: 'text-cyan-500' },
    { label: 'In review', value: props.stats.review_challenges, href: '/admin/challenges?status=review', icon: FileClock, accent: 'text-violet-500' },
    { label: 'Signups this week', value: props.stats.signups_this_week, href: '/admin/users', icon: UserPlus, accent: 'text-emerald-500' },
    { label: 'Solves this week', value: props.stats.solves_this_week, href: '/admin/challenges', icon: Zap, accent: 'text-lime-500' },
    { label: 'Flagged accounts (7d)', value: props.stats.flagged_accounts, href: '/admin/audit?action=security.suspicious_solves', icon: AlertTriangle, accent: 'text-orange-500' },
]);

const actionLabels: Record<string, string> = {
    'challenge.create': 'created challenge',
    'challenge.update': 'edited challenge',
    'challenge.submit_review': 'submitted challenge for review',
    'challenge.publish': 'published challenge',
    'challenge.unpublish': 'unpublished challenge',
    'challenge.archive': 'archived challenge',
    'challenge.sync': 'synced challenges from repo',
    'writeup.approve': 'approved writeup',
    'writeup.reject': 'rejected writeup',
    'comment.hide': 'hid comment',
    'report.dismiss': 'dismissed report',
    'user.role_change': 'changed user role',
    'user.ban': 'suspended user',
    'user.unban': 'reinstated user',
    'security.suspicious_solves': 'flagged for suspicious solves',
};

function describe(a: Activity): string {
    return actionLabels[a.action] ?? a.action;
}

function formatTime(iso: string | null): string {
    if (!iso) {
        return '';
    }

    return new Date(iso).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <Head title="Admin" />

    <div class="mx-auto w-full max-w-6xl px-6 py-8">
        <header class="mb-8 flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Dashboard</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Moderation backlog and this week's activity.
                </p>
            </div>
            <button
                v-if="canSync"
                type="button"
                :disabled="syncing"
                class="inline-flex items-center gap-2 rounded-md border border-border bg-card px-4 py-2 text-sm font-medium transition hover:border-cyan-400/50 hover:text-cyan-500 disabled:opacity-60"
                @click="runSync"
            >
                <RefreshCw class="h-4 w-4" :class="syncing ? 'animate-spin' : ''" />
                {{ syncing ? 'Syncing…' : 'Sync from repo' }}
            </button>
        </header>

        <section class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            <Link
                v-for="tile in tiles"
                :key="tile.label"
                :href="tile.href"
                class="group rounded-lg border border-border bg-card p-4 transition hover:border-cyan-400/40 hover:bg-accent"
            >
                <div class="flex items-center justify-between">
                    <span class="text-xs tracking-wide text-muted-foreground uppercase">
                        {{ tile.label }}
                    </span>
                    <component :is="tile.icon" class="h-4 w-4" :class="tile.accent" />
                </div>
                <div class="mt-3 text-3xl font-bold tabular-nums">
                    {{ tile.value }}
                </div>
            </Link>
        </section>

        <section class="mt-10">
            <h2 class="mb-3 text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                Recent activity
            </h2>
            <div class="overflow-hidden rounded-lg border border-border bg-card">
                <div
                    v-if="recent_activity.length === 0"
                    class="p-6 text-center text-sm text-muted-foreground"
                >
                    No admin activity yet.
                </div>
                <ul v-else class="divide-y divide-border">
                    <li
                        v-for="entry in recent_activity"
                        :key="entry.id"
                        class="flex items-center justify-between gap-4 px-4 py-3 text-sm"
                    >
                        <span>
                            <span class="font-medium">{{ entry.actor ?? 'system' }}</span>
                            <span class="text-muted-foreground"> {{ describe(entry) }}</span>
                            <span
                                v-if="entry.entity_type"
                                class="text-muted-foreground"
                            >
                                ({{ entry.entity_type }}#{{ entry.entity_id }})
                            </span>
                        </span>
                        <span class="shrink-0 text-xs text-muted-foreground">
                            {{ formatTime(entry.created_at) }}
                        </span>
                    </li>
                </ul>
            </div>
        </section>
    </div>
</template>
