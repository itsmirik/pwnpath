<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLogo from '@/components/AppLogo.vue';
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
import { dashboard, login, register } from '@/routes';

const page = usePage<{ auth: { user: { username: string } | null } }>();
const { t } = useI18n();
const year = new Date().getFullYear();
</script>

<template>
    <div class="flex min-h-screen flex-col bg-slate-950 text-slate-100">
        <header
            class="border-b border-slate-800/70 bg-slate-950/90 backdrop-blur"
        >
            <div
                class="mx-auto flex h-16 w-full max-w-6xl items-center justify-between px-6"
            >
                <Link
                    href="/"
                    class="flex items-center gap-2 text-slate-100 hover:text-white"
                >
                    <AppLogo class="h-7 w-7" />
                    <span class="font-semibold tracking-tight">{{
                        t('common.nav.brand')
                    }}</span>
                </Link>

                <nav
                    class="hidden items-center gap-4 text-sm text-slate-300 sm:flex"
                >
                    <Link href="/challenges" class="hover:text-white">
                        {{ t('common.nav.challenges') }}
                    </Link>
                    <Link href="/leaderboard" class="hover:text-white">
                        {{ t('common.nav.leaderboard') }}
                    </Link>
                </nav>

                <div class="flex items-center gap-2">
                    <LocaleSwitcher />

                    <template v-if="page.props.auth?.user">
                        <Link
                            :href="`/u/${page.props.auth.user.username}`"
                            class="hidden rounded-md px-3 py-1.5 text-sm text-slate-300 hover:text-white sm:inline-block"
                        >
                            {{ t('common.nav.profile') }}
                        </Link>
                        <Link
                            :href="dashboard()"
                            class="rounded-md border border-slate-700 px-3 py-1.5 text-sm text-slate-200 hover:border-slate-500 hover:text-white"
                        >
                            {{ t('common.nav.dashboard') }}
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="rounded-md px-3 py-1.5 text-sm text-slate-300 hover:text-white"
                        >
                            {{ t('common.nav.login') }}
                        </Link>
                        <Link
                            :href="register()"
                            class="rounded-md bg-cyan-400 px-3 py-1.5 text-sm font-medium text-slate-950 hover:bg-cyan-300"
                        >
                            {{ t('common.nav.signup') }}
                        </Link>
                    </template>
                </div>
            </div>
        </header>

        <main class="flex-1">
            <slot />
        </main>

        <footer class="border-t border-slate-800/70 bg-slate-950">
            <div
                class="mx-auto flex w-full max-w-6xl flex-col gap-4 px-6 py-8 text-sm text-slate-400 sm:flex-row sm:items-center sm:justify-between"
            >
                <p>{{ t('common.footer.tagline') }}</p>
                <nav class="flex flex-wrap gap-4">
                    <Link href="/terms" class="hover:text-slate-100">{{
                        t('common.footer.terms')
                    }}</Link>
                    <Link href="/privacy" class="hover:text-slate-100">{{
                        t('common.footer.privacy')
                    }}</Link>
                    <Link href="/contact" class="hover:text-slate-100">{{
                        t('common.footer.contact')
                    }}</Link>
                </nav>
                <p class="text-slate-500">
                    {{ t('common.footer.copyright', { year }) }}
                </p>
            </div>
        </footer>
    </div>
</template>
