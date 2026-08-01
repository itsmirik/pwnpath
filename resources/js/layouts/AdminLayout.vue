<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Flag,
    FileText,
    LayoutDashboard,
    ScrollText,
    ShieldAlert,
    Users,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { computed } from 'vue';
import { Toaster } from '@/components/ui/sonner';

type NavLink = {
    label: string;
    href: string;
    icon: LucideIcon;
    show: boolean;
};

const page = usePage();
const user = computed(() => page.props.auth.user);
const can = computed(() => page.props.admin?.can);

const links = computed<NavLink[]>(() =>
    [
        {
            label: 'Dashboard',
            href: '/admin',
            icon: LayoutDashboard,
            show: true,
        },
        {
            label: 'Challenges',
            href: '/admin/challenges',
            icon: Flag,
            show: !!can.value?.author,
        },
        {
            label: 'Writeups',
            href: '/admin/writeups',
            icon: FileText,
            show: !!can.value?.moderate,
        },
        {
            label: 'Reports',
            href: '/admin/reports',
            icon: ShieldAlert,
            show: !!can.value?.moderate,
        },
        {
            label: 'Users',
            href: '/admin/users',
            icon: Users,
            show: !!can.value?.manage_users,
        },
        {
            label: 'Audit log',
            href: '/admin/audit',
            icon: ScrollText,
            show: !!can.value?.view_audit,
        },
    ].filter((link) => link.show),
);

const currentPath = computed(() => {
    const raw = page.url ?? '/admin';
    const q = raw.indexOf('?');

    return q === -1 ? raw : raw.slice(0, q);
});

function isActive(href: string): boolean {
    if (href === '/admin') {
        return currentPath.value === '/admin';
    }

    return currentPath.value.startsWith(href);
}
</script>

<template>
    <div class="flex min-h-screen bg-background text-foreground">
        <aside
            class="sticky top-0 flex h-screen w-60 shrink-0 flex-col border-r border-border bg-card"
        >
            <div
                class="flex h-14 items-center gap-2 border-b border-border px-4"
            >
                <Flag class="h-5 w-5 text-cyan-400" />
                <span class="font-semibold tracking-tight">PwnPath Admin</span>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto p-3">
                <Link
                    v-for="link in links"
                    :key="link.href"
                    :href="link.href"
                    class="flex items-center gap-3 rounded-md px-3 py-2 text-sm transition"
                    :class="
                        isActive(link.href)
                            ? 'bg-accent font-medium text-foreground'
                            : 'text-muted-foreground hover:bg-accent hover:text-foreground'
                    "
                >
                    <component :is="link.icon" class="h-4 w-4 shrink-0" />
                    {{ link.label }}
                </Link>
            </nav>

            <div class="border-t border-border p-3">
                <Link
                    href="/"
                    class="flex items-center gap-2 rounded-md px-3 py-2 text-sm text-muted-foreground transition hover:bg-accent hover:text-foreground"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Back to site
                </Link>
                <div class="mt-2 px-3 text-xs text-muted-foreground">
                    {{ user.display_name }} ·
                    <span class="uppercase">{{ user.role }}</span>
                </div>
            </div>
        </aside>

        <main class="min-w-0 flex-1">
            <slot />
        </main>

        <Toaster />
    </div>
</template>
