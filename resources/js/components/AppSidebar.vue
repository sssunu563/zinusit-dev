<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
} from '@/components/ui/sidebar';
import { mainNavItems } from '@/constants/app-navigation';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const page = usePage();

const navItems = computed<NavItem[]>(() => {
    const authLogBadge = Number(page.props.sidebarBadges?.authLogs ?? 0);
    return mainNavItems.map((item) => {
        if (item.title === 'Log') {
            const badgeVal = authLogBadge > 0 ? String(authLogBadge) : undefined;
            return {
                ...item,
                badge: badgeVal,
                children: item.children?.map((child) => {
                    if (child.title === 'Auth Logs') {
                        return { ...child, badge: badgeVal };
                    }
                    return child;
                })
            };
        }
        return item;
    });
});
</script>

<template>
    <Sidebar
        collapsible="icon"
        variant="sidebar"
        class="border-r border-sidebar-border bg-sidebar"
    >
        <SidebarHeader class="px-3 py-3 group-data-[collapsible=icon]:px-2">
            <Link
                :href="dashboard()"
                class="block group-data-[collapsible=icon]:flex group-data-[collapsible=icon]:justify-center"
            >
                <AppLogo />
            </Link>
        </SidebarHeader>

        <SidebarContent class="px-3 py-1 group-data-[collapsible=icon]:px-2">
            <NavMain :items="navItems" />
        </SidebarContent>

        <SidebarFooter
            class="px-3 pt-2 pb-3 group-data-[collapsible=icon]:px-2"
        >
            <NavUser />
        </SidebarFooter>
    </Sidebar>
</template>
