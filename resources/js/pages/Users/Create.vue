<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import UserForm from '@/pages/Users/Partials/UserForm.vue';
import type { BreadcrumbItem } from '@/types';

type SelectOption = { id: number; name: string };

type Props = {
    options: {
        managers: SelectOption[];
        locations: SelectOption[];
        departments: SelectOption[];
        companies: SelectOption[];
    };
};

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard',  href: '/dashboard' },
    { title: 'Users',      href: '/users' },
    { title: 'Create User', href: '/users/create' },
];
</script>

<template>
    <Head title="Create User" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="app-page-shell">
                <div class="overflow-hidden rounded-[32px] border border-slate-200 bg-white shadow-xl shadow-slate-200/40">
                    <UserForm
                        title="Add New Identity"
                        submit-label="Commit New Identity"
                        submit-url="/users"
                        method="post"
                        :options="options"
                        :initial-values="{
                            first_name:            '',
                            last_name:             '',
                            username:              '',
                            email:                 '',
                            phone:                 '',
                            mobile:                '',
                            jobtitle:              '',
                            employee_num:          '',
                            website:               '',
                            notes:                 '',
                            manager_id:            '',
                            location_id:           '',
                            department_id:         '',
                            company_id:            '',
                            vip:                   false,
                            remote:                false,
                            auto_assign_licenses:  true,
                            password:              '',
                            password_confirmation: '',
                        }"
                    />
                </div>
        </div>
    </AppLayout>
</template>