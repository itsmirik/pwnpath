<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { getInitials } from '@/composables/useInitials';

type Scope = 'global' | 'weekly' | 'category';
type Rank = 'bronze' | 'silver' | 'gold' | 'platinum' | 'diamond';

type Entry = {
    position: number;
    username: string;
    display_name: string;
    avatar_color: string;
    country_code: string | null;
    xp: number;
    rank: Rank;
    solves_count: number;
    is_me: boolean;
};

type Props = {
    scope: Scope;
    category: string | null;
    categories: string[];
    entries: Entry[];
    me: Entry | null;
};

const props = defineProps<Props>();
const { t } = useI18n();

const rankColor: Record<Rank, string> = {
    bronze: 'border-amber-700/40 bg-amber-700/10 text-amber-700 dark:text-amber-500',
    silver: 'border-slate-400/40 bg-slate-400/10 text-muted-foreground',
    gold: 'border-yellow-500/40 bg-yellow-500/10 text-yellow-700 dark:text-yellow-300',
    platinum:
        'border-cyan-400/40 bg-cyan-400/10 text-cyan-800 dark:text-cyan-200',
    diamond:
        'border-fuchsia-400/40 bg-fuchsia-400/10 text-fuchsia-700 dark:text-fuchsia-200',
};

const medals: Record<number, string> = { 1: '🥇', 2: '🥈', 3: '🥉' };

const tabs = computed(() => [
    {
        key: 'global' as Scope,
        href: '/leaderboard',
        label: t('leaderboard.index.tabs.global'),
    },
    {
        key: 'weekly' as Scope,
        href: '/leaderboard/weekly',
        label: t('leaderboard.index.tabs.weekly'),
    },
    {
        key: 'category' as Scope,
        href: `/leaderboard/category/${props.category ?? props.categories[0]}`,
        label: t('leaderboard.index.tabs.category'),
    },
]);

const showMe = computed(
    () => props.me !== null && !props.entries.some((e) => e.is_me),
);

function xp(value: number): string {
    return value.toLocaleString();
}
</script>

<template>
    <Head :title="t('leaderboard.index.title')" />

    <div class="w-full px-6 py-8">
        <header class="mb-8">
            <h1
                class="text-3xl font-bold tracking-tight text-foreground sm:text-4xl"
            >
                {{ t('leaderboard.index.title') }}
            </h1>
            <p class="mt-2 text-muted-foreground">
                {{ t('leaderboard.index.subtitle') }}
            </p>
        </header>

        <!-- Scope tabs -->
        <nav class="mb-6 flex flex-wrap gap-2">
            <Link
                v-for="tab in tabs"
                :key="tab.key"
                :href="tab.href"
                class="rounded-full border px-4 py-1.5 text-sm font-medium transition"
                :class="
                    scope === tab.key
                        ? 'border-cyan-400/60 bg-cyan-400/10 text-cyan-800 dark:text-cyan-200'
                        : 'border-border text-muted-foreground hover:border-foreground/30 hover:text-foreground'
                "
            >
                {{ tab.label }}
            </Link>
        </nav>

        <!-- Category chooser -->
        <div v-if="scope === 'category'" class="mb-6 flex flex-wrap gap-2">
            <Link
                v-for="cat in categories"
                :key="cat"
                :href="`/leaderboard/category/${cat}`"
                class="rounded-md border px-3 py-1 text-xs font-medium transition"
                :class="
                    category === cat
                        ? 'border-cyan-400/60 bg-cyan-400/10 text-cyan-800 dark:text-cyan-200'
                        : 'border-border text-muted-foreground hover:border-foreground/30 hover:text-foreground'
                "
            >
                {{ t(`challenges.browse.category.${cat}`) }}
            </Link>
        </div>

        <div
            v-if="entries.length === 0"
            class="rounded-lg border border-border bg-card p-10 text-center text-muted-foreground"
        >
            {{ t('leaderboard.index.empty') }}
        </div>

        <div
            v-else
            class="overflow-hidden rounded-lg border border-border bg-card"
        >
            <table class="w-full text-left text-sm">
                <thead
                    class="border-b border-border text-xs tracking-wider text-muted-foreground uppercase"
                >
                    <tr>
                        <th class="w-16 px-4 py-3">
                            {{ t('leaderboard.index.columns.rank') }}
                        </th>
                        <th class="px-4 py-3">
                            {{ t('leaderboard.index.columns.player') }}
                        </th>
                        <th class="px-4 py-3 text-right">
                            {{ t('leaderboard.index.columns.solves') }}
                        </th>
                        <th class="px-4 py-3 text-right">
                            {{ t('leaderboard.index.columns.xp') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr
                        v-for="entry in entries"
                        :key="entry.username"
                        :class="
                            entry.is_me ? 'bg-cyan-400/5' : 'hover:bg-accent'
                        "
                    >
                        <td
                            class="px-4 py-3 text-lg font-semibold text-muted-foreground"
                        >
                            <span v-if="medals[entry.position]">{{
                                medals[entry.position]
                            }}</span>
                            <span v-else class="text-muted-foreground"
                                >#{{ entry.position }}</span
                            >
                        </td>
                        <td class="px-4 py-3">
                            <Link
                                :href="`/u/${entry.username}`"
                                class="group flex items-center gap-3"
                            >
                                <span
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold text-slate-950"
                                    :style="{
                                        backgroundColor: entry.avatar_color,
                                    }"
                                >
                                    {{ getInitials(entry.display_name) }}
                                </span>
                                <span class="min-w-0">
                                    <span
                                        class="block truncate font-medium text-foreground group-hover:text-cyan-600 dark:group-hover:text-cyan-300"
                                    >
                                        {{ entry.display_name }}
                                        <span
                                            v-if="entry.is_me"
                                            class="ml-1 text-xs text-cyan-600 dark:text-cyan-400"
                                            >({{
                                                t('leaderboard.index.you')
                                            }})</span
                                        >
                                    </span>
                                    <span class="flex items-center gap-2">
                                        <span
                                            class="truncate text-xs text-muted-foreground"
                                            >@{{ entry.username }}</span
                                        >
                                        <span
                                            class="rounded-full border px-1.5 py-0.5 text-[10px] font-medium"
                                            :class="rankColor[entry.rank]"
                                        >
                                            {{
                                                t(`common.ranks.${entry.rank}`)
                                            }}
                                        </span>
                                    </span>
                                </span>
                            </Link>
                        </td>
                        <td class="px-4 py-3 text-right text-muted-foreground">
                            {{ entry.solves_count }}
                        </td>
                        <td
                            class="px-4 py-3 text-right font-semibold text-cyan-700 dark:text-cyan-300"
                        >
                            {{ xp(entry.xp) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Current user, if outside the visible list -->
        <div
            v-if="showMe && me"
            class="mt-4 rounded-lg border border-cyan-400/30 bg-cyan-400/5 px-4 py-3"
        >
            <p
                class="mb-1 text-xs tracking-wider text-cyan-700 uppercase dark:text-cyan-400/80"
            >
                {{ t('leaderboard.index.your_position') }}
            </p>
            <div class="flex items-center justify-between text-sm">
                <div class="flex items-center gap-3">
                    <span class="font-semibold text-muted-foreground"
                        >#{{ me.position }}</span
                    >
                    <span class="font-medium text-foreground">{{
                        me.display_name
                    }}</span>
                </div>
                <span class="font-semibold text-cyan-700 dark:text-cyan-300"
                    >{{ xp(me.xp) }} XP</span
                >
            </div>
        </div>
    </div>
</template>
