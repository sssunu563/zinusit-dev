<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import { onBeforeUnmount, ref, watch } from 'vue';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

const props = defineProps<{ items: NavItem[] }>();
const { currentUrl, isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
const { state } = useSidebar();

const expandedGroupTitle = ref<string | null>(null);
const openFlyout = ref<NavItem | null>(null);
const flyoutPosition = ref({ top: 0, left: 0 });
const itemElements = new Map<string, HTMLElement>();
let closeFlyoutTimer: ReturnType<typeof setTimeout> | null = null;

const hasChildren = (item: NavItem) => Boolean(item.children?.length);
const findActiveGroupTitle = () =>
    props.items.find(
        (item) =>
            hasChildren(item) &&
            item.children?.some((child) => isCurrentOrParentUrl(child.href)),
    )?.title ?? null;

const isItemActive = (item: NavItem) =>
    Boolean(
        (!hasChildren(item) && isCurrentUrl(item.href)) ||
        item.children?.some((child) => isCurrentOrParentUrl(child.href)),
    );
const isSubItemActive = (item: NavItem) => isCurrentUrl(item.href);
const isGroupExpanded = (item: NavItem) =>
    hasChildren(item) && expandedGroupTitle.value === item.title;

const setItemElement = (title: string, element: Element | null) => {
    if (element instanceof HTMLElement) {
        itemElements.set(title, element);
        return;
    }
    itemElements.delete(title);
};

const cancelFlyoutClose = () => {
    if (!closeFlyoutTimer) return;
    clearTimeout(closeFlyoutTimer);
    closeFlyoutTimer = null;
};
const closeFlyout = () => {
    cancelFlyoutClose();
    openFlyout.value = null;
};
const updateFlyoutPosition = (title: string) => {
    const el = itemElements.get(title);
    if (!el) return;
    const rect = el.getBoundingClientRect();
    flyoutPosition.value = { top: rect.top, left: rect.right + 8 };
};
const showFlyout = (item: NavItem) => {
    cancelFlyoutClose();
    updateFlyoutPosition(item.title);
    openFlyout.value = item;
};
const scheduleFlyoutClose = (title: string) => {
    cancelFlyoutClose();
    closeFlyoutTimer = setTimeout(() => {
        if (openFlyout.value?.title === title) openFlyout.value = null;
    }, 120);
};
const handleParentClick = (item: NavItem, event: MouseEvent) => {
    event.preventDefault();
    if (state.value === 'collapsed') {
        showFlyout(item);
        return;
    }
    closeFlyout();
    expandedGroupTitle.value =
        expandedGroupTitle.value === item.title ? null : item.title;
};
const handleLeafClick = () => closeFlyout();
const handleWindowChange = () => {
    if (state.value !== 'collapsed' || !openFlyout.value) return;
    updateFlyoutPosition(openFlyout.value.title);
};

watch(
    currentUrl,
    () => {
        expandedGroupTitle.value = findActiveGroupTitle();
    },
    { immediate: true },
);
watch(state, (s) => {
    if (s !== 'collapsed') closeFlyout();
});

if (typeof window !== 'undefined') {
    window.addEventListener('resize', handleWindowChange);
    window.addEventListener('scroll', handleWindowChange, true);
}
onBeforeUnmount(() => {
    closeFlyout();
    if (typeof window !== 'undefined') {
        window.removeEventListener('resize', handleWindowChange);
        window.removeEventListener('scroll', handleWindowChange, true);
    }
});
</script>

<template>
    <SidebarGroup class="px-0 py-0">
        <SidebarGroupLabel
            class="mb-1 px-3 text-[10px] font-black tracking-[0.2em] text-slate-400 uppercase group-data-[collapsible=icon]:hidden"
        >
            Menu Utama
        </SidebarGroupLabel>

        <SidebarMenu class="gap-0.5 px-1">
            <SidebarMenuItem v-for="item in props.items" :key="item.title">
                <!-- Parent with children -->
                <div
                    v-if="hasChildren(item)"
                    :ref="
                        (el) => setItemElement(item.title, el as Element | null)
                    "
                    class="relative"
                    @mouseenter="
                        state === 'collapsed' ? showFlyout(item) : undefined
                    "
                    @mouseleave="
                        state === 'collapsed'
                            ? scheduleFlyoutClose(item.title)
                            : undefined
                    "
                >
                    <SidebarMenuButton
                        :class="[
                            'h-9 w-full rounded-lg border px-3 text-sm font-medium transition-all duration-150 group-data-[collapsible=icon]:mx-auto group-data-[collapsible=icon]:h-9 group-data-[collapsible=icon]:w-9 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-0',
                            isItemActive(item)
                                ? 'border-primary/20 bg-primary/10 text-primary'
                                : 'border-transparent text-muted-foreground transition-colors hover:bg-primary/5 hover:text-primary',
                        ]"
                        @click="handleParentClick(item, $event)"
                    >
                        <component :is="item.icon" class="size-4 shrink-0" />
                        <span
                            class="truncate group-data-[collapsible=icon]:hidden"
                            >{{ item.title }}</span
                        >
                        <SidebarMenuBadge
                            v-if="item.badge"
                            class="right-8 h-4 min-w-4 rounded-full px-1 text-[10px] font-bold group-data-[collapsible=icon]:hidden"
                            :class="
                                isItemActive(item)
                                    ? 'bg-primary text-white'
                                    : 'bg-muted text-muted-foreground'
                            "
                        >
                            {{ item.badge }}
                        </SidebarMenuBadge>
                        <ChevronDown
                            class="ml-auto size-3.5 shrink-0 text-muted-foreground transition-transform group-data-[collapsible=icon]:hidden"
                            :class="isGroupExpanded(item) ? 'rotate-180' : ''"
                        />
                    </SidebarMenuButton>
                </div>

                <!-- Leaf item -->
                <SidebarMenuButton
                    v-else
                    :class="[
                        'h-9 w-full rounded-lg border px-3 text-sm font-medium transition-all duration-150 group-data-[collapsible=icon]:mx-auto group-data-[collapsible=icon]:h-9 group-data-[collapsible=icon]:w-9 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-0',
                        isItemActive(item)
                            ? 'border-primary/20 bg-primary/10 text-primary'
                            : 'border-transparent text-muted-foreground transition-colors hover:bg-primary/5 hover:text-primary',
                    ]"
                    as-child
                    :is-active="isItemActive(item)"
                    :tooltip="item.title"
                >
                    <Link :href="item.href" @click="handleLeafClick()">
                        <component :is="item.icon" class="size-4 shrink-0" />
                        <span
                            class="truncate group-data-[collapsible=icon]:hidden"
                            >{{ item.title }}</span
                        >
                        <SidebarMenuBadge
                            v-if="item.badge"
                            class="right-2.5 h-4 min-w-4 rounded-full px-1 text-[10px] font-bold group-data-[collapsible=icon]:hidden"
                            :class="
                                isItemActive(item)
                                    ? 'bg-primary text-white'
                                    : 'bg-muted text-muted-foreground'
                            "
                        >
                            {{ item.badge }}
                        </SidebarMenuBadge>
                    </Link>
                </SidebarMenuButton>

                <!-- Sub items -->
                <Transition name="app-collapse">
                    <div
                        v-if="hasChildren(item) && isGroupExpanded(item)"
                        class="overflow-hidden group-data-[collapsible=icon]:hidden"
                    >
                        <SidebarMenuSub
                            class="mt-0.5 ml-3 space-y-0.5 border-l border-border pl-3"
                        >
                            <SidebarMenuSubItem
                                v-for="child in item.children"
                                :key="`${item.title}-${child.title}`"
                            >
                                <SidebarMenuSubButton
                                    class="h-8 rounded-md"
                                    as-child
                                    :is-active="isSubItemActive(child)"
                                >
                                    <Link
                                        :href="child.href"
                                        :class="[
                                            'flex items-center gap-2 rounded-md px-2.5 py-1.5 text-[13px] transition-colors',
                                            isSubItemActive(child)
                                                ? 'bg-primary/10 font-black text-primary'
                                                : 'text-muted-foreground transition-colors hover:bg-primary/5 hover:text-primary',
                                        ]"
                                    >
                                        <component
                                            v-if="child.icon"
                                            :is="child.icon"
                                            class="size-3.5 shrink-0"
                                        />
                                        <span class="truncate">{{
                                            child.title
                                        }}</span>
                                    </Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                        </SidebarMenuSub>
                    </div>
                </Transition>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>

    <!-- Flyout for collapsed sidebar -->
    <teleport to="body">
        <div
            v-if="
                state === 'collapsed' &&
                openFlyout &&
                openFlyout.children?.length
            "
            class="fixed z-[70] w-52 rounded-xl border border-border bg-card p-1.5 shadow-[0_16px_32px_-8px_rgba(0,0,0,0.1)]"
            :style="{
                top: `${flyoutPosition.top}px`,
                left: `${flyoutPosition.left}px`,
            }"
            @mouseenter="cancelFlyoutClose()"
            @mouseleave="scheduleFlyoutClose(openFlyout.title)"
        >
            <div class="px-2.5 pt-1 pb-1.5">
                <p
                    class="text-[10px] font-bold tracking-[0.2em] text-muted-foreground uppercase"
                >
                    {{ openFlyout.title }}
                </p>
            </div>
            <div class="space-y-0.5">
                <Link
                    v-for="child in openFlyout.children"
                    :key="`${openFlyout.title}-${child.title}-flyout`"
                    :href="child.href"
                    :class="[
                        'flex items-center gap-2 rounded-lg px-2.5 py-2 text-sm font-medium transition-colors',
                        isSubItemActive(child)
                            ? 'bg-primary/10 text-primary'
                            : 'text-muted-foreground transition-colors hover:bg-primary/5 hover:text-primary',
                    ]"
                    @click="closeFlyout()"
                >
                    <component
                        v-if="child.icon"
                        :is="child.icon"
                        class="size-3.5 shrink-0 text-muted-foreground"
                    />
                    <span class="truncate">{{ child.title }}</span>
                </Link>
            </div>
        </div>
    </teleport>
</template>
