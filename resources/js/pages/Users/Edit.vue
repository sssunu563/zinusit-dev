<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import UserForm from '@/pages/Users/Partials/UserForm.vue';
import type { BreadcrumbItem } from '@/types';

type SelectOption = { id: number; name: string };

type Props = {
    user: {
        id: number;
        name: string;
        first_name?: string | null;
        last_name?: string | null;
        username?: string | null;
        email: string;
        phone?: string | null;
        mobile?: string | null;
        jobtitle?: string | null;
        employee_num?: string | null;
        manager_id?: number | null;
        location_id?: number | null;
        department_id?: number | null;
        company_id?: number | null;
        vip?: boolean;
        remote?: boolean;
        auto_assign_licenses?: boolean;
        website?: string | null;
        notes?: string | null;
    };
    options: {
        managers: SelectOption[];
        locations: SelectOption[];
        departments: SelectOption[];
        companies: SelectOption[];
    };
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Users',     href: '/users' },
    { title: `Edit ${props.user.name}`, href: `/users/${props.user.id}/edit` },
];
</script>

<template>
    <Head :title="`Edit ${user.name}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="app-page-shell">
                <div class="overflow-hidden rounded-[32px] border border-slate-200 bg-white shadow-xl shadow-slate-200/40">
                    <UserForm
                        :title="`Modify Identity: ${user.name}`"
                        submit-label="Commit Profile Changes"
                        :submit-url="`/users/${user.id}`"
                        method="put"
                        :user-id="user.id"
                        :options="options"
                        :initial-values="{
                            first_name:            user.first_name           ?? '',
                            last_name:             user.last_name            ?? '',
                            username:              user.username             ?? '',
                            email:                 user.email,
                            phone:                 user.phone               ?? '',
                            mobile:                user.mobile              ?? '',
                            jobtitle:              user.jobtitle            ?? '',
                            employee_num:          user.employee_num        ?? '',
                            website:               user.website             ?? '',
                            notes:                 user.notes               ?? '',
                            manager_id:            user.manager_id   ? String(user.manager_id)   : '',
                            location_id:           user.location_id  ? String(user.location_id)  : '',
                            department_id:         user.department_id ? String(user.department_id): '',
                            company_id:            user.company_id   ? String(user.company_id)   : '',
                            vip:                   user.vip                 ?? false,
                            remote:                user.remote              ?? false,
                            auto_assign_licenses:  user.auto_assign_licenses ?? false,
                            password:              '',
                            password_confirmation: '',
                        }"
                    />
                </div>
        </div>
    </AppLayout>
</template>