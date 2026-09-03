<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import HelpdeskDetail from '@/pages/Helpdesk/Partials/HelpdeskDetail.vue';
import type { BreadcrumbItem } from '@/types';

interface TicketDetail {
    id: number;
    company: string;
    location: string;
    category: string;
    ticket_scope: string;
    priority: string;
    requester: string;
    department: string;
    snipeit_asset_id: number | null;
    asset_reference_snapshot: string | null;
    maintenance_type: string;
    issue_description: string;
    action_taken: string;
    note: string | null;
    technician: string;
    status: string;
    date_closed: string | null;
    snipeit_maintenance_id: number | null;
    snipeit_sync_status: string | null;
    snipeit_sync_message: string | null;
    created_at: string | null;
    creator: {
        id: number;
        name: string;
    } | null;
}

interface Props {
    ticket: TicketDetail;
    canViewAll: boolean;
    techCompany: string;
    techLocation: string;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Workspace', href: '/helpdesk' },
    {
        title: `Ticket #${props.ticket.id}`,
        href: `/helpdesk/${props.ticket.id}`,
    },
];

const handleEdit = () => {
    router.get(`/helpdesk/${props.ticket.id}/edit`);
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="app-page-shell">
            <Head :title="`Workspace #${props.ticket.id}`" />

            <div class="mx-auto max-w-3xl space-y-0">
                <!-- ── Back link ── -->
                <div class="py-3">
                    <Link
                        href="/helpdesk"
                        class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-border bg-card px-3 text-xs font-medium text-foreground shadow-sm transition-colors hover:bg-muted"
                    >
                        ← Back to list
                    </Link>
                </div>

                <HelpdeskDetail
                    :ticket="props.ticket"
                    :can-view-all="props.canViewAll"
                    :tech-company="props.techCompany"
                    :tech-location="props.techLocation"
                    @edit="handleEdit"
                />
            </div>
        </div>
    </AppLayout>
</template>
