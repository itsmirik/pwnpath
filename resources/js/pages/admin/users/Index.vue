<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Ban, RotateCcw } from '@lucide/vue';
import { reactive, ref } from 'vue';

type Row = {
    id: number;
    username: string;
    display_name: string;
    email: string;
    role: string;
    status: 'active' | 'suspended';
    xp_total: number;
    solves_count: number;
    country_code: string | null;
    ban_reason: string | null;
    created_at: string | null;
    banned_at: string | null;
    is_self: boolean;
    profile_url: string;
    role_url: string;
    ban_url: string;
    unban_url: string;
};

const props = defineProps<{
    users: {
        data: Row[];
        meta: { current_page: number; last_page: number; total: number };
        links: { prev: string | null; next: string | null };
    };
    filters: { q: string; role: string | null; status: string | null };
    options: { roles: string[]; statuses: string[] };
}>();

const state = reactive({
    q: props.filters.q,
    role: props.filters.role ?? '',
    status: props.filters.status ?? '',
});

let debounce: ReturnType<typeof setTimeout> | undefined;

function applyFilters(): void {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get(
            '/admin/users',
            {
                q: state.q || undefined,
                role: state.role || undefined,
                status: state.status || undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 250);
}

function changeRole(row: Row, event: Event): void {
    const role = (event.target as HTMLSelectElement).value;
    if (role === row.role) {
        return;
    }
    router.post(row.role_url, { role }, { preserveScroll: true });
}

const banningId = ref<number | null>(null);
const banReason = ref('');

function startBan(row: Row): void {
    banningId.value = row.id;
    banReason.value = '';
}

function confirmBan(row: Row): void {
    router.post(
        row.ban_url,
        { reason: banReason.value || null },
        {
            preserveScroll: true,
            onFinish: () => {
                banningId.value = null;
            },
        },
    );
}

function unban(row: Row): void {
    router.post(row.unban_url, {}, { preserveScroll: true });
}

const roleStyle: Record<string, string> = {
    admin: 'text-rose-500',
    moderator: 'text-violet-500',
    author: 'text-cyan-500',
    user: 'text-muted-foreground',
};
</script>

<template>
    <Head title="Users · Admin" />

    <div class="mx-auto w-full max-w-6xl px-6 py-8">
        <header class="mb-6">
            <h1 class="text-2xl font-bold tracking-tight">Users</h1>
            <p class="mt-1 text-sm text-muted-foreground">{{ users.meta.total }} total</p>
        </header>

        <div class="mb-6 grid gap-3 rounded-lg border border-border bg-card p-4 sm:grid-cols-3">
            <input
                v-model="state.q"
                type="search"
                placeholder="Search username / email…"
                class="rounded-md border border-border bg-background px-3 py-2 text-sm focus:border-cyan-400 focus:outline-none"
                @input="applyFilters"
            />
            <select
                v-model="state.role"
                class="rounded-md border border-border bg-background px-3 py-2 text-sm focus:border-cyan-400 focus:outline-none"
                @change="applyFilters"
            >
                <option value="">All roles</option>
                <option v-for="r in options.roles" :key="r" :value="r">{{ r }}</option>
            </select>
            <select
                v-model="state.status"
                class="rounded-md border border-border bg-background px-3 py-2 text-sm focus:border-cyan-400 focus:outline-none"
                @change="applyFilters"
            >
                <option value="">All statuses</option>
                <option v-for="s in options.statuses" :key="s" :value="s">{{ s }}</option>
            </select>
        </div>

        <div class="overflow-x-auto rounded-lg border border-border bg-card">
            <table class="w-full min-w-[760px] text-sm">
                <thead
                    class="border-b border-border text-left text-xs tracking-wide text-muted-foreground uppercase"
                >
                    <tr>
                        <th class="px-4 py-3 font-medium">User</th>
                        <th class="px-4 py-3 font-medium">Role</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">XP</th>
                        <th class="px-4 py-3 font-medium">Solves</th>
                        <th class="px-4 py-3 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-for="row in users.data" :key="row.id" class="hover:bg-accent/40">
                        <td class="px-4 py-3">
                            <Link :href="row.profile_url" class="font-medium hover:text-cyan-500">
                                {{ row.display_name }}
                            </Link>
                            <div class="text-xs text-muted-foreground">
                                @{{ row.username }} · {{ row.email }}
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <select
                                :value="row.role"
                                :disabled="row.is_self"
                                class="rounded-md border border-border bg-background px-2 py-1 text-xs font-medium capitalize focus:border-cyan-400 focus:outline-none disabled:opacity-50"
                                :class="roleStyle[row.role]"
                                @change="changeRole(row, $event)"
                            >
                                <option v-for="r in options.roles" :key="r" :value="r">
                                    {{ r }}
                                </option>
                            </select>
                        </td>
                        <td class="px-4 py-3">
                            <span
                                class="rounded-full border px-2 py-0.5 text-xs font-medium capitalize"
                                :class="
                                    row.status === 'suspended'
                                        ? 'border-rose-500/30 bg-rose-500/10 text-rose-500'
                                        : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-300'
                                "
                                :title="row.ban_reason ?? ''"
                            >
                                {{ row.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 tabular-nums">{{ row.xp_total }}</td>
                        <td class="px-4 py-3 tabular-nums">{{ row.solves_count }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <template v-if="banningId === row.id">
                                    <input
                                        v-model="banReason"
                                        type="text"
                                        placeholder="Reason (optional)"
                                        class="w-40 rounded-md border border-border bg-background px-2 py-1 text-xs focus:border-cyan-400 focus:outline-none"
                                    />
                                    <button
                                        type="button"
                                        class="rounded-md bg-rose-500 px-2 py-1 text-xs font-medium text-white hover:bg-rose-600"
                                        @click="confirmBan(row)"
                                    >
                                        Confirm
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-md border border-border px-2 py-1 text-xs hover:text-foreground"
                                        @click="banningId = null"
                                    >
                                        Cancel
                                    </button>
                                </template>
                                <template v-else>
                                    <button
                                        v-if="row.status === 'active' && !row.is_self && row.role !== 'admin'"
                                        type="button"
                                        title="Suspend"
                                        class="inline-flex items-center gap-1 rounded-md border border-border px-2 py-1 text-xs transition hover:border-rose-400/50 hover:text-rose-500"
                                        @click="startBan(row)"
                                    >
                                        <Ban class="h-3.5 w-3.5" /> Suspend
                                    </button>
                                    <button
                                        v-else-if="row.status === 'suspended'"
                                        type="button"
                                        title="Reinstate"
                                        class="inline-flex items-center gap-1 rounded-md border border-border px-2 py-1 text-xs transition hover:border-emerald-400/50 hover:text-emerald-500"
                                        @click="unban(row)"
                                    >
                                        <RotateCcw class="h-3.5 w-3.5" /> Reinstate
                                    </button>
                                    <span v-else class="text-xs text-muted-foreground">—</span>
                                </template>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav
            v-if="users.meta.last_page > 1"
            class="mt-6 flex items-center justify-between text-sm text-muted-foreground"
        >
            <Link
                v-if="users.links.prev"
                :href="users.links.prev"
                preserve-scroll
                class="rounded-md border border-border px-3 py-1.5 hover:text-foreground"
            >
                Previous
            </Link>
            <span v-else />
            <span>Page {{ users.meta.current_page }} of {{ users.meta.last_page }}</span>
            <Link
                v-if="users.links.next"
                :href="users.links.next"
                preserve-scroll
                class="rounded-md border border-border px-3 py-1.5 hover:text-foreground"
            >
                Next
            </Link>
            <span v-else />
        </nav>
    </div>
</template>
