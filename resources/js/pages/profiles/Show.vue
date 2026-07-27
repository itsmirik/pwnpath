<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { getInitials } from '@/composables/useInitials';

type Rank = 'bronze' | 'silver' | 'gold' | 'platinum' | 'diamond';
type Category = 'web' | 'crypto' | 'forensics' | 'osint' | 'llm' | 'misc';
type Difficulty = 'easy' | 'medium' | 'hard';

type Profile = {
    username: string;
    display_name: string;
    bio: string | null;
    avatar_color: string;
    country_code: string | null;
    locale: string;
    xp_total: number;
    streak_count: number;
    joined_at: string | null;
    rank: {
        tier: Rank;
        next_tier: Rank | null;
        floor: number;
        next: number | null;
        xp: number;
        progress: number;
    };
    global_position: number | null;
    solves_total: number;
};

type CategoryCount = { category: Category; count: number };

type BadgeItem = {
    slug: string;
    name: string;
    description: string;
    icon: string;
    categories: string[];
    earned: boolean;
    awarded_at: string | null;
};

type TimelineItem = {
    challenge_slug: string | null;
    title: string | null;
    category: Category | null;
    difficulty: Difficulty | null;
    points: number;
    solved_at: string | null;
};

const props = defineProps<{
    profile: Profile;
    categories: CategoryCount[];
    badges: BadgeItem[];
    timeline: TimelineItem[];
}>();

const { t, locale } = useI18n();

const initials = computed(() => getInitials(props.profile.display_name));

const rankColor: Record<Rank, string> = {
    bronze: 'border-amber-700/40 bg-amber-700/10 text-amber-700 dark:text-amber-500',
    silver: 'border-slate-400/40 bg-slate-400/10 text-muted-foreground',
    gold: 'border-yellow-500/40 bg-yellow-500/10 text-yellow-700 dark:text-yellow-300',
    platinum:
        'border-cyan-400/40 bg-cyan-400/10 text-cyan-800 dark:text-cyan-200',
    diamond:
        'border-fuchsia-400/40 bg-fuchsia-400/10 text-fuchsia-700 dark:text-fuchsia-200',
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

const earnedCount = computed(() => props.badges.filter((b) => b.earned).length);

const progressPercent = computed(() =>
    Math.round(props.profile.rank.progress * 100),
);

function formatDate(value: string | null): string {
    if (!value) {
        return '';
    }

    return new Intl.DateTimeFormat(locale.value, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));
}

const joinedFormatted = computed(() => {
    if (!props.profile.joined_at) {
        return '';
    }

    return new Intl.DateTimeFormat(locale.value, {
        year: 'numeric',
        month: 'short',
    }).format(new Date(props.profile.joined_at));
});
</script>

<template>
    <Head :title="`@${profile.username}`" />

    <div class="w-full px-6 py-8">
        <!-- Identity header -->
        <div
            class="flex flex-col gap-6 rounded-xl border border-border bg-card p-8 sm:flex-row sm:items-center"
        >
            <div
                class="flex h-24 w-24 shrink-0 items-center justify-center rounded-full text-3xl font-bold text-slate-950"
                :style="{ backgroundColor: profile.avatar_color }"
            >
                {{ initials }}
            </div>
            <div class="flex-1">
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold text-foreground">
                        {{ profile.display_name }}
                    </h1>
                    <span
                        class="rounded-full border px-2.5 py-0.5 text-xs font-semibold"
                        :class="rankColor[profile.rank.tier]"
                    >
                        {{ t(`common.ranks.${profile.rank.tier}`) }}
                    </span>
                </div>
                <p class="text-sm text-muted-foreground">
                    @{{ profile.username }}
                </p>
                <p
                    v-if="profile.bio"
                    class="mt-3 max-w-xl text-sm text-muted-foreground"
                >
                    {{ profile.bio }}
                </p>
                <p v-else class="mt-3 text-sm text-muted-foreground italic">
                    {{ t('profile.public.no_bio') }}
                </p>
                <div
                    class="mt-4 flex flex-wrap gap-2 text-xs text-muted-foreground"
                >
                    <span
                        v-if="profile.country_code"
                        class="rounded-full border border-border px-2 py-0.5"
                    >
                        {{ t('profile.public.country') }}:
                        {{ profile.country_code }}
                    </span>
                    <span
                        v-if="joinedFormatted"
                        class="rounded-full border border-border px-2 py-0.5"
                    >
                        {{ t('profile.public.joined') }}: {{ joinedFormatted }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Stat cards -->
        <div class="mt-6 grid gap-4 sm:grid-cols-3">
            <div class="rounded-lg border border-border bg-card p-5">
                <p
                    class="text-xs tracking-wider text-muted-foreground uppercase"
                >
                    {{ t('profile.public.xp') }}
                </p>
                <p
                    class="mt-1 text-3xl font-bold text-cyan-700 dark:text-cyan-300"
                >
                    {{ profile.xp_total.toLocaleString() }}
                </p>
                <div
                    v-if="profile.rank.next !== null"
                    class="mt-3"
                    :title="`${progressPercent}%`"
                >
                    <div class="h-1.5 overflow-hidden rounded-full bg-muted">
                        <div
                            class="h-full rounded-full bg-cyan-400"
                            :style="{ width: `${progressPercent}%` }"
                        />
                    </div>
                    <p
                        v-if="profile.rank.next_tier"
                        class="mt-1.5 text-xs text-muted-foreground"
                    >
                        {{
                            t('profile.public.to_next_rank', {
                                xp: (
                                    profile.rank.next - profile.rank.xp
                                ).toLocaleString(),
                                tier: t(
                                    `common.ranks.${profile.rank.next_tier}`,
                                ),
                            })
                        }}
                    </p>
                </div>
                <p v-else class="mt-3 text-xs text-muted-foreground">
                    {{ t('profile.public.max_rank') }}
                </p>
            </div>

            <div class="rounded-lg border border-border bg-card p-5">
                <p
                    class="text-xs tracking-wider text-muted-foreground uppercase"
                >
                    {{ t('profile.public.streak') }}
                </p>
                <p class="mt-1 text-3xl font-bold text-orange-300">
                    🔥 {{ profile.streak_count }}
                </p>
                <p class="mt-3 text-xs text-muted-foreground">
                    {{ profile.solves_total }} ·
                    {{ t('profile.public.solved') }}
                </p>
            </div>

            <div class="rounded-lg border border-border bg-card p-5">
                <p
                    class="text-xs tracking-wider text-muted-foreground uppercase"
                >
                    {{ t('profile.public.global_rank') }}
                </p>
                <p class="mt-1 text-3xl font-bold text-foreground">
                    <span v-if="profile.global_position"
                        >#{{ profile.global_position }}</span
                    >
                    <span v-else class="text-muted-foreground">—</span>
                </p>
            </div>
        </div>

        <!-- Solved per category -->
        <section class="mt-8">
            <h2 class="mb-3 text-lg font-semibold text-foreground">
                {{ t('profile.public.categories') }}
            </h2>
            <div class="grid grid-cols-3 gap-3 sm:grid-cols-6">
                <div
                    v-for="cat in categories"
                    :key="cat.category"
                    class="rounded-lg border border-border bg-card p-3 text-center"
                >
                    <p
                        class="text-xl font-bold"
                        :class="
                            cat.count > 0
                                ? 'text-foreground'
                                : 'text-muted-foreground'
                        "
                    >
                        {{ cat.count }}
                    </p>
                    <p class="mt-1 truncate text-[11px] text-muted-foreground">
                        {{ t(`challenges.browse.category.${cat.category}`) }}
                    </p>
                </div>
            </div>
        </section>

        <!-- Badges -->
        <section class="mt-8">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-foreground">
                    {{ t('profile.public.badges') }}
                </h2>
                <span class="text-sm text-muted-foreground">
                    {{
                        t('profile.public.badges_progress', {
                            earned: earnedCount,
                            total: badges.length,
                        })
                    }}
                </span>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                <div
                    v-for="badge in badges"
                    :key="badge.slug"
                    class="flex flex-col items-center rounded-lg border p-4 text-center transition"
                    :class="
                        badge.earned
                            ? 'border-cyan-400/30 bg-cyan-400/5'
                            : 'border-border bg-muted/30 opacity-50'
                    "
                    :title="badge.description"
                >
                    <span
                        class="text-3xl"
                        :class="badge.earned ? '' : 'grayscale'"
                        >{{ badge.icon }}</span
                    >
                    <p
                        class="mt-2 text-xs font-medium"
                        :class="
                            badge.earned
                                ? 'text-foreground'
                                : 'text-muted-foreground'
                        "
                    >
                        {{ badge.name }}
                    </p>
                    <p
                        v-if="badge.earned && badge.awarded_at"
                        class="mt-0.5 text-[10px] text-muted-foreground"
                    >
                        {{ formatDate(badge.awarded_at) }}
                    </p>
                    <p
                        v-else-if="!badge.earned"
                        class="mt-0.5 text-[10px] text-muted-foreground"
                    >
                        {{ t('profile.public.locked') }}
                    </p>
                </div>
            </div>
        </section>

        <!-- Solve timeline -->
        <section class="mt-8">
            <h2 class="mb-3 text-lg font-semibold text-foreground">
                {{ t('profile.public.timeline') }}
            </h2>
            <div
                v-if="timeline.length === 0"
                class="rounded-lg border border-border bg-card p-6 text-center text-sm text-muted-foreground"
            >
                {{ t('profile.public.timeline_empty') }}
            </div>
            <ul
                v-else
                class="divide-y divide-border overflow-hidden rounded-lg border border-border bg-card"
            >
                <li
                    v-for="(item, i) in timeline"
                    :key="i"
                    class="flex items-center justify-between gap-3 px-4 py-3"
                >
                    <div class="min-w-0">
                        <component
                            :is="item.challenge_slug ? Link : 'span'"
                            :href="
                                item.challenge_slug
                                    ? `/challenges/${item.challenge_slug}`
                                    : undefined
                            "
                            class="block truncate font-medium text-foreground"
                            :class="
                                item.challenge_slug
                                    ? 'hover:text-cyan-600 dark:hover:text-cyan-300'
                                    : ''
                            "
                        >
                            {{ item.title }}
                        </component>
                        <div class="mt-1 flex items-center gap-2">
                            <span
                                v-if="item.category"
                                class="rounded-full border px-1.5 py-0.5 text-[10px] font-medium"
                                :class="categoryColor[item.category]"
                            >
                                {{
                                    t(
                                        `challenges.browse.category.${item.category}`,
                                    )
                                }}
                            </span>
                            <span class="text-xs text-muted-foreground">{{
                                formatDate(item.solved_at)
                            }}</span>
                        </div>
                    </div>
                    <span
                        class="shrink-0 text-sm font-semibold text-cyan-700 dark:text-cyan-300"
                    >
                        +{{ item.points }}
                    </span>
                </li>
            </ul>
        </section>
    </div>
</template>
