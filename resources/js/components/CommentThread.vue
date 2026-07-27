<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { getInitials } from '@/composables/useInitials';

type Author = {
    username: string | null;
    display_name: string | null;
    avatar_color: string | null;
    url: string | null;
};

type CommentItem = {
    id: number;
    author: Author;
    content: string;
    created_at: string | null;
    is_own: boolean;
    can_report: boolean;
    has_reported: boolean;
    report_url: string;
    replies?: CommentItem[];
};

type Comments = {
    locked: boolean;
    can_post: boolean;
    post_url: string;
    items: CommentItem[];
};

const props = defineProps<{ comments: Comments }>();
const { t, locale } = useI18n();

const REPORT_REASONS = ['spoiler', 'spam', 'abuse', 'other'] as const;

const replyingTo = ref<number | null>(null);
const reportingId = ref<number | null>(null);

const topForm = useForm({ content: '', parent_id: null as number | null });
const replyForm = useForm({ content: '', parent_id: null as number | null });
const reportForm = useForm({ reason: REPORT_REASONS[0] as string });

function postTop(): void {
    if (!topForm.content.trim()) {
        return;
    }

    topForm
        .transform((data) => ({ ...data, parent_id: null }))
        .post(props.comments.post_url, {
            preserveScroll: true,
            onSuccess: () => topForm.reset(),
        });
}

function startReply(id: number): void {
    replyingTo.value = id;
    reportingId.value = null;
    replyForm.reset();
    replyForm.clearErrors();
}

function postReply(parentId: number): void {
    if (!replyForm.content.trim()) {
        return;
    }

    replyForm
        .transform((data) => ({ ...data, parent_id: parentId }))
        .post(props.comments.post_url, {
            preserveScroll: true,
            onSuccess: () => {
                replyForm.reset();
                replyingTo.value = null;
            },
        });
}

function startReport(id: number): void {
    reportingId.value = id;
    replyingTo.value = null;
    reportForm.reset();
}

function submitReport(url: string): void {
    reportForm.post(url, {
        preserveScroll: true,
        onSuccess: () => {
            reportingId.value = null;
            reportForm.reset();
        },
    });
}

function formatDate(iso: string | null): string {
    if (!iso) {
        return '';
    }

    return new Date(iso).toLocaleDateString(locale.value, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}
</script>

<template>
    <section class="mt-6 rounded-lg border border-border bg-card p-6">
        <h2 class="text-lg font-semibold text-foreground">
            {{ t('challenges.show.comments.heading') }}
        </h2>

        <!-- Solved-gate -->
        <div
            v-if="comments.locked"
            class="mt-3 rounded-md border border-border bg-muted/30 p-4 text-sm text-muted-foreground"
        >
            <p class="font-medium text-foreground">
                {{ t('challenges.show.comments.locked_title') }}
            </p>
            <p class="mt-1">
                {{ t('challenges.show.comments.locked_body') }}
            </p>
        </div>

        <template v-else>
            <!-- Top-level composer -->
            <form class="mt-4" @submit.prevent="postTop">
                <textarea
                    v-model="topForm.content"
                    :placeholder="t('challenges.show.comments.placeholder')"
                    rows="3"
                    maxlength="2000"
                    class="w-full resize-y rounded-md border border-border bg-background px-3 py-2 text-sm text-foreground focus:border-cyan-400 focus:outline-none"
                />
                <p
                    v-if="topForm.errors.content"
                    class="mt-1 text-sm text-rose-600 dark:text-rose-400"
                >
                    {{ topForm.errors.content }}
                </p>
                <div class="mt-2 flex justify-end">
                    <button
                        type="submit"
                        :disabled="
                            topForm.processing || !topForm.content.trim()
                        "
                        class="rounded-md bg-cyan-400 px-4 py-1.5 text-sm font-semibold text-slate-950 hover:bg-cyan-300 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {{
                            topForm.processing
                                ? t('challenges.show.comments.posting')
                                : t('challenges.show.comments.post')
                        }}
                    </button>
                </div>
            </form>

            <!-- Thread -->
            <ul v-if="comments.items.length" class="mt-6 space-y-5">
                <li
                    v-for="c in comments.items"
                    :key="c.id"
                    class="rounded-md border border-border bg-background/40 p-4"
                >
                    <!-- Top-level comment -->
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <span
                                class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-semibold text-slate-950"
                                :style="{
                                    backgroundColor:
                                        c.author.avatar_color ?? '#64748b',
                                }"
                            >
                                {{ getInitials(c.author.display_name ?? '?') }}
                            </span>
                            <div class="text-sm">
                                <component
                                    :is="c.author.url ? 'a' : 'span'"
                                    :href="c.author.url ?? undefined"
                                    class="font-medium text-foreground hover:text-cyan-600 dark:hover:text-cyan-300"
                                >
                                    {{ c.author.display_name ?? '—' }}
                                </component>
                                <span
                                    class="ml-2 text-xs text-muted-foreground"
                                >
                                    {{ formatDate(c.created_at) }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <p class="mt-2 text-sm whitespace-pre-line text-foreground">
                        {{ c.content }}
                    </p>

                    <!-- Actions -->
                    <div class="mt-2 flex items-center gap-4 text-xs">
                        <button
                            type="button"
                            class="text-muted-foreground hover:text-foreground"
                            @click="startReply(c.id)"
                        >
                            {{ t('challenges.show.comments.reply') }}
                        </button>
                        <span
                            v-if="c.has_reported"
                            class="text-muted-foreground"
                        >
                            {{ t('challenges.show.comments.reported') }}
                        </span>
                        <button
                            v-else-if="c.can_report"
                            type="button"
                            class="text-muted-foreground hover:text-rose-600 dark:hover:text-rose-400"
                            @click="startReport(c.id)"
                        >
                            {{ t('challenges.show.comments.report') }}
                        </button>
                    </div>

                    <!-- Report inline form -->
                    <form
                        v-if="reportingId === c.id"
                        class="mt-2 flex flex-wrap items-center gap-2"
                        @submit.prevent="submitReport(c.report_url)"
                    >
                        <select
                            v-model="reportForm.reason"
                            class="rounded-md border border-border bg-background px-2 py-1 text-xs text-foreground"
                        >
                            <option
                                v-for="reason in REPORT_REASONS"
                                :key="reason"
                                :value="reason"
                            >
                                {{
                                    t(
                                        `challenges.show.comments.reason.${reason}`,
                                    )
                                }}
                            </option>
                        </select>
                        <button
                            type="submit"
                            :disabled="reportForm.processing"
                            class="rounded-md border border-rose-500/40 bg-rose-500/10 px-2 py-1 text-xs text-rose-700 hover:bg-rose-500/20 disabled:opacity-50 dark:text-rose-300"
                        >
                            {{ t('challenges.show.comments.report_submit') }}
                        </button>
                        <button
                            type="button"
                            class="text-xs text-muted-foreground hover:text-foreground"
                            @click="reportingId = null"
                        >
                            {{ t('challenges.show.comments.cancel') }}
                        </button>
                    </form>

                    <!-- Replies -->
                    <ul
                        v-if="c.replies && c.replies.length"
                        class="mt-3 space-y-3 border-l border-border pl-4"
                    >
                        <li v-for="r in c.replies" :key="r.id">
                            <div class="flex items-center gap-2">
                                <span
                                    class="flex h-6 w-6 items-center justify-center rounded-full text-[10px] font-semibold text-slate-950"
                                    :style="{
                                        backgroundColor:
                                            r.author.avatar_color ?? '#64748b',
                                    }"
                                >
                                    {{
                                        getInitials(
                                            r.author.display_name ?? '?',
                                        )
                                    }}
                                </span>
                                <component
                                    :is="r.author.url ? 'a' : 'span'"
                                    :href="r.author.url ?? undefined"
                                    class="text-sm font-medium text-foreground hover:text-cyan-600 dark:hover:text-cyan-300"
                                >
                                    {{ r.author.display_name ?? '—' }}
                                </component>
                                <span class="text-xs text-muted-foreground">
                                    {{ formatDate(r.created_at) }}
                                </span>
                            </div>
                            <p
                                class="mt-1 text-sm whitespace-pre-line text-foreground"
                            >
                                {{ r.content }}
                            </p>
                            <div class="mt-1 text-xs">
                                <span
                                    v-if="r.has_reported"
                                    class="text-muted-foreground"
                                >
                                    {{ t('challenges.show.comments.reported') }}
                                </span>
                                <button
                                    v-else-if="r.can_report"
                                    type="button"
                                    class="text-muted-foreground hover:text-rose-600 dark:hover:text-rose-400"
                                    @click="startReport(r.id)"
                                >
                                    {{ t('challenges.show.comments.report') }}
                                </button>
                            </div>
                            <form
                                v-if="reportingId === r.id"
                                class="mt-2 flex flex-wrap items-center gap-2"
                                @submit.prevent="submitReport(r.report_url)"
                            >
                                <select
                                    v-model="reportForm.reason"
                                    class="rounded-md border border-border bg-background px-2 py-1 text-xs text-foreground"
                                >
                                    <option
                                        v-for="reason in REPORT_REASONS"
                                        :key="reason"
                                        :value="reason"
                                    >
                                        {{
                                            t(
                                                `challenges.show.comments.reason.${reason}`,
                                            )
                                        }}
                                    </option>
                                </select>
                                <button
                                    type="submit"
                                    :disabled="reportForm.processing"
                                    class="rounded-md border border-rose-500/40 bg-rose-500/10 px-2 py-1 text-xs text-rose-700 hover:bg-rose-500/20 disabled:opacity-50 dark:text-rose-300"
                                >
                                    {{
                                        t(
                                            'challenges.show.comments.report_submit',
                                        )
                                    }}
                                </button>
                                <button
                                    type="button"
                                    class="text-xs text-muted-foreground hover:text-foreground"
                                    @click="reportingId = null"
                                >
                                    {{ t('challenges.show.comments.cancel') }}
                                </button>
                            </form>
                        </li>
                    </ul>

                    <!-- Reply composer -->
                    <form
                        v-if="replyingTo === c.id"
                        class="mt-3 border-l border-border pl-4"
                        @submit.prevent="postReply(c.id)"
                    >
                        <textarea
                            v-model="replyForm.content"
                            :placeholder="
                                t('challenges.show.comments.reply_placeholder')
                            "
                            rows="2"
                            maxlength="2000"
                            class="w-full resize-y rounded-md border border-border bg-background px-3 py-2 text-sm text-foreground focus:border-cyan-400 focus:outline-none"
                        />
                        <p
                            v-if="replyForm.errors.content"
                            class="mt-1 text-sm text-rose-600 dark:text-rose-400"
                        >
                            {{ replyForm.errors.content }}
                        </p>
                        <div class="mt-2 flex items-center gap-3">
                            <button
                                type="submit"
                                :disabled="
                                    replyForm.processing ||
                                    !replyForm.content.trim()
                                "
                                class="rounded-md bg-cyan-400 px-3 py-1 text-xs font-semibold text-slate-950 hover:bg-cyan-300 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {{ t('challenges.show.comments.reply_post') }}
                            </button>
                            <button
                                type="button"
                                class="text-xs text-muted-foreground hover:text-foreground"
                                @click="replyingTo = null"
                            >
                                {{ t('challenges.show.comments.cancel') }}
                            </button>
                        </div>
                    </form>
                </li>
            </ul>
            <p v-else class="mt-6 text-sm text-muted-foreground">
                {{ t('challenges.show.comments.empty') }}
            </p>
        </template>
    </section>
</template>
