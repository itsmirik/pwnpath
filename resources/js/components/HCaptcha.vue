<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const HCAPTCHA_SCRIPT = 'https://js.hcaptcha.com/1/api.js?render=explicit';

const page = usePage();
const container = ref<HTMLDivElement | null>(null);
const widgetId = ref<string | null>(null);

const sitekey = computed<string | null>(
    () => page.props.hcaptcha?.sitekey ?? null,
);

type HCaptchaApi = {
    render: (
        el: HTMLElement,
        opts: { sitekey: string; theme?: string },
    ) => string;
    remove: (id: string) => void;
};

declare global {
    interface Window {
        hcaptcha?: HCaptchaApi;
        __hcaptchaLoading?: Promise<void>;
    }
}

function loadScript(): Promise<void> {
    if (typeof window === 'undefined') {
        return Promise.resolve();
    }

    if (window.hcaptcha) {
        return Promise.resolve();
    }

    if (window.__hcaptchaLoading) {
        return window.__hcaptchaLoading;
    }

    window.__hcaptchaLoading = new Promise<void>((resolve, reject) => {
        const script = document.createElement('script');
        script.src = HCAPTCHA_SCRIPT;
        script.async = true;
        script.defer = true;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Failed to load hCaptcha'));
        document.head.appendChild(script);
    });

    return window.__hcaptchaLoading;
}

async function waitForHCaptcha(): Promise<HCaptchaApi | null> {
    await loadScript();

    for (let i = 0; i < 40 && !window.hcaptcha; i++) {
        await new Promise((r) => setTimeout(r, 50));
    }

    return window.hcaptcha ?? null;
}

onMounted(async () => {
    if (!sitekey.value || !container.value) {
        return;
    }

    const api = await waitForHCaptcha();

    if (!api || !container.value) {
        return;
    }

    widgetId.value = api.render(container.value, {
        sitekey: sitekey.value,
        theme: 'dark',
    });
});

onBeforeUnmount(() => {
    if (widgetId.value && window.hcaptcha) {
        try {
            window.hcaptcha.remove(widgetId.value);
        } catch {
            // ignore
        }
    }
});
</script>

<template>
    <div v-if="sitekey" class="flex justify-center">
        <div ref="container" />
    </div>
    <input v-else type="hidden" name="h-captcha-response" value="dev-bypass" />
</template>
