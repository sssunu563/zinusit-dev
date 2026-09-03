<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Check, ChevronDown } from 'lucide-vue-next';
import {
    NavigationMenu,
    NavigationMenuContent,
    NavigationMenuItem,
    NavigationMenuLink,
    NavigationMenuList,
    NavigationMenuTrigger,
    navigationMenuTriggerStyle,
} from '@/components/ui/navigation-menu';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { mainNavItems } from '@/constants/app-navigation';

const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

const activeItemStyles =
    'border border-primary/30 bg-primary/10 text-primary shadow-sm';

const activeChildItemStyles = 'bg-primary/10 font-medium text-primary';

const isItemActive = (item: (typeof mainNavItems)[number]) =>
    Boolean(
        isCurrentUrl(item.href) ||
        item.children?.some((child) => isCurrentOrParentUrl(child.href)),
    );

const isChildActive = (href: (typeof mainNavItems)[number]['href']) =>
    isCurrentUrl(href);
</script>

<template>
    <div class="hidden h-full lg:flex lg:flex-1">
        <NavigationMenu class="ml-8 flex h-full items-stretch">
            <NavigationMenuList
                class="flex h-full items-stretch space-x-2 rounded-full border border-border bg-card/60 px-2 py-1 shadow-lg"
            >
                <NavigationMenuItem
                    v-for="(item, index) in mainNavItems"
                    :key="index"
                    class="relative flex h-full items-center"
                >
                    <template v-if="item.children?.length">
                        <NavigationMenuTrigger
                            :class="[
                                isItemActive(item) ? activeItemStyles : null,
                                'h-10 gap-2 rounded-full px-3.5 text-muted-foreground hover:bg-card hover:text-foreground',
                            ]"
                        >
                            <component
                                v-if="item.icon"
                                :is="item.icon"
                                class="mr-2 h-4 w-4"
                            />
                            <span>{{ item.title }}</span>
                            <ChevronDown
                                class="h-3.5 w-3.5 shrink-0 text-slate-500 transition duration-200 group-data-[state=open]:rotate-180"
                            />
                        </NavigationMenuTrigger>
                        <NavigationMenuContent
                            class="duration-200 data-[motion=from-end]:slide-in-from-right-2 data-[motion=from-start]:slide-in-from-left-2 data-[motion=to-end]:slide-out-to-right-2 data-[motion=to-start]:slide-out-to-left-2 data-[motion^=from-]:animate-in data-[motion^=from-]:fade-in-0 data-[motion^=to-]:animate-out data-[motion^=to-]:fade-out-0"
                        >
                            <div
                                class="w-[320px] rounded-[24px] border border-border bg-popover p-2 shadow-xl backdrop-blur-xl"
                            >
                                <div class="space-y-1">
                                    <NavigationMenuLink
                                        v-for="child in item.children"
                                        :key="`${item.title}-${child.title}`"
                                        as-child
                                    >
                                        <Link
                                            :href="child.href"
                                            :class="[
                                                'flex items-center gap-3 rounded-2xl px-3 py-3 text-sm text-muted-foreground transition hover:bg-card hover:text-foreground',
                                                isChildActive(child.href)
                                                    ? activeChildItemStyles
                                                    : null,
                                            ]"
                                        >
                                            <component
                                                v-if="child.icon"
                                                :is="child.icon"
                                                class="h-4 w-4"
                                            />
                                            <span class="flex-1">{{
                                                child.title
                                            }}</span>
                                            <Check
                                                v-if="isChildActive(child.href)"
                                                class="h-4 w-4 text-slate-500"
                                            />
                                        </Link>
                                    </NavigationMenuLink>
                                </div>
                            </div>
                        </NavigationMenuContent>
                    </template>
                    <Link
                        v-else
                        :class="[
                            navigationMenuTriggerStyle(),
                            isItemActive(item) ? activeItemStyles : null,
                            'h-10 cursor-pointer rounded-full px-3.5 text-muted-foreground hover:bg-card hover:text-foreground',
                        ]"
                        :href="item.href"
                    >
                        <component
                            v-if="item.icon"
                            :is="item.icon"
                            class="mr-2 h-4 w-4"
                        />
                        {{ item.title }}
                    </Link>
                </NavigationMenuItem>
            </NavigationMenuList>
        </NavigationMenu>
    </div>
</template>
