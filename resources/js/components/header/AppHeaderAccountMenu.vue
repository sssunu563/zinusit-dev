<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { getInitials } from '@/composables/useInitials';
import type { User } from '@/types';

defineProps<{
    user: User;
}>();
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger :as-child="true">
            <Button
                variant="ghost"
                size="icon"
                class="relative size-11 w-auto rounded-full border border-border bg-card/60 p-1.5 shadow-xl focus-within:ring-2 focus-within:ring-primary"
            >
                <Avatar class="size-8 overflow-hidden rounded-full">
                    <AvatarImage
                        v-if="user.avatar"
                        :src="user.avatar"
                        :alt="user.name"
                    />
                    <AvatarFallback
                        class="rounded-lg bg-primary/10 font-bold text-primary"
                    >
                        {{ getInitials(user?.name) }}
                    </AvatarFallback>
                </Avatar>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent
            align="end"
            class="w-56 rounded-2xl border border-border bg-popover backdrop-blur-xl"
        >
            <UserMenuContent :user="user" />
        </DropdownMenuContent>
    </DropdownMenu>
</template>
