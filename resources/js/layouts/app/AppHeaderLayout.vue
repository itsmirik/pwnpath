<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppContent from '@/components/AppContent.vue';
import AppHeader from '@/components/AppHeader.vue';
import AppShell from '@/components/AppShell.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const { t } = useI18n();
const year = new Date().getFullYear();
</script>

<template>
    <AppShell variant="header">
        <AppHeader :breadcrumbs="breadcrumbs" />
        <AppContent variant="header" class="flex-1">
            <slot />
        </AppContent>

        <footer class="mt-8 border-t border-sidebar-border/70">
            <div
                class="mx-auto flex w-full max-w-7xl flex-col gap-4 px-4 py-8 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between"
            >
                <p>{{ t('common.footer.tagline') }}</p>
                <nav class="flex flex-wrap gap-4">
                    <Link href="/terms" class="hover:text-foreground">{{
                        t('common.footer.terms')
                    }}</Link>
                    <Link href="/privacy" class="hover:text-foreground">{{
                        t('common.footer.privacy')
                    }}</Link>
                    <Link href="/contact" class="hover:text-foreground">{{
                        t('common.footer.contact')
                    }}</Link>
                </nav>
                <p>{{ t('common.footer.copyright', { year }) }}</p>
            </div>
        </footer>

        <Toaster />
    </AppShell>
</template>
