<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import HCaptcha from '@/components/HCaptcha.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

const { t } = useI18n();
const page = usePage();
const initialLocale = (page.props.locale as 'ru' | 'uz' | 'en') ?? 'ru';

defineProps<{
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: 'auth.forms.register.title',
        description: 'auth.forms.register.description',
    },
});
</script>

<template>
    <Head :title="t('auth.forms.register.title')" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="username">{{
                    t('auth.forms.register.username')
                }}</Label>
                <Input
                    id="username"
                    type="text"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="username"
                    name="username"
                    placeholder="aziz_dev"
                />
                <p class="text-xs text-muted-foreground">
                    {{ t('auth.forms.register.username_hint') }}
                </p>
                <InputError :message="errors.username" />
            </div>

            <div class="grid gap-2">
                <Label for="display_name">{{
                    t('auth.forms.register.display_name')
                }}</Label>
                <Input
                    id="display_name"
                    type="text"
                    required
                    :tabindex="2"
                    autocomplete="nickname"
                    name="display_name"
                    placeholder="Aziz"
                />
                <p class="text-xs text-muted-foreground">
                    {{ t('auth.forms.register.display_name_hint') }}
                </p>
                <InputError :message="errors.display_name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">{{ t('auth.forms.register.email') }}</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    :tabindex="3"
                    autocomplete="email"
                    name="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="locale">{{
                    t('auth.forms.register.locale')
                }}</Label>
                <Select :default-value="initialLocale" name="locale">
                    <SelectTrigger id="locale" :tabindex="4">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="ru">Русский</SelectItem>
                        <SelectItem value="uz">Oʻzbekcha</SelectItem>
                        <SelectItem value="en">English</SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="errors.locale" />
            </div>

            <div class="grid gap-2">
                <Label for="password">{{
                    t('auth.forms.register.password')
                }}</Label>
                <PasswordInput
                    id="password"
                    required
                    :tabindex="5"
                    autocomplete="new-password"
                    name="password"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">{{
                    t('auth.forms.register.password_confirm')
                }}</Label>
                <PasswordInput
                    id="password_confirmation"
                    required
                    :tabindex="6"
                    autocomplete="new-password"
                    name="password_confirmation"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <HCaptcha />
            <InputError :message="errors['h-captcha-response']" />

            <Button
                type="submit"
                class="mt-2 w-full"
                :tabindex="7"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                {{ t('auth.forms.register.submit') }}
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            {{ t('auth.forms.register.have_account') }}
            <TextLink
                :href="login()"
                class="underline underline-offset-4"
                :tabindex="8"
                >{{ t('auth.forms.register.log_in') }}</TextLink
            >
        </div>
    </Form>
</template>
