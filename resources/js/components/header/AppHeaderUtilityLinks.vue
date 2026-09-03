<script setup lang="ts">
import { Search } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { toUrl } from '@/lib/utils';
import { rightNavItems } from './appHeaderLinks';
</script>

<template>
    <div class="relative flex items-center space-x-1">
        <Button
            variant="ghost"
            size="icon"
            class="group h-10 w-10 cursor-pointer rounded-full border border-border bg-card/60 shadow-xl"
        >
            <Search class="size-5 opacity-80 group-hover:opacity-100" />
        </Button>

        <div class="hidden space-x-1 lg:flex">
            <template v-for="item in rightNavItems" :key="item.title">
                <TooltipProvider :delay-duration="0">
                    <Tooltip>
                        <TooltipTrigger>
                            <Button
                                variant="ghost"
                                size="icon"
                                as-child
                                class="group h-10 w-10 cursor-pointer rounded-full border border-border bg-card/60 shadow-xl"
                            >
                                <a
                                    :href="toUrl(item.href)"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <span class="sr-only">{{
                                        item.title
                                    }}</span>
                                    <component
                                        :is="item.icon"
                                        class="size-5 opacity-80 group-hover:opacity-100"
                                    />
                                </a>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>{{ item.title }}</p>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            </template>
        </div>
    </div>
</template>
