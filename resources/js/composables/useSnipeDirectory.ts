import axios from 'axios';
import { reactive, ref } from 'vue';

export type SnipeAssetCategory =
    | 'assets'
    | 'license'
    | 'accessories'
    | 'consumable'
    | 'component'
    | 'asset'
    | 'computer'
    | 'monitor'
    | 'network'
    | 'consumables'
    | 'cctv'
    | 'nvr';

export interface SnipeUser {
    id: number;
    name: string;
    username: string;
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    jobtitle: string;
    title_name: string;
    manager_id: number | null;
    manager_name: string;
    location_id: number | null;
    location_name: string;
    department_id: number | null;
    department_name: string;
    company_id: number | null;
    company_name: string;
}

export interface SnipeGroup {
    id: number;
    name: string;
    completename: string;
}

export interface SnipeAsset {
    id: number;
    name: string;
    serial: string;
    otherserial: string;
    state?: number;
    state_name: string;
    group_name: string;
    type_name: string;
    stock: string | number;
    remaining?: number | null;
    used: string | number;
    asset_type: SnipeAssetCategory;
    asset_type_label: string;
    users_id: number | null;
    location_name: string;
}

const ASSET_CATEGORIES: SnipeAssetCategory[] = [
    'assets',
    'license',
    'accessories',
    'consumable',
    'component',
    'asset',
    'computer',
    'monitor',
    'network',
    'consumables',
    'cctv',
    'nvr',
];

const users = ref<SnipeUser[]>([]);
const groups = ref<SnipeGroup[]>([]);
const assets = reactive<Record<SnipeAssetCategory, SnipeAsset[]>>({
    assets: [],
    license: [],
    accessories: [],
    consumable: [],
    component: [],
    asset: [],
    computer: [],
    monitor: [],
    network: [],
    consumables: [],
    cctv: [],
    nvr: [],
});
const directoryLoaded = ref(false);
const directoryLoading = ref(false);
const assetLoaded = reactive<Record<SnipeAssetCategory, boolean>>({
    assets: false,
    license: false,
    accessories: false,
    consumable: false,
    component: false,
    asset: false,
    computer: false,
    monitor: false,
    network: false,
    consumables: false,
    cctv: false,
    nvr: false,
});
const assetLoading = reactive<Record<SnipeAssetCategory, boolean>>({
    assets: false,
    license: false,
    accessories: false,
    consumable: false,
    component: false,
    asset: false,
    computer: false,
    monitor: false,
    network: false,
    consumables: false,
    cctv: false,
    nvr: false,
});

const ASSET_CACHE_TTL_MS = 5 * 60 * 1000; // 5 minutes
const assetLoadedAt: Partial<Record<SnipeAssetCategory, number>> = {};

let directoryRequest: Promise<void> | null = null;
const assetRequests: Partial<Record<SnipeAssetCategory, Promise<void>>> = {};

export const normalizeSnipeAssetCategory = (
    value: string | null | undefined,
): SnipeAssetCategory => {
    const normalized = String(value || 'assets').toLowerCase();

    if (normalized === 'asset' || normalized === 'hardware') {
        return 'assets';
    }

    if (normalized === 'licenses') {
        return 'license';
    }

    if (normalized === 'accessory') {
        return 'accessories';
    }

    if (normalized === 'components') {
        return 'component';
    }

    if (normalized === 'consumables') {
        return 'consumable';
    }

    if (ASSET_CATEGORIES.includes(normalized as SnipeAssetCategory)) {
        return normalized as SnipeAssetCategory;
    }

    return 'assets';
};

export const ensureSnipeDirectoryLoaded = async (force = false) => {
    if (directoryLoaded.value && !force) {
        return;
    }

    if (directoryRequest && !force) {
        return directoryRequest;
    }

    directoryLoading.value = true;

    directoryRequest = Promise.all([
        axios.get<SnipeUser[]>('/api/snipeit/users'),
        axios.get<SnipeGroup[]>('/api/snipeit/groups'),
    ])
        .then(([usersResponse, groupsResponse]) => {
            users.value = Array.isArray(usersResponse.data)
                ? usersResponse.data
                : [];
            groups.value = Array.isArray(groupsResponse.data)
                ? groupsResponse.data
                : [];
            directoryLoaded.value = true;
        })
        .finally(() => {
            directoryLoading.value = false;
            directoryRequest = null;
        });

    return directoryRequest;
};

export const ensureSnipeAssetsLoaded = async (
    type: string | null | undefined,
    force = false,
) => {
    const category = normalizeSnipeAssetCategory(type);

    const isStale =
        !assetLoadedAt[category] ||
        Date.now() - assetLoadedAt[category]! > ASSET_CACHE_TTL_MS;

    if (assetLoaded[category] && !force && !isStale) {
        return;
    }

    if (assetRequests[category] && !force) {
        return assetRequests[category];
    }

    assetLoading[category] = true;

    const endpointCategory = category;

    assetRequests[category] = axios
        .get<SnipeAsset[]>(`/api/snipeit/assets/${endpointCategory}`)
        .then((response) => {
            assets[category] = Array.isArray(response.data)
                ? response.data
                : [];
            assetLoaded[category] = true;
            assetLoadedAt[category] = Date.now();
        })
        .finally(() => {
            assetLoading[category] = false;
            delete assetRequests[category];
        });

    return assetRequests[category];
};

export const getSnipeUserById = (id: number | string | null | undefined) => {
    if (!id) return null;
    const numericId = Number(id);
    return users.value.find((u) => u.id === numericId) || null;
};

export const getSnipeGroupById = (id: number | null | undefined) =>
    groups.value.find((group) => group.id === Number(id)) ?? null;

export const getSnipeAssetById = (
    id: number | null | undefined,
    type?: string | null,
) => {
    const assetId = Number(id);

    if (!assetId) {
        return null;
    }

    if (type) {
        const category = normalizeSnipeAssetCategory(type);

        return assets[category].find((asset) => asset.id === assetId) ?? null;
    }

    for (const category of ASSET_CATEGORIES) {
        const match = assets[category].find((asset) => asset.id === assetId);

        if (match) {
            return match;
        }
    }

    return null;
};

export const getSnipeGroupParts = (
    groupId: number | null | undefined,
    userId?: number | null | undefined,
) => {
    const user = getSnipeUserById(userId);
    const group = getSnipeGroupById(groupId ?? user?.location_id ?? null);

    return {
        company: user?.company_name || '-',
        location: group?.name || user?.location_name || '-',
        department: user?.department_name || '-',
    };
};

export const getSnipeUserLabel = (userId: number | null | undefined) =>
    getSnipeUserById(userId)?.name || (userId ? `User #${userId}` : '-');

export const getSnipeUserPhone = (userId: number | null | undefined) =>
    getSnipeUserById(userId)?.phone || '-';

export const getSnipeUserEmail = (userId: number | null | undefined) =>
    getSnipeUserById(userId)?.email || '-';

export const getSnipeUserPosition = (userId: number | null | undefined) =>
    getSnipeUserById(userId)?.title_name ||
    getSnipeUserById(userId)?.jobtitle ||
    '-';

export const getSnipeDeptHeadLabel = (userId: number | null | undefined) =>
    getSnipeUserById(userId)?.manager_name || '-';

export const getSnipeAssetReferenceLabel = (
    type: string | null | undefined,
) => {
    switch (normalizeSnipeAssetCategory(type)) {
        case 'assets':
            return 'Asset Tag';
        case 'license':
            return 'Product Key';
        case 'accessories':
        case 'consumable':
            return 'Model No';
        case 'component':
            return 'Serial';
        default:
            return 'Reference';
    }
};

export const getSnipeAssetReferenceValue = (
    asset: Pick<SnipeAsset, 'serial' | 'otherserial' | 'asset_type'>,
) => {
    return asset.otherserial || asset.serial || '';
};

export const getSnipeAssetLabel = (item: {
    asset_reference_snapshot?: string | null;
    inventory_number?: string | null;
    computer_id?: number | null;
    kategori?: string | null;
    serial_no?: string | null;
    nama?: string | null;
}) => {
    const snapshotReference = String(
        item.asset_reference_snapshot ?? '',
    ).trim();

    if (snapshotReference !== '') {
        return snapshotReference;
    }

    const inventoryNumber = String(item.inventory_number ?? '').trim();
    if (inventoryNumber === '[USER]') {
        return 'Langsung ke User';
    }

    if (inventoryNumber !== '') {
        return inventoryNumber;
    }

    const serialNumber = String(item.serial_no ?? '').trim();

    if (serialNumber !== '') {
        return serialNumber;
    }

    const itemName = String(item.nama ?? '').trim();

    if (itemName !== '') {
        return itemName;
    }

    const asset = getSnipeAssetById(item.computer_id, item.kategori);

    if (asset) {
        const reference = getSnipeAssetReferenceValue(asset);

        if (reference) {
            return reference;
        }
    }

    if (asset?.name) {
        return asset.name;
    }

    return item.computer_id ? `Asset #${item.computer_id}` : '-';
};

// ── Per-user asset cache ──────────────────────────────────────────────────
const USER_ASSET_CACHE_TTL_MS = 3 * 60 * 1000; // 3 minutes
const userAssetCache: Record<string, { data: SnipeAsset[]; loadedAt: number }> =
    {};
const userAssetRequests: Record<string, Promise<SnipeAsset[]>> = {};

export const fetchUserAssets = async (
    userId: number,
    type: string = 'assets',
    force = false,
): Promise<SnipeAsset[]> => {
    if (!userId) return [];

    const cacheKey = `${userId}:${type}`;
    const cached = userAssetCache[cacheKey];

    // Return cached if still fresh
    if (
        !force &&
        cached &&
        Date.now() - cached.loadedAt < USER_ASSET_CACHE_TTL_MS
    ) {
        return cached.data;
    }

    // Deduplicate in-flight requests
    if (userAssetRequests[cacheKey] !== undefined) {
        return userAssetRequests[cacheKey];
    }

    userAssetRequests[cacheKey] = axios
        .get<SnipeAsset[]>(`/api/snipeit/users/${userId}/assets/${type}`)
        .then((response) => {
            const data = Array.isArray(response.data) ? response.data : [];
            userAssetCache[cacheKey] = { data, loadedAt: Date.now() };
            return data;
        })
        .catch((error) => {
            console.error(`Failed to fetch user ${type}:`, error);
            return [];
        })
        .finally(() => {
            delete userAssetRequests[cacheKey];
        });

    return userAssetRequests[cacheKey];
};

export const useSnipeDirectory = () => ({
    users,
    groups,
    assets,
    directoryLoaded,
    directoryLoading,
    assetLoaded,
    assetLoading,
    ensureDirectoryLoaded: ensureSnipeDirectoryLoaded,
    ensureAssetsLoaded: ensureSnipeAssetsLoaded,
    getUserById: getSnipeUserById,
    getGroupById: getSnipeGroupById,
    getAssetById: getSnipeAssetById,
    fetchUserAssets,
});
