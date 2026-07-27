<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Languages } from '@lucide/vue';
import type { AcceptableValue } from 'reka-ui';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Locale } from '@/i18n';

const page = usePage();
const { locale } = useI18n();

const currentLocale = computed<Locale>(
    () => (page.props.locale as Locale) ?? (locale.value as Locale),
);

const localeOptions: { value: Locale; label: string }[] = [
    { value: 'en', label: 'English' },
    { value: 'ru', label: 'Русский' },
    { value: 'uz', label: "O'zbekcha" },
];

function switchLocale(value: AcceptableValue) {
    if (typeof value !== 'string' || value === currentLocale.value) {
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
    <Select :model-value="currentLocale" @update:model-value="switchLocale">
        <SelectTrigger size="sm" class="gap-1.5" aria-label="Language">
            <Languages class="size-4 opacity-70" />
            <SelectValue />
        </SelectTrigger>
        <SelectContent align="end">
            <SelectItem
                v-for="opt in localeOptions"
                :key="opt.value"
                :value="opt.value"
            >
                {{ opt.label }}
            </SelectItem>
        </SelectContent>
    </Select>
</template>
