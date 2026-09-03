<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { LogOut, Settings } from 'lucide-vue-next';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

type Props = {
    user: User;
};

const handleLogout = () => {
    router.flushAll();
};

defineProps<Props>();
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-2 py-2 text-left text-sm">
            <UserInfo :user="user" :show-email="true" tone="default" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator class="bg-slate-100" />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link
                class="flex w-full cursor-pointer items-center rounded-xl border border-transparent px-3 py-2 text-sm font-bold text-slate-700 transition-all duration-200 hover:bg-primary/5 hover:text-primary"
                :href="edit()"
                prefetch
            >
                <Settings class="mr-2 h-4 w-4 text-primary" />
                <span>Settings</span>
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator class="bg-slate-100" />
    <DropdownMenuItem :as-child="true">
        <Link
            class="flex w-full cursor-pointer items-center rounded-xl border border-transparent px-3 py-2 text-sm font-bold text-rose-600 transition-all duration-200 hover:bg-rose-50 hover:text-rose-700"
            :href="logout()"
            @click="handleLogout"
            as="button"
            data-test="logout-button"
        >
            <LogOut class="mr-2 h-4 w-4" />
            <span>Log out</span>
        </Link>
    </DropdownMenuItem>
</template>
