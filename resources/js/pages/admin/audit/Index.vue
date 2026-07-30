<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

type Log = {
    id: number;
    action: string;
    actor: string | null;
    entity_type: string | null;
    entity_id: number | null;
    meta: Record<string, unknown> | null;
    ip_address: string | null;
    created_at: string | null;
};

const props = defineProps<{
    logs: {
        data: Log[];
        meta: { current_page: number; last_page: number; total: number };
        links: { prev: string | null; next: string | null };
    };
    filters: {
        action: string | null;
        actor: string | null;
        from: string | null;
        to: string | null;
    };
    options: { actions: string[] };
}>();

const state = reactive({
    action: props.filters.action ?? '',
    actor: props.filters.actor ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});

let debounce: ReturnType<typeof setTimeout> | undefined;

function applyFilters(): void {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get(
            '/admin/audit',
            {
                action: state.action || undefined,
                actor: state.actor || undefined,
                from: state.from || undefined,
                to: state.to || undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
}

function formatTime(iso: string | null): string {
    return iso ? new Date(iso).toLocaleString() : '';
}

function metaText(meta: Record<string, unknown> | null): string {
    if (!meta || Object.keys(meta).length === 0) {
        return '';
    }

    return JSON.stringify(meta);
}
</script>

<template>
    <Head title="Audit log · Admin" />

    <div class="mx-auto w-full max-w-6xl px-6 py-8">
        <header class="mb-6">
            <h1 class="text-2xl font-bold tracking-tight">Audit log</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                {{ logs.meta.total }} entries
            </p>
        </header>

        <div
            class="mb-6 grid gap-3 rounded-lg border border-border bg-card p-4 sm:grid-cols-4"
        >
            <select
                v-model="state.action"
                class="rounded-md border border-border bg-background px-3 py-2 text-sm focus:border-cyan-400 focus:outline-none"
                @change="applyFilters"
            >
                <option value="">All actions</option>
                <option v-for="a in options.actions" :key="a" :value="a">
                    {{ a }}
                </option>
            </select>
            <input
                v-model="state.actor"
                type="search"
                placeholder="Actor username…"
                class="rounded-md border border-border bg-background px-3 py-2 text-sm focus:border-cyan-400 focus:outline-none"
                @input="applyFilters"
            />
            <label
                class="flex items-center gap-2 text-xs text-muted-foreground"
            >
                From
                <input
                    v-model="state.from"
                    type="date"
                    class="flex-1 rounded-md border border-border bg-background px-2 py-2 text-sm focus:border-cyan-400 focus:outline-none"
                    @change="applyFilters"
                />
            </label>
            <label
                class="flex items-center gap-2 text-xs text-muted-foreground"
            >
                To
                <input
                    v-model="state.to"
                    type="date"
                    class="flex-1 rounded-md border border-border bg-background px-2 py-2 text-sm focus:border-cyan-400 focus:outline-none"
                    @change="applyFilters"
                />
            </label>
        </div>

        <div
            v-if="logs.data.length === 0"
            class="rounded-lg border border-border bg-card p-10 text-center text-muted-foreground"
        >
            No audit entries match these filters.
        </div>

        <div
            v-else
            class="overflow-x-auto rounded-lg border border-border bg-card"
        >
            <table class="w-full min-w-[820px] text-sm">
                <thead
                    class="border-b border-border text-left text-xs tracking-wide text-muted-foreground uppercase"
                >
                    <tr>
                        <th class="px-4 py-3 font-medium">Time</th>
                        <th class="px-4 py-3 font-medium">Actor</th>
                        <th class="px-4 py-3 font-medium">Action</th>
                        <th class="px-4 py-3 font-medium">Entity</th>
                        <th class="px-4 py-3 font-medium">IP</th>
                        <th class="px-4 py-3 font-medium">Meta</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr
                        v-for="log in logs.data"
                        :key="log.id"
                        class="hover:bg-accent/40"
                    >
                        <td
                            class="px-4 py-3 whitespace-nowrap text-muted-foreground"
                        >
                            {{ formatTime(log.created_at) }}
                        </td>
                        <td class="px-4 py-3">{{ log.actor ?? 'system' }}</td>
                        <td class="px-4 py-3 font-mono text-xs">
                            {{ log.action }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            <span v-if="log.entity_type">
                                {{ log.entity_type }}#{{ log.entity_id }}
                            </span>
                            <span v-else>—</span>
                        </td>
                        <td
                            class="px-4 py-3 font-mono text-xs text-muted-foreground"
                        >
                            {{ log.ip_address ?? '—' }}
                        </td>
                        <td class="max-w-xs px-4 py-3">
                            <span
                                class="block truncate font-mono text-xs text-muted-foreground"
                                :title="metaText(log.meta)"
                            >
                                {{ metaText(log.meta) || '—' }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav
            v-if="logs.meta.last_page > 1"
            class="mt-6 flex items-center justify-between text-sm text-muted-foreground"
        >
            <Link
                v-if="logs.links.prev"
                :href="logs.links.prev"
                preserve-scroll
                class="rounded-md border border-border px-3 py-1.5 hover:text-foreground"
            >
                Previous
            </Link>
            <span v-else />
            <span
                >Page {{ logs.meta.current_page }} of
                {{ logs.meta.last_page }}</span
            >
            <Link
                v-if="logs.links.next"
                :href="logs.links.next"
                preserve-scroll
                class="rounded-md border border-border px-3 py-1.5 hover:text-foreground"
            >
                Next
            </Link>
            <span v-else />
        </nav>
    </div>
</template>
