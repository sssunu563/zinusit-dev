<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronDown, Menu } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { mainNavItems } from '@/constants/app-navigation';
import { toUrl } from '@/lib/utils';
import { rightNavItems } from './appHeaderLinks';

const { currentUrl, isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

const activeItemStyles =
    'border-primary/20 bg-primary/10 text-primary';

const expandedItemTitle = ref<string | null>(null);

const findActiveParentTitle = () =>
    mainNavItems.find(
        (item) =>
            item.children?.length &&
            item.children.some((child) => isCurrentOrParentUrl(child.href)),
    )?.title ?? null;

const isItemActive = (item: (typeof mainNavItems)[number]) =>
    Boolean(
        isCurrentUrl(item.href) ||
        item.children?.some((child) => isCurrentOrParentUrl(child.href)),
    );

const isItemExpanded = (item: (typeof mainNavItems)[number]) =>
    expandedItemTitle.value === item.title;

const toggleItem = (item: (typeof mainNavItems)[number]) => {
    expandedItemTitle.value = isItemExpanded(item) ? null : item.title;
};

watch(
    currentUrl,
    () => {
        expandedItemTitle.value = findActiveParentTitle();
    },
    { immediate: true },
);
</script>

<template>
    <div class="lg:hidden">
        <Sheet>
            <SheetTrigger :as-child="true">
                <Button variant="ghost" size="icon" class="mr-2 h-9 w-9">
                    <Menu class="h-5 w-5" />
                </Button>
            </SheetTrigger>
            <SheetContent side="left" class="w-[300px] p-6">
                <SheetTitle class="sr-only">Navigation menu</SheetTitle>
                <SheetHeader class="flex justify-start text-left">
                    <AppLogoIcon
                        class="size-6 fill-current text-primary"
                    />
                </SheetHeader>
                <div
                    class="flex h-full flex-1 flex-col justify-between space-y-4 py-6"
                >
                    <nav class="-mx-3 space-y-1">
                        <div
                            v-for="item in mainNavItems"
                            :key="item.title"
                            class="space-y-1"
                        >
                            <button
                                v-if="item.children?.length"
                                type="button"
                                class="flex w-full items-center gap-x-3 rounded-lg px-3 py-2 text-left text-sm font-medium transition hover:bg-primary/5 hover:text-primary"
                                :class="
                                    isItemActive(item) ? activeItemStyles : null
                                "
                                @click="toggleItem(item)"
                            >
                                <component
                                    v-if="item.icon"
                                    :is="item.icon"
                                    class="h-5 w-5"
                                />
                                <span class="flex-1">{{ item.title }}</span>
                                <ChevronDown
                                    class="h-4 w-4 shrink-0 transition duration-200"
                                    :class="
                                        isItemExpanded(item)
                                            ? 'rotate-180'
                                            : 'rotate-0'
                                    "
                                />
                            </button>
                            <Link
                                v-else
                                :href="item.href"
                                class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-medium transition hover:bg-primary/5 hover:text-primary"
                                :class="
                                    isItemActive(item) ? activeItemStyles : null
                                "
                            >
                                <component
                                    v-if="item.icon"
                                    :is="item.icon"
                                    class="h-5 w-5"
                                />
                                {{ item.title }}
                            </Link>
                            <Transition name="app-collapse">
                                <div
                                    v-if="
                                        item.children?.length &&
                                        isItemExpanded(item)
                                    "
                                    class="ml-6 overflow-hidden pl-3"
                                >
                                    <div class="space-y-1 py-1">
                                        <Link
                                            v-for="child in item.children"
                                            :key="`${item.title}-${child.title}`"
                                            :href="child.href"
                                            class="flex items-center gap-x-2 rounded-lg px-3 py-2 text-sm text-slate-600 transition hover:bg-primary/5 hover:text-primary"
                                            :class="
                                                isCurrentOrParentUrl(child.href)
                                                    ? activeItemStyles
                                                    : null
                                            "
                                        >
                                            <component
                                                v-if="child.icon"
                                                :is="child.icon"
                                                class="h-4 w-4"
                                            />
                                            {{ child.title }}
                                        </Link>
                                    </div>
                                </div>
                            </Transition>
                        </div>
                    </nav>
                    <div class="flex flex-col space-y-4">
                        <a
                            v-for="item in rightNavItems"
                            :key="item.title"
                            :href="toUrl(item.href)"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="flex items-center space-x-2 text-sm font-medium"
                        >
                            <component
                                v-if="item.icon"
                                :is="item.icon"
                                class="h-5 w-5"
                            />
                            <span>{{ item.title }}</span>
                        </a>
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    </div>
</template>
