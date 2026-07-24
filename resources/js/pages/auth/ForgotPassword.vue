<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import HCaptcha from '@/components/HCaptcha.vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

const { t } = useI18n();

defineOptions({
    layout: {
        title: 'auth.forms.forgot.title',
        description: 'auth.forms.forgot.description',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head :title="t('auth.forms.forgot.title')" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <div class="space-y-6">
        <Form
            v-bind="email.form()"
            v-slot="{ errors, processing }"
            class="space-y-4"
        >
            <div class="grid gap-2">
                <Label for="email">{{ t('auth.forms.forgot.email') }}</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    autocomplete="off"
                    autofocus
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <HCaptcha />
            <InputError :message="errors['h-captcha-response']" />

            <div class="my-6 flex items-center justify-start">
                <Button
                    class="w-full"
                    :disabled="processing"
                    data-test="email-password-reset-link-button"
                >
                    <Spinner v-if="processing" />
                    {{ t('auth.forms.forgot.submit') }}
                </Button>
            </div>
        </Form>

        <div class="space-x-1 text-center text-sm text-muted-foreground">
            <TextLink :href="login()">{{
                t('auth.forms.forgot.back')
            }}</TextLink>
        </div>
    </div>
</template>
