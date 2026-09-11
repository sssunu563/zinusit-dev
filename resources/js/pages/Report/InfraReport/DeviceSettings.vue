<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref, onMounted } from 'vue';
import {
    ArrowLeft,
    Check,
    Loader2,
    Pencil,
    Search,
    Server,
    Video,
    Wifi,
    X,
    Gauge,
    AlertTriangle,
    CheckCircle2,
    SlidersHorizontal,
    Plus,
    Clock,
    Eye,
    EyeOff,
    Trash2,
    Save,
    RotateCcw,
    Shield,
} from 'lucide-vue-next';
import axios from 'axios';
import AppLayout from '@/layouts/AppLayout.vue';

interface Device {
    id: number;
    type: string;
    device_name: string;
    ip_address: string | null;
    site: string | null;
    location: string | null;
    host_group: string | null;
    included: boolean;
}

interface BandwidthContract {
    id: number;
    location: string;
    fct: string;
    provider: string;
    bandwidth: number;
    target_pct: number;
    is_active: boolean;
    sort_order: number;
}

interface FailedDevice {
    id: number | null;
    device_id: number;
    device_name: string;
    ip_address: string | null;
    site: string | null;
    category: string;
    type: string;
    is_excluded: boolean;
    uptime_percent: number;
    started_at: string | null;
    resolved_at: string | null;
    event_type: string;
    status: string;
    remark: string;
}

const props = defineProps<{
    devices: Record<string, Device[]>;
    bandwidthContracts?: BandwidthContract[];
    failedDevices?: FailedDevice[];
}>();

// Read active tab from URL query (?tab=failed, ?tab=bandwidth, ?tab=devices)
const activeTab = ref<'devices' | 'bandwidth' | 'failed'>('devices');

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    const tab = params.get('tab');
    if (tab === 'bandwidth' || tab === 'failed' || tab === 'devices') {
        activeTab.value = tab;
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// Reactive State
// ─────────────────────────────────────────────────────────────────────────────
const localDevices = ref<Record<string, Device[]>>(
    Object.fromEntries(
        Object.entries(props.devices || {}).map(([key, value]) => [key, [...value]]),
    ),
);

const localContracts = ref<BandwidthContract[]>([
    ...(props.bandwidthContracts || []),
]);

const localFailedDevices = ref<FailedDevice[]>([
    ...(props.failedDevices || []),
]);

const feedbackMsg = ref<{ type: 'success' | 'error'; text: string } | null>(null);
function notify(text: string, type: 'success' | 'error' = 'success') {
    feedbackMsg.value = { type, text };
    setTimeout(() => {
        if (feedbackMsg.value?.text === text) feedbackMsg.value = null;
    }, 4000);
}

// ─────────────────────────────────────────────────────────────────────────────
// TAB 1: PERANGKAT & UPTIME
// ─────────────────────────────────────────────────────────────────────────────
const deviceSearch = ref('');
const selectedSiteFilter = ref('all');
const activeCategoryFilter = ref('all');
const savingDeviceId = ref<string | null>(null);

const categories = [
    { key: 'network', label: 'Network', icon: Wifi, badge: 'bg-blue-50 text-blue-700 border-blue-200' },
    { key: 'nvr', label: 'NVR', icon: Server, badge: 'bg-violet-50 text-violet-700 border-violet-200' },
    { key: 'cctv', label: 'CCTV', icon: Video, badge: 'bg-sky-50 text-sky-700 border-sky-200' },
    { key: 'server', label: 'Server', icon: Server, badge: 'bg-orange-50 text-orange-700 border-orange-200' },
];

const allSites = computed(() => {
    const set = new Set<string>();
    Object.values(localDevices.value)
        .flat()
        .forEach((d) => {
            if (d.site) set.add(d.site);
        });
    return Array.from(set).sort();
});

// Matrix row summary for Site x Metric
interface MatrixRow {
    site: string;
    categoryKey: string;
    categoryLabel: string;
    totalCount: number;
    includedCount: number;
    percent: number;
}

const matrixRows = computed<MatrixRow[]>(() => {
    const out: MatrixRow[] = [];
    allSites.value.forEach((site) => {
        categories.forEach((cat) => {
            // Hanya hitung device yang included (bukan yang excluded)
            const includedList = (localDevices.value[cat.key] || []).filter(
                (d) => d.site === site && d.included,
            );
            const total = includedList.length;
            const inc = includedList.length;
            const pct = 100; // Selalu 100% karena kita hanya menghitung yang included

            out.push({
                site,
                categoryKey: cat.key,
                categoryLabel: cat.label,
                totalCount: total,
                includedCount: inc,
                percent: pct,
            });
        });
    });
    return out;
});

// Manage Devices Modal
const showManageModal = ref(false);
const modalSite = ref('');
const modalCategoryKey = ref('network');
const modalCategoryLabel = ref('');
const modalSearch = ref('');
const modalSelectedIds = ref<number[]>([]);
const modalSaving = ref(false);

const modalDevices = computed(() => {
    const list = localDevices.value[modalCategoryKey.value] || [];
    return list.filter((d) => d.site === modalSite.value);
});

const filteredModalDevices = computed(() => {
    const q = modalSearch.value.trim().toLowerCase();
    if (!q) return modalDevices.value;
    return modalDevices.value.filter(
        (d) =>
            d.device_name.toLowerCase().includes(q) ||
            (d.ip_address && d.ip_address.toLowerCase().includes(q)) ||
            (d.host_group && d.host_group.toLowerCase().includes(q)),
    );
});

function openManageModal(row: MatrixRow) {
    modalSite.value = row.site;
    modalCategoryKey.value = row.categoryKey;
    modalCategoryLabel.value = row.categoryLabel;
    modalSearch.value = '';

    const list = localDevices.value[row.categoryKey] || [];
    modalSelectedIds.value = list
        .filter((d) => d.site === row.site && d.included)
        .map((d) => d.id);

    showManageModal.value = true;
}

function selectAllModal() {
    modalSelectedIds.value = modalDevices.value.map((d) => d.id);
}

function deselectAllModal() {
    modalSelectedIds.value = [];
}

async function saveModalDevices() {
    modalSaving.value = true;
    try {
        const payload = {
            type: modalCategoryKey.value,
            site: modalSite.value,
            included_ids: modalSelectedIds.value,
        };
        const res = await axios.post('/infra-report/device-settings/batch', payload);
        if (res.data.success) {
            const list = localDevices.value[modalCategoryKey.value] || [];
            list.forEach((d) => {
                if (d.site === modalSite.value) {
                    d.included = modalSelectedIds.value.includes(d.id);
                }
            });
            notify(`Pengaturan perangkat ${modalSite.value} (${modalCategoryLabel.value}) berhasil disimpan.`);
            showManageModal.value = false;
        }
    } catch (err: any) {
        alert('Gagal menyimpan perangkat: ' + (err.response?.data?.message || err.message));
    } finally {
        modalSaving.value = false;
    }
}

// Flat device list filtering & toggle
const visibleFlatDevices = computed(() => {
    const q = deviceSearch.value.trim().toLowerCase();
    const cat = activeCategoryFilter.value;
    const site = selectedSiteFilter.value;

    let pool: Device[] = [];
    if (cat === 'all') {
        pool = Object.values(localDevices.value).flat();
    } else {
        pool = localDevices.value[cat] || [];
    }

    return pool.filter((d) => {
        const matchSite = site === 'all' || d.site === site;
        const matchQuery =
            !q ||
            d.device_name.toLowerCase().includes(q) ||
            (d.ip_address && d.ip_address.toLowerCase().includes(q)) ||
            (d.site && d.site.toLowerCase().includes(q)) ||
            (d.host_group && d.host_group.toLowerCase().includes(q));
        return matchSite && matchQuery;
    });
});

async function toggleDevice(device: Device) {
    const key = `${device.type}-${device.id}`;
    const nextVal = !device.included;
    savingDeviceId.value = key;
    device.included = nextVal;

    try {
        await axios.put(`/infra-report/device-settings/${device.type}/${device.id}`, {
            included: nextVal,
        });
        notify(
            `${device.device_name} sekarang ${nextVal ? 'diikutsertakan' : 'dikeluarkan dari laporan'}.`,
        );
    } catch (e: any) {
        device.included = !nextVal;
        alert('Gagal mengubah status: ' + (e.response?.data?.message || e.message));
    } finally {
        savingDeviceId.value = null;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// TAB 2: KAPASITAS BANDWIDTH (ISP SLA)
// ─────────────────────────────────────────────────────────────────────────────
const showBandwidthModal = ref(false);
const editingContract = ref<BandwidthContract | null>(null);
const bandwidthForm = ref({
    id: 0,
    bandwidth: 100,
    target_pct: 99.5,
});
const bandwidthSaving = ref(false);

function openEditBandwidth(contract: BandwidthContract) {
    editingContract.value = contract;
    bandwidthForm.value = {
        id: contract.id,
        bandwidth: Number(contract.bandwidth),
        target_pct: Number(contract.target_pct || 99.5),
    };
    showBandwidthModal.value = true;
}

async function saveBandwidth() {
    bandwidthSaving.value = true;
    try {
        const res = await axios.post('/infra-report/bandwidth-capacity', bandwidthForm.value);
        if (res.data.success) {
            const idx = localContracts.value.findIndex((c) => c.id === bandwidthForm.value.id);
            if (idx !== -1) {
                localContracts.value[idx].bandwidth = bandwidthForm.value.bandwidth;
                localContracts.value[idx].target_pct = bandwidthForm.value.target_pct;
            }
            notify(`Kapasitas SLA ${editingContract.value?.provider} (${editingContract.value?.location}) berhasil diperbarui.`);
            showBandwidthModal.value = false;
        }
    } catch (e: any) {
        alert('Gagal menyimpan bandwidth: ' + (e.response?.data?.message || e.message));
    } finally {
        bandwidthSaving.value = false;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// TAB 3: FAILED DEVICES & CATATAN DOWNTIME
// ─────────────────────────────────────────────────────────────────────────────
const failedSearch = ref('');
const showDowntimeModal = ref(false);
const downtimeSaving = ref(false);
const downtimeForm = ref({
    id: null as number | null,
    device_type: 'network',
    device_id: 0,
    device_name: '',
    ip_address: '',
    site: '',
    category: 'Network',
    started_at: '',
    resolved_at: '',
    event_type: 'maintenance',
    status: 'closed',
    notes: '',
});

const isNewDowntimeEntry = ref(false);
const devicePickerSearch = ref('');

const allAvailableDevices = computed(() => {
    return Object.values(localDevices.value).flat();
});

const filteredPickerDevices = computed(() => {
    const q = devicePickerSearch.value.trim().toLowerCase();
    if (!q) return allAvailableDevices.value.slice(0, 30);
    return allAvailableDevices.value
        .filter(
            (d) =>
                d.device_name.toLowerCase().includes(q) ||
                (d.ip_address && d.ip_address.toLowerCase().includes(q)) ||
                (d.site && d.site.toLowerCase().includes(q)),
        )
        .slice(0, 30);
});

function openEditDowntime(item: FailedDevice) {
    isNewDowntimeEntry.value = false;
    downtimeForm.value = {
        id: item.id,
        device_type: item.type,
        device_id: item.device_id,
        device_name: item.device_name,
        ip_address: item.ip_address || '-',
        site: item.site || '-',
        category: item.category,
        started_at: item.started_at || new Date().toISOString().slice(0, 16),
        resolved_at: item.resolved_at || '',
        event_type: item.event_type || 'maintenance',
        status: item.status || 'closed',
        notes: item.remark || '',
    };
    showDowntimeModal.value = true;
}

function openCreateDowntime() {
    isNewDowntimeEntry.value = true;
    devicePickerSearch.value = '';
    const firstDev = allAvailableDevices.value[0];
    downtimeForm.value = {
        id: null,
        device_type: firstDev?.type || 'network',
        device_id: firstDev?.id || 0,
        device_name: firstDev?.device_name || '',
        ip_address: firstDev?.ip_address || '-',
        site: firstDev?.site || '-',
        category: firstDev?.type?.toUpperCase() || 'Network',
        started_at: new Date().toISOString().slice(0, 16),
        resolved_at: '',
        event_type: 'maintenance',
        status: 'closed',
        notes: '',
    };
    showDowntimeModal.value = true;
}

function onSelectPickerDevice(dev: Device) {
    downtimeForm.value.device_id = dev.id;
    downtimeForm.value.device_type = dev.type;
    downtimeForm.value.device_name = dev.device_name;
    downtimeForm.value.ip_address = dev.ip_address || '-';
    downtimeForm.value.site = dev.site || '-';
    downtimeForm.value.category = dev.type.toUpperCase();
}

async function saveDowntimeLog() {
    if (!downtimeForm.value.device_id) {
        alert('Pilih perangkat terlebih dahulu.');
        return;
    }
    if (!downtimeForm.value.started_at) {
        alert('Waktu mulai wajib diisi.');
        return;
    }

    downtimeSaving.value = true;
    try {
        const payload = {
            id: downtimeForm.value.id,
            device_type: downtimeForm.value.device_type,
            device_id: downtimeForm.value.device_id,
            started_at: downtimeForm.value.started_at,
            resolved_at: downtimeForm.value.resolved_at || null,
            event_type: downtimeForm.value.event_type,
            status: downtimeForm.value.resolved_at ? 'closed' : downtimeForm.value.status,
            notes: downtimeForm.value.notes,
        };

        const res = await axios.post('/infra-report/maintenance-log', payload);
        if (res.data.success) {
            // Update localFailedDevices
            const target = localFailedDevices.value.find(
                (f) =>
                    f.device_id === downtimeForm.value.device_id &&
                    f.type === downtimeForm.value.device_type,
            );
            if (target) {
                target.id = res.data.log.id;
                target.started_at = downtimeForm.value.started_at;
                target.resolved_at = downtimeForm.value.resolved_at;
                target.event_type = downtimeForm.value.event_type;
                target.status = downtimeForm.value.status;
                target.remark = downtimeForm.value.notes;
            } else {
                localFailedDevices.value.unshift({
                    id: res.data.log.id,
                    device_id: downtimeForm.value.device_id,
                    device_name: downtimeForm.value.device_name,
                    ip_address: downtimeForm.value.ip_address,
                    site: downtimeForm.value.site,
                    category: downtimeForm.value.category,
                    type: downtimeForm.value.device_type,
                    is_excluded: false,
                    uptime_percent: 0,
                    started_at: downtimeForm.value.started_at,
                    resolved_at: downtimeForm.value.resolved_at,
                    event_type: downtimeForm.value.event_type,
                    status: downtimeForm.value.status,
                    remark: downtimeForm.value.notes,
                });
            }

            notify(`Alasan downtime untuk ${downtimeForm.value.device_name} berhasil disimpan.`);
            showDowntimeModal.value = false;
        }
    } catch (e: any) {
        alert('Gagal menyimpan log downtime: ' + (e.response?.data?.message || e.message));
    } finally {
        downtimeSaving.value = false;
    }
}

async function toggleHideFailedDevice(item: FailedDevice) {
    const nextExcluded = !item.is_excluded;
    item.is_excluded = nextExcluded;

    try {
        await axios.put(`/infra-report/device-settings/${item.type}/${item.device_id}`, {
            included: !nextExcluded,
        });

        // Also update localDevices
        const pool = localDevices.value[item.type] || [];
        const dev = pool.find((d) => d.id === item.device_id);
        if (dev) dev.included = !nextExcluded;

        notify(
            `${item.device_name} sekarang ${nextExcluded ? 'disembunyikan (Excluded)' : 'ditampilkan di laporan'}.`,
        );
    } catch (e: any) {
        item.is_excluded = !nextExcluded;
        alert('Gagal mengubah visibilitas: ' + (e.response?.data?.message || e.message));
    }
}

const visibleFailedDevices = computed(() => {
    const q = failedSearch.value.trim().toLowerCase();
    if (!q) return localFailedDevices.value;
    return localFailedDevices.value.filter(
        (d) =>
            d.device_name.toLowerCase().includes(q) ||
            (d.ip_address && d.ip_address.toLowerCase().includes(q)) ||
            (d.site && d.site.toLowerCase().includes(q)) ||
            (d.remark && d.remark.toLowerCase().includes(q)),
    );
});
</script>

<template>
    <Head title="Infra Report Settings" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Report', href: '/reports' },
            { title: 'Infra Report', href: '/infra-report' },
            { title: 'Pengaturan', href: '/infra-report/device-settings' },
        ]"
    >
        <div class="min-h-screen bg-slate-50/60 px-4 py-8 md:px-8">
            <div class="mx-auto max-w-7xl space-y-6">
                <!-- TOP BANNER & HEADER -->
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200 pb-5">
                    <div>
                        <div class="flex items-center gap-2">
                            <Link
                                href="/infra-report"
                                class="inline-flex items-center gap-1 text-xs font-black text-slate-500 hover:text-[#003628] uppercase transition-colors"
                            >
                                <ArrowLeft class="size-3.5" /> Kembali ke Dashboard
                            </Link>
                        </div>
                        <h1 class="mt-2 text-2xl md:text-3xl font-black tracking-tight text-slate-900">
                            Pengaturan Weekly Infra Report
                        </h1>
                        <p class="mt-1 text-xs font-medium text-slate-500">
                            Kelola pemilihan perangkat uptime per branch, kapasitas bandwidth SLA, dan remark alasan downtime.
                        </p>
                    </div>

                    <!-- TOAST NOTIFICATION -->
                    <div
                        v-if="feedbackMsg"
                        class="flex items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-bold shadow-sm transition-all"
                        :class="
                            feedbackMsg.type === 'success'
                                ? 'border border-emerald-200 bg-emerald-50 text-emerald-800'
                                : 'border border-rose-200 bg-rose-50 text-rose-800'
                        "
                    >
                        <CheckCircle2 v-if="feedbackMsg.type === 'success'" class="size-4 text-emerald-600" />
                        <AlertTriangle v-else class="size-4 text-rose-600" />
                        <span>{{ feedbackMsg.text }}</span>
                    </div>
                </div>

                <!-- MAIN TABS -->
                <div class="flex items-center gap-2 border-b border-slate-200">
                    <button
                        type="button"
                        @click="activeTab = 'devices'"
                        class="flex items-center gap-2 border-b-2 px-5 py-3 text-xs font-black tracking-wider uppercase transition-all"
                        :class="
                            activeTab === 'devices'
                                ? 'border-[#003628] text-[#003628]'
                                : 'border-transparent text-slate-400 hover:text-slate-700'
                        "
                    >
                        <SlidersHorizontal class="size-4" />
                        List Perangkat &amp; Uptime
                    </button>
                    <button
                        type="button"
                        @click="activeTab = 'bandwidth'"
                        class="flex items-center gap-2 border-b-2 px-5 py-3 text-xs font-black tracking-wider uppercase transition-all"
                        :class="
                            activeTab === 'bandwidth'
                                ? 'border-[#003628] text-[#003628]'
                                : 'border-transparent text-slate-400 hover:text-slate-700'
                        "
                    >
                        <Gauge class="size-4" />
                        Kapasitas Bandwidth SLA
                    </button>
                    <button
                        type="button"
                        @click="activeTab = 'failed'"
                        class="flex items-center gap-2 border-b-2 px-5 py-3 text-xs font-black tracking-wider uppercase transition-all"
                        :class="
                            activeTab === 'failed'
                                ? 'border-[#991B1B] text-[#991B1B]'
                                : 'border-transparent text-slate-400 hover:text-slate-700'
                        "
                    >
                        <AlertTriangle class="size-4" />
                        Failed Devices &amp; Catatan Downtime
                    </button>
                </div>

                <!-- ═════════════════════════════════════════════════════════════ -->
                <!-- TAB 1 CONTENT: PERANGKAT & UPTIME                             -->
                <!-- ═════════════════════════════════════════════════════════════ -->
                <div v-if="activeTab === 'devices'" class="space-y-6">
                    <!-- SECTION 1A: Branch Matrix -->
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
                            <div>
                                <h2 class="text-base font-black text-slate-900">
                                    Pemilihan Perangkat per Branch &amp; Kategori
                                </h2>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    Klik "Kelola Perangkat" pada branch dan kategori yang ingin disesuaikan (misal: Bogor &rarr; Network) untuk memilih device yang dihitung uptimenya.
                                </p>
                            </div>
                        </div>

                        <div class="overflow-x-auto rounded-xl border border-slate-100">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="bg-[#003628] text-white">
                                        <th class="px-5 py-3 font-black uppercase">Branch / Site</th>
                                        <th class="px-4 py-3 font-black uppercase">Kategori</th>
                                        <th class="px-4 py-3 font-black uppercase text-center">Status Perangkat</th>
                                        <th class="px-4 py-3 font-black uppercase text-center">Persentase Aktif</th>
                                        <th class="px-4 py-3 font-black uppercase text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr
                                        v-for="row in matrixRows"
                                        :key="`${row.site}-${row.categoryKey}`"
                                        class="hover:bg-slate-50/80 transition-colors"
                                    >
                                        <td class="px-5 py-3 font-bold text-slate-900">
                                            <span class="inline-flex items-center gap-2">
                                                <span class="h-2 w-2 rounded-full bg-emerald-600"></span>
                                                {{ row.site }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 font-bold text-slate-700">
                                            <span
                                                class="rounded border px-2.5 py-0.5 text-[10px] font-extrabold uppercase"
                                                :class="
                                                    categories.find((c) => c.key === row.categoryKey)?.badge ||
                                                    'border-slate-200 text-slate-600'
                                                "
                                            >
                                                {{ row.categoryLabel }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="font-bold text-slate-800 tabular-nums">
                                                {{ row.includedCount }}
                                            </span>
                                            <span class="text-slate-400"> / {{ row.totalCount }} perangkat diikutsertakan</span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span
                                                class="rounded-full px-2.5 py-0.5 text-[10px] font-black"
                                                :class="
                                                    row.percent >= 90
                                                        ? 'bg-emerald-50 text-emerald-700'
                                                        : row.percent >= 70
                                                          ? 'bg-amber-50 text-amber-700'
                                                          : 'bg-rose-50 text-rose-700'
                                                "
                                            >
                                                {{ row.percent }}%
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <button
                                                type="button"
                                                @click="openManageModal(row)"
                                                class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-1.5 text-xs font-black text-slate-700 shadow-sm transition hover:border-[#003628] hover:text-[#003628]"
                                            >
                                                <Pencil class="size-3.5" /> Kelola Perangkat
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- SECTION 1B: Flat Device Search & Toggle -->
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                            <div>
                                <h3 class="text-base font-black text-slate-900">
                                    Semua Perangkat (Quick Switch)
                                </h3>
                                <p class="text-xs text-slate-500">
                                    Cari perangkat tertentu dan aktifkan/nonaktifkan secara langsung.
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <!-- Site filter -->
                                <select
                                    v-model="selectedSiteFilter"
                                    class="h-9 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20"
                                >
                                    <option value="all">Semua Site</option>
                                    <option v-for="s in allSites" :key="s" :value="s">{{ s }}</option>
                                </select>

                                <!-- Category filter -->
                                <select
                                    v-model="activeCategoryFilter"
                                    class="h-9 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20"
                                >
                                    <option value="all">Semua Kategori</option>
                                    <option v-for="c in categories" :key="c.key" :value="c.key">{{ c.label }}</option>
                                </select>

                                <!-- Search -->
                                <div class="relative">
                                    <Search class="absolute left-3 top-2.5 size-4 text-slate-400" />
                                    <input
                                        v-model="deviceSearch"
                                        type="text"
                                        placeholder="Cari device / IP..."
                                        class="h-9 w-48 md:w-64 rounded-xl border border-slate-200 bg-slate-50 pl-9 pr-3 text-xs font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20"
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="overflow-x-auto max-h-[480px] rounded-xl border border-slate-100">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead class="sticky top-0 bg-slate-100/90 backdrop-blur-xs">
                                    <tr>
                                        <th class="px-4 py-2.5 font-black text-slate-700 uppercase">Device Name</th>
                                        <th class="px-3 py-2.5 font-black text-slate-700 uppercase">IP Address</th>
                                        <th class="px-3 py-2.5 font-black text-slate-700 uppercase">Site / Branch</th>
                                        <th class="px-3 py-2.5 font-black text-slate-700 uppercase">Category</th>
                                        <th class="px-3 py-2.5 font-black text-slate-700 uppercase">Host Group</th>
                                        <th class="px-4 py-2.5 font-black text-slate-700 uppercase text-center">Ikut Dihitung</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr
                                        v-for="d in visibleFlatDevices"
                                        :key="`${d.type}-${d.id}`"
                                        class="hover:bg-slate-50/80 transition-colors"
                                    >
                                        <td class="px-4 py-2 font-bold text-slate-900">
                                            {{ d.device_name }}
                                        </td>
                                        <td class="px-3 py-2 font-mono text-slate-600">
                                            {{ d.ip_address || '-' }}
                                        </td>
                                        <td class="px-3 py-2 text-slate-700">
                                            {{ d.site || '-' }}
                                        </td>
                                        <td class="px-3 py-2">
                                            <span
                                                class="rounded border px-2 py-0.5 text-[9px] font-black uppercase"
                                                :class="
                                                    categories.find((c) => c.key === d.type)?.badge ||
                                                    'border-slate-200 text-slate-600'
                                                "
                                            >
                                                {{ d.type }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-slate-500 text-[11px]">
                                            {{ d.host_group || '-' }}
                                        </td>
                                        <td class="px-4 py-2 text-center">
                                            <button
                                                type="button"
                                                :disabled="savingDeviceId === `${d.type}-${d.id}`"
                                                @click="toggleDevice(d)"
                                                class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[10px] font-black uppercase transition-all"
                                                :class="
                                                    d.included
                                                        ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200'
                                                        : 'bg-slate-100 text-slate-400 hover:bg-slate-200'
                                                "
                                            >
                                                <Loader2
                                                    v-if="savingDeviceId === `${d.type}-${d.id}`"
                                                    class="size-3 animate-spin"
                                                />
                                                <template v-else>
                                                    <Check v-if="d.included" class="size-3 text-emerald-600" />
                                                    <X v-else class="size-3 text-slate-400" />
                                                    <span>{{ d.included ? 'Included' : 'Excluded' }}</span>
                                                </template>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="!visibleFlatDevices.length">
                                        <td colspan="6" class="px-4 py-8 text-center text-slate-400 italic">
                                            Tidak ada perangkat yang sesuai filter pencarian.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ═════════════════════════════════════════════════════════════ -->
                <!-- TAB 2 CONTENT: KAPASITAS BANDWIDTH (ISP SLA)                  -->
                <!-- ═════════════════════════════════════════════════════════════ -->
                <div v-else-if="activeTab === 'bandwidth'" class="space-y-6">
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                            <div>
                                <h2 class="text-base font-black text-slate-900">
                                    Konfigurasi Kapasitas Bandwidth (SLA Contract)
                                </h2>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    Nilai kapasitas ini digunakan pada perhitungan SLA dashboard mingguan dan sheet export Excel. Ubah jika terdapat kenaikan kontrak ISP.
                                </p>
                            </div>
                        </div>

                        <div class="overflow-x-auto rounded-xl border border-slate-100">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="bg-[#003628] text-white">
                                        <th class="px-5 py-3 font-black uppercase">Branch / Site</th>
                                        <th class="px-4 py-3 font-black uppercase">FCT</th>
                                        <th class="px-4 py-3 font-black uppercase">ISP Provider</th>
                                        <th class="px-4 py-3 font-black uppercase text-center">Kapasitas SLA</th>
                                        <th class="px-4 py-3 font-black uppercase text-center">Target SLA %</th>
                                        <th class="px-4 py-3 font-black uppercase text-center">Status</th>
                                        <th class="px-4 py-3 font-black uppercase text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr
                                        v-for="contract in localContracts"
                                        :key="contract.id"
                                        class="hover:bg-slate-50/80 transition-colors"
                                    >
                                        <td class="px-5 py-3.5 font-bold text-slate-900">
                                            {{ contract.location }}
                                        </td>
                                        <td class="px-4 py-3.5 font-mono text-slate-600 font-bold">
                                            {{ contract.fct }}
                                        </td>
                                        <td class="px-4 py-3.5 font-black text-[#003628]">
                                            {{ contract.provider }}
                                        </td>
                                        <td class="px-4 py-3.5 text-center font-black text-emerald-700 text-sm tabular-nums">
                                            {{ Number(contract.bandwidth).toFixed(0) }} Mbps
                                        </td>
                                        <td class="px-4 py-3.5 text-center font-mono font-bold text-slate-700">
                                            {{ Number(contract.target_pct || 99.5).toFixed(1) }}%
                                        </td>
                                        <td class="px-4 py-3.5 text-center">
                                            <span class="rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 text-[10px] font-black text-emerald-700 uppercase">
                                                Active
                                            </span>
                                        </td>
                                        <td class="px-4 py-3.5 text-right">
                                            <button
                                                type="button"
                                                @click="openEditBandwidth(contract)"
                                                class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-1.5 text-xs font-black text-slate-700 shadow-sm transition hover:border-[#003628] hover:text-[#003628]"
                                            >
                                                <Pencil class="size-3.5" /> Edit Kapasitas
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="!localContracts.length">
                                        <td colspan="7" class="px-4 py-8 text-center text-slate-400 italic">
                                            Belum ada data kontrak SLA bandwidth.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ═════════════════════════════════════════════════════════════ -->
                <!-- TAB 3 CONTENT: FAILED DEVICES & CATATAN DOWNTIME              -->
                <!-- ═════════════════════════════════════════════════════════════ -->
                <div v-else-if="activeTab === 'failed'" class="space-y-6">
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                            <div>
                                <h2 class="text-base font-black text-slate-900">
                                    Perangkat Bermasalah &amp; Catatan Downtime
                                </h2>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    Atur perangkat yang sengaja dimatikan (Hide / Exclude agar tidak menurunkan uptime) serta berikan alasan &amp; rentang tanggal downtime agar kolom remark dan durasi tidak kosong.
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="relative">
                                    <Search class="absolute left-3 top-2.5 size-4 text-slate-400" />
                                    <input
                                        v-model="failedSearch"
                                        type="text"
                                        placeholder="Cari device / remark..."
                                        class="h-9 w-48 md:w-64 rounded-xl border border-slate-200 bg-slate-50 pl-9 pr-3 text-xs font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20"
                                    />
                                </div>
                                <button
                                    type="button"
                                    @click="openCreateDowntime"
                                    class="inline-flex items-center gap-1.5 rounded-xl bg-[#003628] px-4 py-2 text-xs font-black text-white uppercase shadow-md shadow-[#003628]/20 transition hover:bg-[#00251b]"
                                >
                                    <Plus class="size-4" /> Catat Downtime Baru
                                </button>
                            </div>
                        </div>

                        <div class="overflow-x-auto rounded-xl border border-slate-100">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="bg-[#991B1B] text-white">
                                        <th class="px-4 py-3 font-black uppercase">Branch</th>
                                        <th class="px-3 py-3 font-black uppercase">Kategori</th>
                                        <th class="px-4 py-3 font-black uppercase">Nama Perangkat</th>
                                        <th class="px-3 py-3 font-black uppercase">IP Address</th>
                                        <th class="px-3 py-3 font-black uppercase text-center">Visibilitas Laporan</th>
                                        <th class="px-4 py-3 font-black uppercase">Periode Downtime</th>
                                        <th class="px-4 py-3 font-black uppercase">Alasan / Remark</th>
                                        <th class="px-3 py-3 font-black uppercase text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-700">
                                    <tr
                                        v-for="item in visibleFailedDevices"
                                        :key="`${item.type}-${item.device_id}`"
                                        class="hover:bg-rose-50/20 transition-colors"
                                    >
                                        <td class="px-4 py-3 font-bold text-slate-900">
                                            {{ item.site || '-' }}
                                        </td>
                                        <td class="px-3 py-3">
                                            <span
                                                class="rounded border px-2 py-0.5 text-[9px] font-black uppercase"
                                                :class="
                                                    categories.find((c) => c.key === item.type)?.badge ||
                                                    'border-slate-200 text-slate-600'
                                                "
                                            >
                                                {{ item.category }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 font-bold text-slate-800">
                                            {{ item.device_name }}
                                        </td>
                                        <td class="px-3 py-3 font-mono text-slate-600">
                                            {{ item.ip_address || '-' }}
                                        </td>
                                        <td class="px-3 py-3 text-center">
                                            <button
                                                type="button"
                                                @click="toggleHideFailedDevice(item)"
                                                class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[9px] font-black uppercase transition-all"
                                                :class="
                                                    item.is_excluded
                                                        ? 'bg-amber-100 text-amber-800 hover:bg-amber-200'
                                                        : 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200'
                                                "
                                                :title="item.is_excluded ? 'Sengaja dimatikan (tidak dihitung di report)' : 'Tampil di laporan mingguan'"
                                            >
                                                <EyeOff v-if="item.is_excluded" class="size-3 text-amber-700" />
                                                <Eye v-else class="size-3 text-emerald-700" />
                                                <span>{{ item.is_excluded ? 'Sengaja Dimatikan (Hidden)' : 'Tampil di Report' }}</span>
                                            </button>
                                        </td>
                                        <td class="px-4 py-3 font-mono text-slate-600 text-[11px] whitespace-nowrap">
                                            <div v-if="item.started_at">
                                                <span>{{ item.started_at.replace('T', ' ') }}</span>
                                                <span v-if="item.resolved_at" class="text-slate-400"> s/d {{ item.resolved_at.replace('T', ' ') }}</span>
                                                <span v-else class="text-rose-600 font-bold"> (Masih Gangguan)</span>
                                            </div>
                                            <div v-else class="text-slate-400 italic">
                                                Belum ada jadwal
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-slate-700 italic max-w-xs truncate" :title="item.remark">
                                            {{ item.remark || '— Belum ada remark —' }}
                                        </td>
                                        <td class="px-3 py-3 text-right whitespace-nowrap">
                                            <button
                                                type="button"
                                                @click="openEditDowntime(item)"
                                                class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-black text-slate-700 shadow-sm transition hover:border-[#003628] hover:text-[#003628]"
                                            >
                                                <Pencil class="size-3" /> Beri Alasan
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="!visibleFailedDevices.length">
                                        <td colspan="8" class="px-4 py-8 text-center text-slate-400 italic">
                                            Tidak ada perangkat bermasalah atau sengaja dimatikan saat ini.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═════════════════════════════════════════════════════════════════ -->
        <!-- MODAL: KELOLA PERANGKAT UPTIME (BOGOR -> NETWORK, DLL)            -->
        <!-- ═════════════════════════════════════════════════════════════════ -->
        <Teleport to="body">
            <div
                v-if="showManageModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-xs p-4"
            >
                <div class="w-full max-w-2xl overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50 px-6 py-4">
                        <div>
                            <span class="rounded-full bg-[#003628]/10 px-2.5 py-0.5 text-[10px] font-black text-[#003628] uppercase">
                                {{ modalSite }} &bull; {{ modalCategoryLabel }}
                            </span>
                            <h3 class="mt-1 text-lg font-black text-slate-900">
                                Pilih Perangkat Uptime
                            </h3>
                            <p class="text-xs text-slate-500">
                                Centang perangkat yang ingin dimasukkan ke dalam perhitungan uptime &amp; laporan mingguan.
                            </p>
                        </div>
                        <button
                            type="button"
                            @click="showManageModal = false"
                            class="rounded-full p-2 text-slate-400 hover:bg-slate-200 hover:text-slate-700 transition"
                        >
                            <X class="size-5" />
                        </button>
                    </div>

                    <div class="p-6 space-y-4">
                        <div class="flex items-center justify-between gap-3">
                            <div class="relative flex-1">
                                <Search class="absolute left-3 top-2.5 size-4 text-slate-400" />
                                <input
                                    v-model="modalSearch"
                                    type="text"
                                    placeholder="Cari dalam daftar perangkat..."
                                    class="h-9 w-full rounded-xl border border-slate-200 bg-slate-50 pl-9 pr-3 text-xs font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20"
                                />
                            </div>
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    @click="selectAllModal"
                                    class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-50"
                                >
                                    Pilih Semua
                                </button>
                                <button
                                    type="button"
                                    @click="deselectAllModal"
                                    class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-50"
                                >
                                    Keluarkan Semua
                                </button>
                            </div>
                        </div>

                        <div class="max-h-[380px] overflow-y-auto rounded-xl border border-slate-200 divide-y divide-slate-100">
                            <label
                                v-for="dev in filteredModalDevices"
                                :key="dev.id"
                                class="flex items-center justify-between gap-3 px-4 py-2.5 hover:bg-slate-50/80 cursor-pointer transition-colors"
                            >
                                <div class="flex items-center gap-3">
                                    <input
                                        type="checkbox"
                                        :value="dev.id"
                                        v-model="modalSelectedIds"
                                        class="size-4 rounded text-[#003628] focus:ring-[#003628]"
                                    />
                                    <div>
                                        <p class="text-xs font-bold text-slate-800">
                                            {{ dev.device_name }}
                                        </p>
                                        <p class="font-mono text-[10px] text-slate-400">
                                            {{ dev.ip_address || '-' }} &bull; {{ dev.host_group || dev.location || 'No Group' }}
                                        </p>
                                    </div>
                                </div>
                                <span
                                    class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase"
                                    :class="
                                        modalSelectedIds.includes(dev.id)
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : 'bg-slate-100 text-slate-400'
                                    "
                                >
                                    {{ modalSelectedIds.includes(dev.id) ? 'Included' : 'Excluded' }}
                                </span>
                            </label>
                            <div v-if="!filteredModalDevices.length" class="p-8 text-center text-slate-400 text-xs italic">
                                Tidak ada perangkat yang sesuai filter.
                            </div>
                        </div>

                        <div class="flex items-center justify-between text-xs text-slate-500 font-medium">
                            <span>
                                Terpilih: <strong class="text-slate-800">{{ modalSelectedIds.length }}</strong> dari {{ modalDevices.length }} perangkat
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-slate-100 bg-slate-50 px-6 py-4">
                        <button
                            type="button"
                            @click="showManageModal = false"
                            class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-black text-slate-600 uppercase transition hover:bg-slate-50"
                        >
                            Batal
                        </button>
                        <button
                            type="button"
                            :disabled="modalSaving"
                            @click="saveModalDevices"
                            class="flex items-center gap-2 rounded-xl bg-[#003628] px-5 py-2 text-xs font-black text-white uppercase shadow-md shadow-[#003628]/20 transition hover:bg-[#00251b] disabled:opacity-50"
                        >
                            <Loader2 v-if="modalSaving" class="size-3.5 animate-spin" />
                            <Save v-else class="size-3.5" />
                            Simpan Perubahan
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ═════════════════════════════════════════════════════════════════ -->
        <!-- MODAL: EDIT KAPASITAS BANDWIDTH (SLA)                             -->
        <!-- ═════════════════════════════════════════════════════════════════ -->
        <Teleport to="body">
            <div
                v-if="showBandwidthModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-xs p-4"
            >
                <div class="w-full max-w-md overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl">
                    <div class="border-b border-slate-100 bg-slate-50 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-base font-black text-slate-900">
                                Edit Kapasitas Bandwidth
                            </h3>
                            <button
                                type="button"
                                @click="showBandwidthModal = false"
                                class="rounded-full p-1.5 text-slate-400 hover:bg-slate-200 hover:text-slate-700 transition"
                            >
                                <X class="size-4" />
                            </button>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ editingContract?.location }} &bull; {{ editingContract?.fct }} &bull; {{ editingContract?.provider }}
                        </p>
                    </div>

                    <form @submit.prevent="saveBandwidth" class="p-6 space-y-4">
                        <div>
                            <label class="block text-[10px] font-black tracking-widest text-slate-400 uppercase mb-1">
                                Kapasitas Bandwidth (Mbps)
                            </label>
                            <div class="relative">
                                <input
                                    v-model.number="bandwidthForm.bandwidth"
                                    type="number"
                                    step="1"
                                    min="1"
                                    required
                                    class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 pr-16 text-sm font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#003628]/20"
                                />
                                <span class="absolute right-3 top-2.5 text-xs font-black text-slate-400">
                                    Mbps
                                </span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black tracking-widest text-slate-400 uppercase mb-1">
                                Target SLA (%)
                            </label>
                            <div class="relative">
                                <input
                                    v-model.number="bandwidthForm.target_pct"
                                    type="number"
                                    step="0.1"
                                    min="0"
                                    max="100"
                                    required
                                    class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 pr-10 text-sm font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#003628]/20"
                                />
                                <span class="absolute right-3 top-2.5 text-xs font-black text-slate-400">
                                    %
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-2">
                            <button
                                type="button"
                                @click="showBandwidthModal = false"
                                class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-black text-slate-600 uppercase transition hover:bg-slate-50"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="bandwidthSaving"
                                class="flex items-center gap-2 rounded-xl bg-[#003628] px-5 py-2 text-xs font-black text-white uppercase shadow-md shadow-[#003628]/20 transition hover:bg-[#00251b] disabled:opacity-50"
                            >
                                <Loader2 v-if="bandwidthSaving" class="size-3.5 animate-spin" />
                                <Save v-else class="size-3.5" />
                                Simpan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- ═════════════════════════════════════════════════════════════════ -->
        <!-- MODAL: BERI ALASAN DOWNTIME / CATAT MAINTENANCE                  -->
        <!-- ═════════════════════════════════════════════════════════════════ -->
        <Teleport to="body">
            <div
                v-if="showDowntimeModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-xs p-4"
            >
                <div class="w-full max-w-lg overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50 px-6 py-4">
                        <div>
                            <span class="rounded-full bg-rose-100 px-2.5 py-0.5 text-[10px] font-black text-rose-800 uppercase">
                                {{ isNewDowntimeEntry ? 'Tambah Catatan Baru' : 'Edit Alasan Downtime' }}
                            </span>
                            <h3 class="mt-1 text-base font-black text-slate-900">
                                {{ downtimeForm.device_name || 'Pilih Perangkat' }}
                            </h3>
                            <p class="text-xs text-slate-500 font-mono">
                                {{ downtimeForm.ip_address }} &bull; {{ downtimeForm.site }}
                            </p>
                        </div>
                        <button
                            type="button"
                            @click="showDowntimeModal = false"
                            class="rounded-full p-2 text-slate-400 hover:bg-slate-200 hover:text-slate-700 transition"
                        >
                            <X class="size-4" />
                        </button>
                    </div>

                    <form @submit.prevent="saveDowntimeLog" class="p-6 space-y-4">
                        <!-- IF NEW ENTRY: PICKER -->
                        <div v-if="isNewDowntimeEntry" class="space-y-1.5">
                            <label class="block text-[10px] font-black tracking-widest text-slate-400 uppercase">
                                Pilih Perangkat
                            </label>
                            <input
                                v-model="devicePickerSearch"
                                type="text"
                                placeholder="Cari nama atau IP perangkat..."
                                class="h-9 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20"
                            />
                            <div class="max-h-28 overflow-y-auto rounded-xl border border-slate-100 divide-y divide-slate-100">
                                <div
                                    v-for="pDev in filteredPickerDevices"
                                    :key="`${pDev.type}-${pDev.id}`"
                                    @click="onSelectPickerDevice(pDev)"
                                    class="px-3 py-1.5 hover:bg-slate-50 cursor-pointer flex items-center justify-between text-xs"
                                    :class="downtimeForm.device_id === pDev.id && downtimeForm.device_type === pDev.type ? 'bg-emerald-50/80 font-bold text-[#003628]' : 'text-slate-700'"
                                >
                                    <span>{{ pDev.device_name }} ({{ pDev.ip_address || '-' }})</span>
                                    <span class="text-[9px] uppercase text-slate-400">{{ pDev.site }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- DATE RANGE: FROM - TO -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-black tracking-widest text-slate-400 uppercase mb-1">
                                    Mulai Downtime
                                </label>
                                <input
                                    v-model="downtimeForm.started_at"
                                    type="datetime-local"
                                    required
                                    class="h-9 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20"
                                />
                            </div>
                            <div>
                                <label class="block text-[10px] font-black tracking-widest text-slate-400 uppercase mb-1">
                                    Selesai Downtime
                                </label>
                                <input
                                    v-model="downtimeForm.resolved_at"
                                    type="datetime-local"
                                    class="h-9 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20"
                                />
                            </div>
                        </div>

                        <!-- EVENT TYPE & STATUS -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-black tracking-widest text-slate-400 uppercase mb-1">
                                    Tipe Kejadian
                                </label>
                                <select
                                    v-model="downtimeForm.event_type"
                                    class="h-9 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20"
                                >
                                    <option value="maintenance">Maintenance Terjadwal</option>
                                    <option value="restart">Restart / Pemadaman</option>
                                    <option value="down">Gangguan Fisik / Hardware</option>
                                    <option value="intentional">Sengaja Dimatikan</option>
                                    <option value="auto_detected">Auto Detected PRTG</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black tracking-widest text-slate-400 uppercase mb-1">
                                    Status
                                </label>
                                <select
                                    v-model="downtimeForm.status"
                                    class="h-9 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20"
                                >
                                    <option value="closed">Selesai (Closed)</option>
                                    <option value="open">Masih Gangguan (Open)</option>
                                </select>
                            </div>
                        </div>

                        <!-- NOTES / REMARK -->
                        <div>
                            <label class="block text-[10px] font-black tracking-widest text-slate-400 uppercase mb-1">
                                Catatan / Alasan Downtime (Remark)
                            </label>
                            <textarea
                                v-model="downtimeForm.notes"
                                rows="3"
                                required
                                placeholder="Contoh: Perangkat sengaja dimatikan karena pemeliharaan panel listrik gedung A..."
                                class="w-full resize-none rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003628]/20"
                            ></textarea>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                            <button
                                type="button"
                                @click="showDowntimeModal = false"
                                class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-black text-slate-600 uppercase transition hover:bg-slate-50"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="downtimeSaving"
                                class="flex items-center gap-2 rounded-xl bg-[#003628] px-5 py-2 text-xs font-black text-white uppercase shadow-md shadow-[#003628]/20 transition hover:bg-[#00251b] disabled:opacity-50"
                            >
                                <Loader2 v-if="downtimeSaving" class="size-3.5 animate-spin" />
                                <Save v-else class="size-3.5" />
                                Simpan Alasan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
