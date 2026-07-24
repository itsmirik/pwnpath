<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { login, register } from '@/routes';

const { t } = useI18n();

const featureKeys = [
    'dynamic_flags',
    'modern_categories',
    'streaks_xp',
    'leaderboards',
    'writeups',
    'trilingual',
] as const;
</script>

<template>
    <Head :title="t('landing.hero.title')" />

    <section class="relative isolate overflow-hidden">
        <div
            class="absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_top,_rgba(34,211,238,0.15),transparent_60%)]"
        />
        <div class="mx-auto flex max-w-6xl flex-col gap-8 px-6 py-24 lg:py-32">
            <span
                class="w-fit rounded-full border border-cyan-400/30 bg-cyan-400/10 px-3 py-1 text-xs font-medium tracking-wider text-cyan-300 uppercase"
            >
                {{ t('landing.hero.eyebrow') }}
            </span>
            <h1
                class="max-w-3xl text-4xl leading-tight font-bold tracking-tight text-white sm:text-5xl lg:text-6xl"
            >
                {{ t('landing.hero.title') }}
            </h1>
            <p class="max-w-2xl text-lg text-slate-300">
                {{ t('landing.hero.subtitle') }}
            </p>
            <div class="flex flex-wrap gap-3">
                <Link
                    :href="register()"
                    class="rounded-md bg-cyan-400 px-5 py-2.5 text-sm font-semibold text-slate-950 hover:bg-cyan-300"
                >
                    {{ t('landing.hero.cta_primary') }}
                </Link>
                <Link
                    :href="login()"
                    class="rounded-md border border-slate-700 px-5 py-2.5 text-sm font-medium text-slate-200 hover:border-slate-500 hover:text-white"
                >
                    {{ t('landing.hero.cta_secondary') }}
                </Link>
            </div>
        </div>
    </section>

    <section class="border-t border-slate-800/70 bg-slate-950/60">
        <div class="mx-auto max-w-6xl px-6 py-20">
            <h2
                class="text-2xl font-semibold tracking-tight text-white sm:text-3xl"
            >
                {{ t('landing.features.heading') }}
            </h2>
            <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="key in featureKeys"
                    :key="key"
                    class="rounded-lg border border-slate-800 bg-slate-900/40 p-6 transition hover:border-cyan-400/40 hover:bg-slate-900/70"
                >
                    <h3 class="text-lg font-semibold text-white">
                        {{ t(`landing.features.items.${key}.title`) }}
                    </h3>
                    <p class="mt-2 text-sm text-slate-400">
                        {{ t(`landing.features.items.${key}.body`) }}
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="border-t border-slate-800/70">
        <div
            class="mx-auto flex max-w-4xl flex-col items-start gap-6 px-6 py-20 text-left sm:items-center sm:text-center"
        >
            <h2
                class="text-3xl font-bold tracking-tight text-white sm:text-4xl"
            >
                {{ t('landing.cta.heading') }}
            </h2>
            <p class="max-w-2xl text-slate-300">
                {{ t('landing.cta.body') }}
            </p>
            <Link
                :href="register()"
                class="rounded-md bg-cyan-400 px-6 py-3 text-sm font-semibold text-slate-950 hover:bg-cyan-300"
            >
                {{ t('landing.cta.button') }}
            </Link>
        </div>
    </section>
</template>
