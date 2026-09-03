<script setup lang="ts">
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import type { User } from '@/types';

type Props = {
    user: User;
    showEmail?: boolean;
    tone?: 'sidebar' | 'default';
};

const props = withDefaults(defineProps<Props>(), {
    showEmail: false,
    tone: 'sidebar',
});

const { getInitials } = useInitials();

const nameColor = computed(() =>
    props.tone === 'default'
        ? 'var(--foreground)'
        : 'var(--app-sidebar-text)',
);

const metaColor = computed(() =>
    props.tone === 'default'
        ? 'var(--muted-foreground)'
        : 'var(--app-sidebar-text-muted)',
);

const avatarFallbackClass = computed(() =>
    props.tone === 'default'
        ? 'rounded-xl border border-slate-200 bg-slate-50 text-slate-700'
        : 'rounded-full border border-white/10 bg-white/5 text-white',
);

// Compute whether we should show the avatar image
const showAvatar = computed(
    () => props.user.avatar && props.user.avatar !== '',
);
</script>

<template>
    <Avatar
        class="h-[1.875rem] w-[1.875rem] overflow-hidden rounded-full shadow-none group-data-[collapsible=icon]:h-[1.875rem] group-data-[collapsible=icon]:w-[1.875rem] group-data-[collapsible=icon]:rounded-full"
        style="border-color: var(--app-sidebar-chip-border)"
    >
        <AvatarImage v-if="showAvatar" :src="user.avatar!" :alt="user.name" />
        <AvatarFallback :class="avatarFallbackClass">
            {{ getInitials(user.name) }}
        </AvatarFallback>
    </Avatar>

    <div
        class="grid flex-1 text-left text-sm leading-tight group-data-[collapsible=icon]:hidden"
    >
        <span
            class="truncate text-[11px] font-black"
            :style="{ color: nameColor }"
            >{{ user.name }}</span
        >
        <span
            v-if="showEmail"
            class="truncate text-[10px]"
            :style="{ color: metaColor }"
            >{{ user.email }}</span
        >
    </div>
</template>
