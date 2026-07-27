<script setup lang="ts">
import { Crepe } from '@milkdown/crepe';
import { onBeforeUnmount, onMounted, useTemplateRef } from 'vue';
import '@milkdown/crepe/theme/common/style.css';
import '@milkdown/crepe/theme/frame.css';

const props = withDefaults(
    defineProps<{
        modelValue: string;
        uploadUrl: string;
        placeholder?: string;
    }>(),
    { placeholder: '' },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const host = useTemplateRef<HTMLDivElement>('host');
let crepe: Crepe | null = null;
let destroyed = false;

function xsrfToken(): string {
    return decodeURIComponent(
        document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
    );
}

async function uploadImage(file: File): Promise<string> {
    const body = new FormData();
    body.append('image', file);

    const res = await fetch(props.uploadUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-XSRF-TOKEN': xsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body,
    });

    if (!res.ok) {
        throw new Error('Image upload failed');
    }

    const data = (await res.json()) as { url: string };

    return data.url;
}

onMounted(async () => {
    if (!host.value) {
        return;
    }

    crepe = new Crepe({
        root: host.value,
        defaultValue: props.modelValue,
        featureConfigs: {
            [Crepe.Feature.Placeholder]: { text: props.placeholder },
            [Crepe.Feature.ImageBlock]: {
                onUpload: uploadImage,
                blockOnUpload: uploadImage,
                inlineOnUpload: uploadImage,
            },
        },
    });

    crepe.on((listener) => {
        listener.markdownUpdated((_ctx, markdown) => {
            emit('update:modelValue', markdown);
        });
    });

    await crepe.create();

    // Component may have unmounted while the async create was in flight.
    if (destroyed) {
        await crepe.destroy();
        crepe = null;
    }
});

onBeforeUnmount(() => {
    destroyed = true;
    void crepe?.destroy();
    crepe = null;
});
</script>

<template>
    <div
        ref="host"
        class="markdown-editor rounded-md border border-border bg-white text-slate-900"
    />
</template>

<style>
/* Keep the Crepe editor readable inside the app's dark chrome by pinning it
   to a light "paper" surface, and let it grow with content. */
.markdown-editor .milkdown {
    --crepe-color-background: #ffffff;
    min-height: 22rem;
    padding: 0.5rem 0;
    border-radius: 0.375rem;
}

.markdown-editor .milkdown .ProseMirror {
    min-height: 20rem;
    padding: 1rem 1.5rem;
    outline: none;
}
</style>
