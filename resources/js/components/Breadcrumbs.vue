<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { CircleHelp } from 'lucide-vue-next';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

type Props = {
    breadcrumbs: BreadcrumbItemType[];
};

defineProps<Props>();
</script>

<template>
    <Breadcrumb>
        <BreadcrumbList
            class="flex-wrap gap-1.5 text-[12px] text-muted-foreground md:text-[12.5px]"
        >
            <template v-for="(item, index) in breadcrumbs" :key="index">
                <BreadcrumbItem>
                    <template v-if="index === breadcrumbs.length - 1">
                        <BreadcrumbPage
                            class="font-semibold tracking-[0.01em] text-primary"
                            >{{ item.title }}</BreadcrumbPage
                        >
                    </template>
                    <template v-else>
                        <BreadcrumbLink as-child>
                            <Link
                                :href="item.href"
                                class="font-medium transition hover:text-primary"
                                >{{ item.title }}</Link
                            >
                        </BreadcrumbLink>
                    </template>
                    <span
                        v-if="item.help"
                        class="inline-flex items-center"
                        :title="item.help"
                        :aria-label="item.help"
                    >
                        <CircleHelp class="size-3.5 text-muted-foreground" />
                    </span>
                </BreadcrumbItem>
                <BreadcrumbSeparator
                    v-if="index !== breadcrumbs.length - 1"
                    class="text-border"
                />
            </template>
        </BreadcrumbList>
    </Breadcrumb>
</template>
