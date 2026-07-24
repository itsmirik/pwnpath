<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { getInitials } from '@/composables/useInitials';

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
};

const props = defineProps<{
    profile: Profile;
}>();

const { t, locale } = useI18n();

const initials = computed(() => getInitials(props.profile.display_name));

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

    <div class="mx-auto max-w-4xl px-6 py-12">
        <div
            class="flex flex-col gap-6 rounded-xl border border-slate-800 bg-slate-900/40 p-8 sm:flex-row sm:items-center"
        >
            <div
                class="flex h-24 w-24 shrink-0 items-center justify-center rounded-full text-3xl font-bold text-slate-950"
                :style="{ backgroundColor: profile.avatar_color }"
            >
                {{ initials }}
            </div>
            <div class="flex-1">
                <h1 class="text-2xl font-bold text-white">
                    {{ profile.display_name }}
                </h1>
                <p class="text-sm text-slate-400">@{{ profile.username }}</p>
                <p
                    v-if="profile.bio"
                    class="mt-3 max-w-xl text-sm text-slate-300"
                >
                    {{ profile.bio }}
                </p>
                <p v-else class="mt-3 text-sm text-slate-500 italic">
                    {{ t('profile.public.no_bio') }}
                </p>
                <div class="mt-4 flex flex-wrap gap-2 text-xs text-slate-400">
                    <span
                        v-if="profile.country_code"
                        class="rounded-full border border-slate-700 px-2 py-0.5"
                    >
                        {{ t('profile.public.country') }}:
                        {{ profile.country_code }}
                    </span>
                    <span
                        v-if="joinedFormatted"
                        class="rounded-full border border-slate-700 px-2 py-0.5"
                    >
                        {{ t('profile.public.joined') }}: {{ joinedFormatted }}
                    </span>
                </div>
            </div>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            <div class="rounded-lg border border-slate-800 bg-slate-900/40 p-5">
                <p class="text-xs tracking-wider text-slate-500 uppercase">
                    {{ t('profile.public.xp') }}
                </p>
                <p class="mt-1 text-3xl font-bold text-cyan-300">
                    {{ profile.xp_total.toLocaleString() }}
                </p>
            </div>
            <div class="rounded-lg border border-slate-800 bg-slate-900/40 p-5">
                <p class="text-xs tracking-wider text-slate-500 uppercase">
                    {{ t('profile.public.streak') }}
                </p>
                <p class="mt-1 text-3xl font-bold text-orange-300">
                    {{ profile.streak_count }}
                </p>
            </div>
        </div>
    </div>
</template>
