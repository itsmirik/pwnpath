<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
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
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';

const { t } = useI18n();
const page = usePage();
const user = computed(() => page.props.auth.user);

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Profile settings',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head :title="t('settings.profile.title')" />

    <h1 class="sr-only">{{ t('settings.profile.title') }}</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            :title="t('settings.profile.section')"
            :description="t('settings.profile.section_desc')"
        />

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="username">{{
                    t('settings.profile.username')
                }}</Label>
                <Input
                    id="username"
                    class="mt-1 block w-full"
                    :default-value="user.username"
                    readonly
                    disabled
                />
                <p class="text-xs text-muted-foreground">
                    {{ t('settings.profile.username_locked') }}
                </p>
            </div>

            <div class="grid gap-2">
                <Label for="display_name">{{
                    t('settings.profile.display_name')
                }}</Label>
                <Input
                    id="display_name"
                    class="mt-1 block w-full"
                    name="display_name"
                    :default-value="user.display_name"
                    required
                    autocomplete="nickname"
                />
                <InputError class="mt-2" :message="errors.display_name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">{{ t('settings.profile.email') }}</Label>
                <Input
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    name="email"
                    :default-value="user.email"
                    required
                    autocomplete="username"
                />
                <InputError class="mt-2" :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="bio">{{ t('settings.profile.bio') }}</Label>
                <textarea
                    id="bio"
                    name="bio"
                    :placeholder="t('settings.profile.bio_placeholder')"
                    class="mt-1 block min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                    maxlength="300"
                    :value="user.bio ?? ''"
                />
                <InputError class="mt-2" :message="errors.bio" />
            </div>

            <div class="grid gap-2">
                <Label for="avatar_color">{{
                    t('settings.profile.avatar_color')
                }}</Label>
                <input
                    id="avatar_color"
                    type="color"
                    name="avatar_color"
                    :value="user.avatar_color"
                    class="h-10 w-24 cursor-pointer rounded-md border border-input bg-transparent"
                />
                <InputError class="mt-2" :message="errors.avatar_color" />
            </div>

            <div class="grid gap-2">
                <Label for="country_code">{{
                    t('settings.profile.country_code')
                }}</Label>
                <Input
                    id="country_code"
                    class="mt-1 block w-24 uppercase"
                    name="country_code"
                    :default-value="user.country_code ?? ''"
                    maxlength="2"
                    placeholder="UZ"
                />
                <InputError class="mt-2" :message="errors.country_code" />
            </div>

            <div class="grid gap-2">
                <Label for="locale">{{ t('settings.profile.locale') }}</Label>
                <Select :default-value="user.locale" name="locale">
                    <SelectTrigger id="locale" class="w-48">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="ru">Русский</SelectItem>
                        <SelectItem value="uz">Oʻzbekcha</SelectItem>
                        <SelectItem value="en">English</SelectItem>
                    </SelectContent>
                </Select>
                <InputError class="mt-2" :message="errors.locale" />
            </div>

            <div v-if="page.props.mustVerifyEmail && !user.email_verified_at">
                <p class="-mt-4 text-sm text-muted-foreground">
                    {{ t('settings.profile.unverified') }}
                    <Link
                        :href="send()"
                        as="button"
                        class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                    >
                        {{ t('settings.profile.resend_verification') }}
                    </Link>
                </p>

                <div
                    v-if="page.props.status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600"
                >
                    {{ t('settings.profile.verification_sent') }}
                </div>
            </div>

            <div class="flex items-center gap-4">
                <Button
                    :disabled="processing"
                    data-test="update-profile-button"
                >
                    {{ t('settings.profile.save') }}
                </Button>
            </div>
        </Form>
    </div>

    <DeleteUser />
</template>
