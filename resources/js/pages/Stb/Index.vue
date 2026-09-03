<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppConfirmDialog from '@/components/AppConfirmDialog.vue';
import { useRenderProfiler } from '@/composables/useRenderProfiler';
import AppLayout from '@/layouts/AppLayout.vue';
import StbListTableSection from '@/pages/Stb/Partials/StbListTableSection.vue';
import type { BreadcrumbItem } from '@/types';
import {
    formatStbDocId,
    isStbLoanOut,
    isStbServiceDocument,
    resolveStbDocumentLabel,
} from '@/utils/stb';
import {
    ensureStbDirectoryLoaded,
    getStbGroupParts,
    getStbUserLabel,
} from '@/utils/stbDirectory';

interface STB {
    id: number;
    user_id: number | null;
    group_id: number | null;
    deliver_date: string | null;
    building: string | null;
    use_date: string | null;
    batch_no: string | null;
    it_drafter_id: number | null;
    it_checker_id: number | null;
    it_approved_id: number | null;
    status: number | null;
    document_type?: string | null;
    movement_type?: string | null;
    returned_at?: string | null;
    document_label?: string | null;
    share_url?: string | null;
    completed_pdf_url?: string | null;
    completed_at?: string | null;
    completed_pdf_path?: string | null;
    is_fully_signed?: boolean;
    is_completed?: boolean;
    cancelled_at?: string | null;
    is_cancelled?: boolean;
    created_at: string;
    updated_at: string;
    items?: any[];
    it_drafter_signature_path?: string | null;
    it_checker_signature_path?: string | null;
    it_approved_signature_path?: string | null;
    requester_received_signature_path?: string | null;
    requester_dept_head_signature_path?: string | null;
}

interface Props {
    stbs: {
        data: STB[];
        links: any[];
        meta: any;
    };
    activeTab: 'pending' | 'completed' | 'cancelled';
    pendingCount: number;
    completedCount: number;
    cancelledCount: number;
}

type SortKey =
    | 'docId'
    | 'location'
    | 'userName'
    | 'company'
    | 'department'
    | 'status'
    | 'batchNo'
    | 'deliverDate'
    | 'updatedAt';

type TableColumn = {
    key: SortKey;
    label: string;
    value: (stb: STB) => string;
    cellClass?: string;
    headerClass?: string;
};

const props = defineProps<Props>();
useRenderProfiler('StbIndex');
const completeConfirmId = ref<number | null>(null);
const deleteConfirmId = ref<number | null>(null);
const actionNotice = ref<{ type: 'success' | 'error'; message: string } | null>(
    null,
);
let actionNoticeTimer: number | null = null;
const completeProcessing = ref(false);
const cancelProcessing = ref(false);
const searchQuery = ref('');
const sortKey = ref<SortKey>('updatedAt');
const sortDirection = ref<'asc' | 'desc'>('desc');
const columnFilters = ref<Partial<Record<SortKey, string>>>({});
const pageSize = ref(10);
const currentPage = ref(1);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'STB', href: '/stb' },
];

const setActionNotice = (
    message: string,
    type: 'success' | 'error' = 'success',
) => {
    actionNotice.value = { message, type };

    if (actionNoticeTimer) {
        window.clearTimeout(actionNoticeTimer);
    }

    actionNoticeTimer = window.setTimeout(() => {
        actionNotice.value = null;
    }, 3200);
};

const openDeleteConfirm = (id: number) => {
    deleteConfirmId.value = id;
};

const closeDeleteConfirm = () => {
    deleteConfirmId.value = null;
};

const deleteStb = () => {
    if (deleteConfirmId.value === null) {
        return;
    }

    cancelProcessing.value = true;
    router.post(
        `/stb/${deleteConfirmId.value}/cancel`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                cancelProcessing.value = false;
                closeDeleteConfirm();
            },
        },
    );
};

const openCompleteConfirm = (id: number) => {
    completeConfirmId.value = id;
};

const closeCompleteConfirm = () => {
    completeConfirmId.value = null;
};

const activeDeleteStb = computed(
    () =>
        props.stbs.data.find((stb) => stb.id === deleteConfirmId.value) ?? null,
);

const activeCompleteStb = computed(
    () =>
        props.stbs.data.find((stb) => stb.id === completeConfirmId.value) ??
        null,
);

const completeStb = () => {
    if (completeConfirmId.value === null) {
        return;
    }

    completeProcessing.value = true;
    router.post(
        `/stb/${completeConfirmId.value}/complete`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                completeProcessing.value = false;
                closeCompleteConfirm();
            },
        },
    );
};

const copyShareLink = async (shareUrl?: string | null) => {
    if (!shareUrl) {
        return;
    }

    try {
        await navigator.clipboard.writeText(shareUrl);
        setActionNotice('Share link copied to clipboard.');
    } catch {
        const fallbackInput = document.createElement('textarea');
        fallbackInput.value = shareUrl;
        fallbackInput.setAttribute('readonly', 'true');
        fallbackInput.style.position = 'fixed';
        fallbackInput.style.opacity = '0';
        document.body.appendChild(fallbackInput);
        fallbackInput.select();

        try {
            document.execCommand('copy');
            setActionNotice('Share link copied to clipboard.');
        } catch {
            setActionNotice(
                'Share link could not be copied automatically in this browser.',
                'error',
            );
        } finally {
            document.body.removeChild(fallbackInput);
        }
    }
};

const formatDate = (date: string) =>
    new Date(date).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });

const resolveCompany = (stb: STB) =>
    getStbGroupParts(stb.group_id, stb.user_id).company || '-';
const resolveLocation = (stb: STB) =>
    getStbGroupParts(stb.group_id, stb.user_id).location || '-';
const resolveDepartment = (stb: STB) =>
    getStbGroupParts(stb.group_id, stb.user_id).department || '-';
const isCancelled = (stb: STB) => Boolean(stb.is_cancelled || stb.cancelled_at);
const resolveStatus = (stb: STB) =>
    isCancelled(stb)
        ? 'Cancelled'
        : stb.document_label || resolveStbDocumentLabel(stb);
const resolveSignatureStats = (stb: STB) => {
    const fields = [
        stb.it_drafter_signature_path,
        stb.it_checker_signature_path,
        stb.it_approved_signature_path,
        stb.requester_received_signature_path,
        stb.requester_dept_head_signature_path,
    ];
    const signedCount = fields.filter((f) => !!f).length;
    return `${signedCount}/5`;
};

const resolveStatusBadgeClass = (stb: STB) => {
    if (isCancelled(stb)) {
        return 'app-badge-danger';
    }

    if (isCompleted(stb)) {
        return 'app-badge-positive';
    }

    if (isStbServiceDocument(stb)) {
        return 'app-badge-indigo';
    }

    if (isReadyToComplete(stb)) {
        return 'app-badge-warning';
    }

    return 'app-badge-neutral';
};
const resolveDocId = (stb: STB) =>
    formatStbDocId({
        id: stb.id,
        company: getStbGroupParts(stb.group_id, stb.user_id).company,
        date: stb.created_at,
    }) || `${stb.id}`;
const tableColumns: TableColumn[] = [
    {
        key: 'docId',
        label: '#',
        value: () => '#',
        cellClass: 'text-center',
    },
    {
        key: 'docId',
        label: 'DOC ID',
        value: (stb) => resolveDocId(stb),
        cellClass: 'app-table-emphasis',
    },
    {
        key: 'location',
        label: 'LOCATION',
        value: (stb) => resolveLocation(stb),
    },
    {
        key: 'deliverDate',
        label: 'DELIVER DATE',
        value: (stb) => stb.deliver_date ? formatDate(stb.deliver_date) : formatDate(stb.created_at),
    },
    {
        key: 'userName',
        label: 'NAME',
        value: (stb) => getStbUserLabel(stb.user_id),
    },
    {
        key: 'company',
        label: 'COMPANY',
        value: (stb) => resolveCompany(stb),
    },
    {
        key: 'department',
        label: 'DEPARTMENT',
        value: (stb) => resolveDepartment(stb),
    },
    {
        key: 'status',
        label: 'STATUS',
        value: (stb) => resolveStatus(stb),
    },
    {
        key: 'docId',
        label: 'SIGNATURE',
        value: (stb) => resolveSignatureStats(stb),
        cellClass: 'text-center',
    },
    {
        key: 'batchNo',
        label: 'ITEMS',
        value: (stb) => String(stb.items?.length || 0),
        cellClass: 'text-center',
    },
];

const selectedStatus = computed(() => columnFilters.value.status || '');
const selectedCompany = computed(() => columnFilters.value.company || '');
const selectedLocation = computed(() => columnFilters.value.location || '');

const statusOptions = computed(() => {
    const column = tableColumns.find((entry) => entry.key === 'status');

    return column ? getColumnFilterOptions(column) : [];
});

const companyOptions = computed(() => {
    const column = tableColumns.find((entry) => entry.key === 'company');

    return column ? getColumnFilterOptions(column) : [];
});

const locationOptions = computed(() => {
    const column = tableColumns.find((entry) => entry.key === 'location');

    return column ? getColumnFilterOptions(column) : [];
});

const handleStatusChange = (value: string) => {
    updateColumnFilter('status', value);
};

const handleCompanyChange = (value: string) => {
    updateColumnFilter('company', value);
};

const handleLocationChange = (value: string) => {
    updateColumnFilter('location', value);
};

const csvFileName = computed(() => {
    const stamp = new Date().toISOString().slice(0, 10);

    return `stb-${props.activeTab}-${stamp}.csv`;
});

const escapeCsvValue = (value: string) => {
    const normalized = String(value ?? '');

    if (
        normalized.includes(',') ||
        normalized.includes('"') ||
        normalized.includes('\n')
    ) {
        return `"${normalized.replace(/"/g, '""')}"`;
    }

    return normalized;
};

const downloadCsv = () => {
    const header = tableColumns.map((column) => escapeCsvValue(column.label));
    const rows = sortedStbs.value.map((stb) =>
        tableColumns
            .map((column) => escapeCsvValue(column.value(stb)))
            .join(','),
    );
    const csv = [header.join(','), ...rows].join('\n');
    const blob = new Blob([csv], {
        type: 'text/csv;charset=utf-8;',
    });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = url;
    link.download = csvFileName.value;
    link.click();
    URL.revokeObjectURL(url);
};

const downloadPdf = () => {
    window.print();
};

const isCompleted = (stb: STB) =>
    Boolean(stb.is_completed || (stb.completed_at && stb.completed_pdf_path));
const getLoanReturnHref = (stb: STB) => {
    if (
        !isStbLoanOut(stb) ||
        !stb.is_fully_signed ||
        isCancelled(stb) ||
        stb.returned_at
    ) {
        return null;
    }

    return `/peminjaman/create?movementType=return&linkedStbId=${stb.id}`;
};
const isReadyToComplete = (stb: STB) =>
    Boolean(stb.is_fully_signed) &&
    !isCompleted(stb) &&
    !getLoanReturnHref(stb);
const hasLockedActions = (stb: STB) =>
    isReadyToComplete(stb) || Boolean(getLoanReturnHref(stb));
const toSearchable = (value: unknown) =>
    String(value ?? '')
        .trim()
        .toLowerCase();
const searchedStbs = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    if (!query) {
        return props.stbs.data;
    }

    return props.stbs.data.filter((stb) => {
        const haystack = [
            resolveDocId(stb),
            getStbUserLabel(stb.user_id),
            resolveCompany(stb),
            resolveLocation(stb),
            resolveDepartment(stb),
            resolveStatus(stb),
            stb.building,
            stb.batch_no,
            stb.deliver_date ? formatDate(stb.deliver_date) : '',
        ];

        return haystack.some((value) => toSearchable(value).includes(query));
    });
});
const getColumnFilterOptions = (column: TableColumn) => {
    const values = searchedStbs.value
        .map((stb) => column.value(stb))
        .filter((value) => value !== '-');

    return [...new Set(values)].sort((left, right) =>
        left.localeCompare(right, undefined, {
            numeric: true,
            sensitivity: 'base',
        }),
    );
};
const updateColumnFilter = (nextKey: SortKey, value: string) => {
    if (value === '') {
        delete columnFilters.value[nextKey];
        columnFilters.value = { ...columnFilters.value };
        return;
    }

    columnFilters.value = {
        ...columnFilters.value,
        [nextKey]: value,
    };
};
const toggleColumnSort = (nextKey: SortKey) => {
    if (sortKey.value === nextKey) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
        return;
    }

    sortKey.value = nextKey;
    sortDirection.value = 'asc';
};
const filteredStbs = computed(() => {
    const activeFilters = Object.entries(columnFilters.value).filter(
        ([, value]) => Boolean(value),
    ) as Array<[SortKey, string]>;

    return searchedStbs.value.filter((stb) =>
        activeFilters.every(([activeKey, activeValue]) => {
            const column = tableColumns.find(
                (entry) => entry.key === activeKey,
            );

            if (!column) {
                return true;
            }

            return column.value(stb).trim() === activeValue;
        }),
    );
});
const sortedStbs = computed(() => {
    const column = tableColumns.find((entry) => entry.key === sortKey.value);

    if (!column) {
        return filteredStbs.value;
    }

    return [...filteredStbs.value].sort((left, right) => {
        const leftValue = column.value(left);
        const rightValue = column.value(right);
        const result = leftValue.localeCompare(rightValue, undefined, {
            numeric: true,
            sensitivity: 'base',
        });

        return sortDirection.value === 'asc' ? result : -result;
    });
});
const totalPages = computed(() =>
    Math.max(1, Math.ceil(sortedStbs.value.length / pageSize.value)),
);
const paginatedStbs = computed(() => {
    const start = (currentPage.value - 1) * pageSize.value;

    return sortedStbs.value.slice(start, start + pageSize.value);
});
const pageStart = computed(() => {
    if (!sortedStbs.value.length) {
        return 0;
    }

    return (currentPage.value - 1) * pageSize.value + 1;
});
const pageEnd = computed(() =>
    Math.min(currentPage.value * pageSize.value, sortedStbs.value.length),
);
const pageNumbers = computed(() => {
    const total = totalPages.value;
    const current = currentPage.value;
    const pages = new Set<number>([
        1,
        total,
        current,
        current - 1,
        current + 1,
    ]);

    return [...pages]
        .filter((page) => page >= 1 && page <= total)
        .sort((left, right) => left - right);
});
const goToPreviousPage = () => {
    currentPage.value = Math.max(1, currentPage.value - 1);
};
const goToNextPage = () => {
    currentPage.value = Math.min(totalPages.value, currentPage.value + 1);
};
const setPage = (page: number) => {
    currentPage.value = Math.min(Math.max(1, page), totalPages.value);
};
const resetFilters = () => {
    searchQuery.value = '';
    columnFilters.value = {};
    sortKey.value = 'updatedAt';
    sortDirection.value = 'desc';
    currentPage.value = 1;
};

watch(
    [searchQuery, columnFilters, sortKey, sortDirection, pageSize],
    () => {
        currentPage.value = 1;
    },
    { deep: true },
);

watch(totalPages, (nextTotal) => {
    if (currentPage.value > nextTotal) {
        currentPage.value = nextTotal;
    }
});

onMounted(() => {
    ensureStbDirectoryLoaded();
});

onBeforeUnmount(() => {
    if (actionNoticeTimer) {
        window.clearTimeout(actionNoticeTimer);
    }
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="app-page-shell">
            <Head title="FORM SERAH TERIMA BARANG" />


            <div
                v-if="actionNotice"
                :class="[
                    'app-inline-notice',
                    actionNotice.type === 'success'
                        ? 'app-inline-notice-success'
                        : 'app-inline-notice-danger',
                ]"
            >
                {{ actionNotice.message }}
            </div>

            <StbListTableSection
                :search-query="searchQuery"
                :download-csv="downloadCsv"
                :download-pdf="downloadPdf"
                :server-links="stbs.links"
                :server-total="props.stbs.meta?.total || sortedStbs.length"
                :table-columns="tableColumns"
                :paginated-stbs="paginatedStbs"
                :sort-key="sortKey"
                :sort-direction="sortDirection"
                :status-options="statusOptions"
                :company-options="companyOptions"
                :location-options="locationOptions"
                :selected-status="selectedStatus"
                :selected-company="selectedCompany"
                :selected-location="selectedLocation"
                :page-start="pageStart"
                :page-end="pageEnd"
                :total-rows="sortedStbs.length"
                :page-size="pageSize"
                :current-page="currentPage"
                :total-pages="totalPages"
                :page-numbers="pageNumbers"
                :resolve-doc-id="resolveDocId"
                :resolve-company="resolveCompany"
                :resolve-location="resolveLocation"
                :resolve-department="resolveDepartment"
                :resolve-status="resolveStatus"
                :resolve-status-badge-class="resolveStatusBadgeClass"
                :format-date="formatDate"
                :is-completed="isCompleted"
                :is-cancelled="isCancelled"
                :is-ready-to-complete="isReadyToComplete"
                :get-loan-return-href="getLoanReturnHref"
                :has-locked-actions="hasLockedActions"
                :copy-share-link="copyShareLink"
                :open-complete-confirm="openCompleteConfirm"
                :open-delete-confirm="openDeleteConfirm"
                :toggle-column-sort="toggleColumnSort"
                :handle-status-change="handleStatusChange"
                :handle-company-change="handleCompanyChange"
                :handle-location-change="handleLocationChange"
                :reset-filters="resetFilters"
                :go-to-previous-page="goToPreviousPage"
                :go-to-next-page="goToNextPage"
                :set-page="setPage"
                :active-tab="props.activeTab"
                :pending-count="props.pendingCount"
                :completed-count="props.completedCount"
                :cancelled-count="props.cancelledCount"
                @update:search-query="searchQuery = $event"
                @update:page-size="pageSize = $event"
            />

            <div v-if="sortedStbs.length === 0" class="app-empty-state">
                <p class="app-empty-state-copy mb-4">
                    {{
                        searchQuery
                            ? 'Tidak ada dokumen yang cocok dengan pencarian di halaman ini.'
                            : 'Belum ada data STB.'
                    }}
                </p>
                <div class="app-empty-state-actions">
                    <Link
                        href="/stb/create?documentType=handover&movementType=out"
                        class="app-empty-state-link"
                    >
                        Serah Terima Asset
                    </Link>
                    <Link
                        href="/stb/create?documentType=handover&movementType=return"
                        class="app-empty-state-link"
                    >
                        Pengembalian Asset
                    </Link>
                </div>
            </div>
        </div>
        <AppConfirmDialog
            :open="completeConfirmId !== null"
            kicker="Finalisasi Dokumen"
            title="Kunci dokumen ke PDF final?"
            description="Setelah difinalisasi, dokumen masuk tabel Selesai, akses edit/hapus akan hilang, dan proses pencetakan akan menggunakan file PDF final."
            confirm-label="Ya, Finalisasi"
            cancel-label="Kembali"
            confirm-variant="warning"
            :processing="completeProcessing"
            :subject="
                activeCompleteStb ? resolveDocId(activeCompleteStb) : null
            "
            @close="closeCompleteConfirm"
            @confirm="completeStb"
        />

        <AppConfirmDialog
            :open="deleteConfirmId !== null"
            kicker="Batalkan Dokumen"
            title="Batalkan dokumen ini?"
            description="Tindakan ini akan memindahkan dokumen ke daftar Dibatalkan dan mengunci dokumen dari proses edit, tanda tangan, maupun finalisasi."
            confirm-label="Ya, Batalkan"
            cancel-label="Kembali"
            confirm-variant="danger"
            :processing="cancelProcessing"
            :subject="activeDeleteStb ? resolveDocId(activeDeleteStb) : null"
            @close="closeDeleteConfirm"
            @confirm="deleteStb"
        />
    </AppLayout>
</template>
