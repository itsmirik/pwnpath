<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { Locale } from '@/i18n';

const page = usePage();
const { locale } = useI18n();

const currentLocale = computed<Locale>(
    () => (page.props.locale as Locale) ?? (locale.value as Locale),
);

const localeOptions: { value: Locale; label: string }[] = [
    { value: 'ru', label: 'RU' },
    { value: 'uz', label: 'UZ' },
    { value: 'en', label: 'EN' },
];

function switchLocale(value: Locale) {
    if (value === currentLocale.value) {
        return;
    }

    router.post(
        '/locale',
        { locale: value },
        {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => {
                locale.value = value;
            },
        },
    );
}
</script>

<template>
    <div
        class="flex rounded-md border border-border bg-background/60 p-0.5 text-xs font-medium"
    >
        <button
            v-for="opt in localeOptions"
            :key="opt.value"
            type="button"
            :class="[
                'rounded px-2 py-1 transition',
                currentLocale === opt.value
                    ? 'bg-foreground text-background'
                    : 'text-muted-foreground hover:text-foreground',
            ]"
            :aria-pressed="currentLocale === opt.value"
            @click="switchLocale(opt.value)"
        >
            {{ opt.label }}
        </button>
    </div>
</template>
