<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import AddStockModal from '@/pages/Asset/Partials/AddStockModal.vue';
import AssetListTableSection from '@/pages/Asset/Partials/AssetListTableSection.vue';
import HandoverModal from '@/pages/Asset/Partials/HandoverModal.vue';
import ReturnLoanModal from '@/pages/Asset/Modals/ReturnLoanModal.vue';
import type { AssetItem, SortKey, TableColumn } from '@/pages/Asset/types';
import type { BreadcrumbItem } from '@/types';
import { notify } from '@/utils/flash';

interface Metadata {
    users: any[];
    assets: {
        locations: any[];
        [key: string]: any;
    };
    [key: string]: any;
}

interface AssetState {
    id: number | string;
    name: string;
    count?: number;
}

interface AssetType {
    key: string;
    endpoint: string;
    label: string;
}

interface Props {
    types?: AssetType[];
    statuses: AssetState[];
    assets: AssetItem[];
    activeStatus?: number | null;
    activeType?: string | null;
    activeTypeLabel?: string | null;
    showStatusFilter?: boolean;
    totalAssets?: number;
    integrationDisabled?: boolean;
    metadata?: Metadata;
    loanReferences?: any[];
}

const props = withDefaults(defineProps<Props>(), {
    activeStatus: null,
    activeType: null,
    activeTypeLabel: 'Asset',
    showStatusFilter: false,
    totalAssets: 0,
    integrationDisabled: true,
});

const selectedIds = ref<(number | string)[]>([]);
const showHandoverModal = ref(false);
const showReturnLoanModal = ref(false);
const returnLoanSelectedItems = ref<AssetItem[]>([]);

const deleteAsset = (asset: AssetItem) => {
    if (!window.confirm(`Hapus asset "${asset.name}" dari Snipe-IT?`)) return;

    router.delete(
        `/asset/item/${encodeURIComponent(String(asset.id))}?type=${encodeURIComponent(activeType.value)}`,
        {
            preserveScroll: true,
        },
    );
};

const normalizeAssetStatusForStb = (value?: string | null) => {
    const normalized = String(value ?? '')
        .trim()
        .toLowerCase();

    if (!normalized || normalized === '-') return 'unsupported';
    if (normalized.includes('active')) return 'active';
    if (
        normalized.includes('stock') ||
        normalized.includes('ready to deploy') ||
        normalized.includes('available') ||
        normalized.includes('deployable')
    ) {
        return 'stock';
    }
    if (
        normalized.includes('borrow') ||
        normalized.includes('borrowed') ||
        normalized.includes('on loan')
    ) {
        return 'borrow';
    }

    return 'unsupported';
};

const handleHandover = () => {
    if (selectedIds.value.length === 0) {
        notify('error', 'Pilih minimal satu item untuk membuat STB.');
        return;
    }

    const selectedItems = props.assets.filter((a) =>
        selectedIds.value.some((id) => String(id) === String(a.id)),
    );

    const states = selectedItems.map((item) =>
        normalizeAssetStatusForStb(item.state_name ?? item.status_name),
    );
    const uniqueStates = [...new Set(states)];

    if (isStockType.value) {
        const zeroStockItem = selectedItems.find((item) => {
            const stockValue =
                item.remaining !== undefined && item.remaining !== null
                    ? Number(item.remaining)
                    : Number(item.stock ?? 0);
            return stockValue <= 0;
        });

        if (zeroStockItem) {
            notify(
                'error',
                `Tolong cek kembali ketersediaan ${zeroStockItem.name}, saat ini stocknya 0.`,
            );
            return;
        }

        const params = new URLSearchParams({
            documentType: 'handover',
            movementType: 'out',
        });

        selectedIds.value.forEach((id) => {
            params.append('selectedAssetIds[]', String(id));
        });

        router.visit(`/stb/create?${params.toString()}`);
        return;
    }

    if (uniqueStates.length > 1) {
        notify(
            'error',
            'Asset Active dan Stock tidak boleh dicampur dalam satu STB. Pilih hanya aset dengan status yang sama.',
        );
        return;
    }

    if (uniqueStates[0] === 'unsupported') {
        notify(
            'error',
            'Hanya aset dengan status Active atau Stock yang bisa dibuat STB. Status lain tidak diizinkan.',
        );
        return;
    }

    const movementType = uniqueStates[0] === 'active' ? 'return' : 'out';
    const params = new URLSearchParams({
        documentType: 'handover',
        movementType,
    });

    selectedIds.value.forEach((id) => {
        params.append('selectedAssetIds[]', String(id));
    });

    router.visit(`/stb/create?${params.toString()}`);
};

const handleLoan = () => {
    if (selectedIds.value.length === 0) {
        notify('error', 'Pilih minimal satu item untuk membuat Peminjaman.');
        return;
    }

    const selectedItems = props.assets.filter((a) =>
        selectedIds.value.some((id) => String(id) === String(a.id)),
    );

    const states = selectedItems.map((item) =>
        normalizeAssetStatusForStb(item.state_name ?? item.status_name),
    );
    const uniqueStates = [...new Set(states)];

    if (uniqueStates.length > 1) {
        notify(
            'error',
            'Asset Active dan Stock tidak boleh dicampur dalam satu Peminjaman. Pilih hanya aset dengan status yang sama.',
        );
        return;
    }

    if (uniqueStates[0] !== 'stock') {
        notify(
            'error',
            'Hanya aset dengan status Stock yang bisa dibuat Peminjaman (pinjaman baru).',
        );
        return;
    }

    const params = new URLSearchParams({
        movementType: 'out',
    });

    selectedIds.value.forEach((id) => {
        params.append('selectedAssetIds[]', String(id));
    });

    router.visit(`/peminjaman/create?${params.toString()}`);
};

const handleReturnLoan = () => {
    if (selectedIds.value.length === 0) {
        notify(
            'error',
            'Pilih minimal satu item untuk pengembalian Peminjaman.',
        );
        return;
    }

    const selectedItems = props.assets.filter((a) =>
        selectedIds.value.some((id) => String(id) === String(a.id)),
    );

    const states = selectedItems.map((item) =>
        normalizeAssetStatusForStb(item.state_name ?? item.status_name),
    );
    const uniqueStates = [...new Set(states)];

    if (uniqueStates.length > 1) {
        notify(
            'error',
            'Asset Borrow saja yang bisa dikembalikan. Pilih hanya aset dengan status Borrow.',
        );
        return;
    }

    if (uniqueStates[0] !== 'borrow') {
        notify(
            'error',
            'Hanya aset dengan status Borrow yang bisa dikembalikan (pengembalian pinjaman).',
        );
        return;
    }

    // Show modal to select Peminjaman
    returnLoanSelectedItems.value = selectedItems;
    showReturnLoanModal.value = true;
};

const handleReturnLoanSelect = (peminjaman: any) => {
    router.visit(
        `/peminjaman/create?linkedLoanId=${peminjaman.id}&movementType=return`,
    );
};

const selectedItems = computed(() =>
    props.assets.filter((asset) =>
        selectedIds.value.some((id) => String(id) === String(asset.id)),
    ),
);

const selectedItemStates = computed(() => {
    const states = selectedItems.value.map((item) =>
        normalizeAssetStatusForStb(item.state_name ?? item.status_name),
    );

    return [...new Set(states)];
});

const canShowGenerateStbIn = computed(
    () =>
        selectedIds.value.length > 0 &&
        selectedItemStates.value.length === 1 &&
        selectedItemStates.value[0] === 'active',
);

const canShowGenerateStbOut = computed(
    () =>
        selectedIds.value.length > 0 &&
        ((isStockType.value &&
            selectedItems.value.every((item) => {
                const stockValue =
                    item.remaining !== undefined && item.remaining !== null
                        ? Number(item.remaining)
                        : Number(item.stock ?? 0);
                return stockValue > 0;
            })) ||
            (!isStockType.value &&
                selectedItemStates.value.length === 1 &&
                selectedItemStates.value[0] === 'stock')),
);

const canShowGenerateLoan = computed(() => false);

const canShowReturnLoan = computed(
    () =>
        selectedIds.value.length > 0 &&
        selectedItemStates.value.length === 1 &&
        selectedItemStates.value[0] === 'borrow',
);

const handleHandoverSuccess = () => {
    selectedIds.value = [];
    showHandoverModal.value = false;
    router.reload({ preserveScroll: true });
};

const activeType = computed(() =>
    String(props.activeType || 'assets').toLowerCase(),
);
const pageTitle = computed(() => {
    const label = props.activeTypeLabel || 'Asset';
    if (label === 'Assets' || label === 'Asset') return 'Hardware';
    return label;
});
const pageHeading = computed(() => pageTitle.value);
const isHardwareType = computed(() =>
    ['assets', 'laptop'].includes(activeType.value),
);
const isStockType = computed(() =>
    ['accessories', 'consumable', 'component', 'license'].includes(
        activeType.value,
    ),
);
const defaultSortKey = computed<SortKey>(() => 'name');
const addButtonLabel = computed(() => {
    return (
        {
            Hardware: 'Add Asset',
            License: 'Add License',
            Accessories: 'Add Accessory',
            Consumable: 'Add Consumable',
            Component: 'Add Component',
        }[pageTitle.value] || `Add ${pageTitle.value}`
    );
});

const searchQuery = ref('');
const sortKey = ref<SortKey>(defaultSortKey.value);
const sortDirection = ref<'asc' | 'desc'>('asc');
const columnFilters = ref<Partial<Record<SortKey, string>>>({});
const pageSize = ref(10);
const currentPage = ref(1);
const selectedStatus = ref(
    props.activeStatus === null ? 'all' : String(props.activeStatus),
);

watch([activeType, selectedStatus], () => {
    selectedIds.value = [];
});

const toSearchable = (value: unknown) => String(value ?? '').toLowerCase();
const getDetailHref = (asset: AssetItem) => {
    const type = activeType.value === 'laptop' ? 'assets' : activeType.value;
    return `/asset/item/${encodeURIComponent(String(asset.id))}?type=${encodeURIComponent(type)}`;
};
const getEditHref = (asset: AssetItem) => {
    const type = activeType.value === 'laptop' ? 'assets' : activeType.value;
    return `/asset/${encodeURIComponent(String(asset.id))}/edit?type=${encodeURIComponent(type)}`;
};

const stockAsset = ref<AssetItem | null>(null);
const showStockModal = ref(false);
function openStockModal(asset: AssetItem) {
    stockAsset.value = asset;
    showStockModal.value = true;
}

const selectedAssetId = ref<number | string | null>(null);

const openDetail = (asset: AssetItem) => {
    router.visit(getDetailHref(asset));
};
const formatCellValue = (value: string | number) =>
    value === '' || value === null || value === undefined ? '-' : value;
const createColumn = (column: TableColumn): TableColumn => column;

const hardwareColumns = computed<TableColumn[]>(() => [
    createColumn({
        key: 'group_name',
        label: 'Location',
        sortKey: 'group_name',
        value: (asset) => asset.group_name || '',
    }),
    createColumn({
        key: 'holder_name',
        label: 'User',
        sortKey: 'holder_name',
        value: (asset) => asset.holder_name || '',
    }),
    createColumn({
        key: 'otherserial',
        label: 'Asset Tag',
        sortKey: 'otherserial',
        cellClass: 'app-table-emphasis',
        linkStyle: 'asset-tag',
        value: (asset) => asset.otherserial || 'Detail',
    }),
    createColumn({
        key: 'name',
        label: 'Name',
        sortKey: 'name',
        cellClass: 'app-table-emphasis',
        value: (asset) => asset.name || '',
    }),
    createColumn({
        key: 'serial',
        label: 'Serial',
        sortKey: 'serial',
        value: (asset) => asset.serial || '',
    }),
    createColumn({
        key: 'type_name',
        label: 'Kategori',
        sortKey: 'type_name',
        value: (asset) => asset.type_name || '',
    }),
    createColumn({
        key: 'state_name',
        label: 'Status',
        sortKey: 'state_name',
        value: (asset) => asset.state_name || '',
    }),
]);

const nonHardwareColumns = computed<TableColumn[]>(() => {
    const baseColumns = [
        createColumn({
            key: 'name',
            label: 'Name',
            sortKey: 'name',
            cellClass: 'app-table-emphasis',
            linkStyle: 'text',
            value: (asset) => asset.name || '',
        }),
    ];

    switch (activeType.value) {
        case 'license':
            return [
                ...baseColumns,
                createColumn({
                    key: 'otherserial',
                    label: 'Product Key',
                    sortKey: 'otherserial',
                    value: (asset) => asset.otherserial || '',
                }),
                createColumn({
                    key: 'serial',
                    label: 'Serial',
                    sortKey: 'serial',
                    value: (asset) => asset.serial || '',
                }),
                createColumn({
                    key: 'type_name',
                    label: 'Manufacturer',
                    sortKey: 'type_name',
                    value: (asset) => asset.type_name || '',
                }),
                createColumn({
                    key: 'group_name',
                    label: 'Location',
                    sortKey: 'group_name',
                    value: (asset) => asset.group_name || '',
                }),
                createColumn({
                    key: 'remaining',
                    label: 'Stock',
                    sortKey: 'stock',
                    headerClass: 'text-right',
                    cellClass: 'text-right font-bold',
                    value: (asset) => asset.remaining ?? '-',
                }),
                createColumn({
                    key: 'used',
                    label: 'Assigned',
                    sortKey: 'used',
                    headerClass: 'text-right',
                    cellClass: 'text-right text-gray-600',
                    value: (asset) => asset.used ?? '-',
                }),
            ];
        case 'accessories':
            return [
                ...baseColumns,
                createColumn({
                    key: 'serial',
                    label: 'Model No',
                    sortKey: 'serial',
                    value: (asset) => asset.serial || '',
                }),
                createColumn({
                    key: 'type_name',
                    label: 'Kategori',
                    sortKey: 'type_name',
                    value: (asset) => asset.type_name || '',
                }),
                createColumn({
                    key: 'group_name',
                    label: 'Location',
                    sortKey: 'group_name',
                    value: (asset) => asset.group_name || '',
                }),
                createColumn({
                    key: 'remaining',
                    label: 'Stock',
                    sortKey: 'stock',
                    headerClass: 'text-right',
                    cellClass: 'text-right font-bold',
                    value: (asset) => asset.remaining ?? '-',
                }),
                createColumn({
                    key: 'used',
                    label: 'Checked Out',
                    sortKey: 'used',
                    headerClass: 'text-right',
                    cellClass: 'text-right text-gray-600',
                    value: (asset) => asset.used ?? '-',
                }),
            ];
        case 'component':
            return [
                ...baseColumns,
                createColumn({
                    key: 'serial',
                    label: 'Serial',
                    sortKey: 'serial',
                    value: (asset) => asset.serial || '',
                }),
                createColumn({
                    key: 'type_name',
                    label: 'Kategori',
                    sortKey: 'type_name',
                    value: (asset) => asset.type_name || '',
                }),
                createColumn({
                    key: 'group_name',
                    label: 'Location',
                    sortKey: 'group_name',
                    value: (asset) => asset.group_name || '',
                }),
                createColumn({
                    key: 'remaining',
                    label: 'Stock',
                    sortKey: 'stock',
                    headerClass: 'text-right',
                    cellClass: 'text-right font-bold',
                    value: (asset) => asset.remaining ?? '-',
                }),
                createColumn({
                    key: 'used',
                    label: 'Assigned',
                    sortKey: 'used',
                    headerClass: 'text-right',
                    cellClass: 'text-right text-gray-600',
                    value: (asset) => asset.used ?? '-',
                }),
            ];
        case 'consumable':
        default:
            return [
                ...baseColumns,
                createColumn({
                    key: 'serial',
                    label: 'Model No',
                    sortKey: 'serial',
                    value: (asset) => asset.serial || '',
                }),
                createColumn({
                    key: 'type_name',
                    label: 'Kategori',
                    sortKey: 'type_name',
                    value: (asset) => asset.type_name || '',
                }),
                createColumn({
                    key: 'group_name',
                    label: 'Location',
                    sortKey: 'group_name',
                    value: (asset) => asset.group_name || '',
                }),
                createColumn({
                    key: 'remaining',
                    label: 'Stock',
                    sortKey: 'stock',
                    headerClass: 'text-right',
                    cellClass: 'text-right font-bold',
                    value: (asset) => asset.remaining ?? '-',
                }),
                createColumn({
                    key: 'used',
                    label: 'Consumed',
                    sortKey: 'used',
                    headerClass: 'text-right',
                    cellClass: 'text-right text-gray-600',
                    value: (asset) => asset.used ?? '-',
                }),
            ];
    }
});

const tableColumns = computed(() =>
    isHardwareType.value ? hardwareColumns.value : nonHardwareColumns.value,
);
const emptyColspan = computed(() => tableColumns.value.length + 1);

const updateColumnFilter = (sortKeyValue: SortKey, value: string) => {
    if (value === '') {
        delete columnFilters.value[sortKeyValue];
        columnFilters.value = { ...columnFilters.value };
        return;
    }

    columnFilters.value = {
        ...columnFilters.value,
        [sortKeyValue]: value,
    };
};

const toSortableNumber = (value: unknown) => {
    if (
        value === '-' ||
        value === '' ||
        value === null ||
        value === undefined
    ) {
        return Number.NEGATIVE_INFINITY;
    }

    const parsed = Number(value);
    return Number.isNaN(parsed) ? Number.NEGATIVE_INFINITY : parsed;
};

const filteredAssets = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();
    const activeColumnFilters = Object.entries(columnFilters.value).filter(
        ([, value]) => Boolean(value),
    ) as Array<[SortKey, string]>;

    return props.assets.filter((asset) => {
        const matchesSearch =
            !query ||
            [
                asset.name,
                asset.otherserial,
                asset.serial,
                asset.type_name,
                asset.group_name,
                asset.department_name,
                asset.company_name,
                asset.holder_name,
                asset.state_name,
                asset.stock,
                asset.used,
            ].some((value) => toSearchable(value).includes(query));

        if (!matchesSearch) {
            return false;
        }

        return activeColumnFilters.every(([activeFilterKey, activeValue]) => {
            const rawValue = asset[activeFilterKey];
            return (
                String(formatCellValue(rawValue ?? '')).trim() === activeValue
            );
        });
    });
});

const sortedAssets = computed(() => {
    const items = [...filteredAssets.value];

    items.sort((left, right) => {
        const leftValue = left[sortKey.value];
        const rightValue = right[sortKey.value];
        const numericKeys: SortKey[] = ['stock', 'used'];
        const result = numericKeys.includes(sortKey.value)
            ? toSortableNumber(leftValue) - toSortableNumber(rightValue)
            : String(leftValue ?? '').localeCompare(
                  String(rightValue ?? ''),
                  undefined,
                  {
                      numeric: true,
                      sensitivity: 'base',
                  },
              );

        return sortDirection.value === 'asc' ? result : -result;
    });

    return items;
});

const totalPages = computed(() =>
    Math.max(1, Math.ceil(sortedAssets.value.length / pageSize.value)),
);
const paginatedAssets = computed(() => {
    const start = (currentPage.value - 1) * pageSize.value;
    return sortedAssets.value.slice(start, start + pageSize.value);
});
const pageStart = computed(() =>
    sortedAssets.value.length === 0
        ? 0
        : (currentPage.value - 1) * pageSize.value + 1,
);
const pageEnd = computed(() =>
    Math.min(currentPage.value * pageSize.value, sortedAssets.value.length),
);
const pageNumbers = computed(() => {
    const total = totalPages.value;

    if (total <= 5) {
        return Array.from({ length: total }, (_, index) => index + 1);
    }

    const start = Math.max(1, currentPage.value - 1);
    const end = Math.min(total, start + 2);
    const adjustedStart = Math.max(1, end - 2);

    return Array.from(
        { length: end - adjustedStart + 1 },
        (_, index) => adjustedStart + index,
    );
});

watch([searchQuery, sortKey, sortDirection, pageSize, columnFilters], () => {
    currentPage.value = 1;
});

watch(activeType, () => {
    sortKey.value = defaultSortKey.value;
    columnFilters.value = {};
    currentPage.value = 1;
});

watch(
    () => props.activeStatus,
    (value) => {
        selectedStatus.value = value === null ? 'all' : String(value);
    },
);

watch(totalPages, (value) => {
    if (currentPage.value > value) {
        currentPage.value = value;
    }
});

const toggleColumnSort = (nextSortKey: SortKey) => {
    if (sortKey.value === nextSortKey) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
        return;
    }

    sortKey.value = nextSortKey;
    sortDirection.value = 'asc';
};

const goToPreviousPage = () => {
    currentPage.value = Math.max(1, currentPage.value - 1);
};

const goToNextPage = () => {
    currentPage.value = Math.min(totalPages.value, currentPage.value + 1);
};

const setPage = (page: number) => {
    currentPage.value = Math.min(Math.max(page, 1), totalPages.value);
};

const handleStatusChange = (value: string) => {
    selectedStatus.value = value;

    const query = `?type=${encodeURIComponent(activeType.value)}`;

    if (selectedStatus.value === 'all') {
        router.get(`/asset${query}`);
        return;
    }

    router.get(`/asset/${encodeURIComponent(selectedStatus.value)}${query}`);
};

const resetFilters = () => {
    searchQuery.value = '';
    columnFilters.value = {};
    sortKey.value = defaultSortKey.value;
    sortDirection.value = 'asc';
    currentPage.value = 1;
};

const locationOptions = computed(() => {
    const locations = props.assets
        .map((asset) => String(asset.group_name || '').trim())
        .filter(Boolean);

    return [...new Set(locations)].sort((left, right) =>
        left.localeCompare(right, undefined, {
            sensitivity: 'base',
        }),
    );
});

const selectedLocation = computed(() => columnFilters.value.group_name || '');
const categoryOptions = computed(() => {
    const categories = props.assets
        .map((asset) => String(asset.type_name || '').trim())
        .filter(Boolean);

    return [...new Set(categories)].sort((left, right) =>
        left.localeCompare(right, undefined, {
            sensitivity: 'base',
        }),
    );
});
const selectedCategory = computed(() => columnFilters.value.type_name || '');

const handleLocationChange = (value: string) => {
    updateColumnFilter('group_name', value);
};

const handleCategoryChange = (value: string) => {
    updateColumnFilter('type_name', value);
};

const csvFileName = computed(() => {
    const suffix = String(pageTitle.value || 'asset')
        .trim()
        .toLowerCase()
        .replace(/\s+/g, '-');

    return `${suffix}-directory.csv`;
});

const escapeCsvValue = (value: string | number) => {
    const normalized = String(value ?? '').replace(/"/g, '""');
    return `"${normalized}"`;
};

const downloadCsv = () => {
    const headers = tableColumns.value.map((column) => column.label);
    const rows = sortedAssets.value.map((asset) =>
        tableColumns.value.map((column) =>
            escapeCsvValue(formatCellValue(column.value(asset))),
        ),
    );

    const csv = [headers.join(','), ...rows.map((row) => row.join(','))].join(
        '\n',
    );

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = url;
    link.download = csvFileName.value;
    link.click();
    window.URL.revokeObjectURL(url);
};

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Assets', href: '/asset' },
    {
        title: pageTitle.value,
        href: `/asset?type=${encodeURIComponent(activeType.value)}`,
        help:
            activeType.value === 'component'
                ? 'Component adalah bagian yang dipasang pada hardware, seperti RAM, SSD, atau processor.'
                : activeType.value === 'accessories'
                  ? 'Accessories adalah perangkat tambahan yang dapat dipinjam langsung oleh user, seperti mouse, keyboard, atau headset.'
                  : undefined,
    },
]);

const createHref = computed(
    () => `/asset/create?type=${encodeURIComponent(activeType.value)}`,
);
</script>

<template>
    <Head :title="pageHeading" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="app-page-shell">
            <AssetListTableSection
                :page-title="pageTitle"
                :show-status-filter="showStatusFilter"
                :statuses="statuses"
                :selected-status="selectedStatus"
                :category-options="categoryOptions"
                :selected-category="selectedCategory"
                :location-options="locationOptions"
                :selected-location="selectedLocation"
                :search-query="searchQuery"
                :add-button-label="addButtonLabel"
                :create-href="createHref"
                :download-csv="downloadCsv"
                :table-columns="tableColumns"
                :paginated-assets="paginatedAssets"
                :empty-colspan="emptyColspan"
                :sort-key="sortKey"
                :sort-direction="sortDirection"
                :column-filters="columnFilters"
                :page-start="pageStart"
                :page-end="pageEnd"
                :total-rows="sortedAssets.length"
                :page-size="pageSize"
                :current-page="currentPage"
                :total-pages="totalPages"
                :page-numbers="pageNumbers"
                :is-stock-type="isStockType"
                :can-show-generate-stb-in="canShowGenerateStbIn"
                :can-show-generate-stb-out="canShowGenerateStbOut"
                :can-show-generate-loan="canShowGenerateLoan"
                :can-show-return-loan="canShowReturnLoan"
                :get-detail-href="getDetailHref"
                :get-edit-href="getEditHref"
                :format-cell-value="formatCellValue"
                :toggle-column-sort="toggleColumnSort"
                :handle-status-change="handleStatusChange"
                :handle-category-change="handleCategoryChange"
                :handle-location-change="handleLocationChange"
                :reset-filters="resetFilters"
                :go-to-previous-page="goToPreviousPage"
                :go-to-next-page="goToNextPage"
                :set-page="setPage"
                v-model:selected-ids="selectedIds"
                @update:search-query="searchQuery = $event"
                @update:page-size="pageSize = $event"
                @add-stock="openStockModal"
                @show-detail="openDetail"
                @handover="handleHandover"
                @loan="handleLoan"
                @return-loan="handleReturnLoan"
                @delete="deleteAsset"
            />
        </div>
    </AppLayout>

    <HandoverModal
        v-if="metadata"
        :show="showHandoverModal"
        :selected-items="props.assets.filter((a) => selectedIds.includes(a.id))"
        :asset-type="activeType"
        :metadata="metadata"
        @close="showHandoverModal = false"
        @success="handleHandoverSuccess"
    />

    <ReturnLoanModal
        :show="showReturnLoanModal"
        :selected-assets="returnLoanSelectedItems"
        :loan-references="props.loanReferences"
        @close="showReturnLoanModal = false"
        @select="handleReturnLoanSelect"
    />

    <AddStockModal
        v-if="stockAsset"
        :show="showStockModal"
        :asset-id="stockAsset.id"
        :asset-type="activeType"
        @close="
            showStockModal = false;
            stockAsset = null;
        "
    />
</template>
