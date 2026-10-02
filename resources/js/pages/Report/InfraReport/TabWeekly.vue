<script setup lang="ts">
import { ref, onMounted, watch, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    Loader2,
    AlertCircle,
    CheckCircle2,
    Settings,
    Pencil,
} from 'lucide-vue-next';

const props = defineProps<{
    from: string | null;
    to: string | null;
    applyTrigger: number;
}>();

const loading = ref(false);
const reportData = ref<any>(null);
const errorMsg = ref<string | null>(null);

// Placeholder function for edit remark (to be implemented)
function openEditLog(row: any) {
    console.log('Edit log for:', row);
    // TODO: Implement edit remark modal
}

async function loadData() {
    if (!props.from || !props.to) return;
    loading.value = true;
    errorMsg.value = null;
    try {
        const params = new URLSearchParams({
            from: props.from,
            to: props.to,
        });
        const res = await fetch(`/infra-report/data?${params}`, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
            },
        });
        if (!res.ok) throw new Error('API Error: ' + res.status);
        const json = await res.json();

        if (json.error) {
            errorMsg.value =
                json.error +
                (json.file ? ' (' + json.file + ':' + json.line + ')' : '');
            reportData.value = json;
        } else {
            errorMsg.value = null;
            reportData.value = json;
        }
    } catch (e) {
        console.error('InfraReport Load Error:', e);
        errorMsg.value = (e as Error).message;
    } finally {
        loading.value = false;
    }
}

onMounted(() => loadData());
watch(
    () => props.applyTrigger,
    () => loadData(),
);

// Format helpers
function formatDate(dtStr: string | null) {
    if (!dtStr) return '—';
    try {
        const d = new Date(dtStr);
        if (isNaN(d.getTime())) return dtStr;
        const day = String(d.getDate()).padStart(2, '0');
        const months = [
            'Jan',
            'Feb',
            'Mar',
            'Apr',
            'May',
            'Jun',
            'Jul',
            'Aug',
            'Sep',
            'Oct',
            'Nov',
            'Dec',
        ];
        const month = months[d.getMonth()];
        const year = String(d.getFullYear()).slice(-2);
        return `${day}-${month}-${year}`;
    } catch {
        return dtStr;
    }
}

function cleanLocation(str: string | null) {
    if (!str) return '—';
    return str.replace(/^F\d+\s+/i, '');
}

const formattedDateRange = computed(() => {
    return `${formatDate(props.from)} – ${formatDate(props.to)}`;
});

// Branch Health
const branchHealthRows = computed(() => {
    if (!reportData.value) return [];
    const d = reportData.value;
    const sites = ['F1 Bogor', 'F2 Karawang', 'F3 Tangerang'];

    return sites.map((site) => {
        const findUptime = (arr: any[]) => {
            const match = arr?.find((item) => item.location === site);
            return match ? Number(match.uptime) : 100.0;
        };

        const net = findUptime(d.network);
        const nvr = findUptime(d.nvr);
        const cctv = findUptime(d.cctv);
        const srv = findUptime(d.server);
        const avg = Math.round(((net + nvr + cctv + srv) / 4) * 10) / 10;

        return {
            location: site,
            network: net,
            nvr: nvr,
            cctv: cctv,
            server: srv,
            average: avg,
        };
    });
});

const branchHealthAvg = computed(() => {
    const rows = branchHealthRows.value;
    if (!rows.length)
        return { network: 100, nvr: 100, cctv: 100, server: 100 };
    const avgFor = (key: string) => {
        const sum = rows.reduce((acc, r) => acc + (Number(r[key]) || 0), 0);
        return Math.round((sum / rows.length) * 10) / 10;
    };
    return {
        network: avgFor('network'),
        nvr: avgFor('nvr'),
        cctv: avgFor('cctv'),
        server: avgFor('server'),
    };
});

// Bandwidth Snapshot
const bandwidthSnapshotRows = computed(() => {
    if (!reportData.value?.bandwidth) return [];
    return reportData.value.bandwidth.flatMap((site: any) =>
        (site.providers ?? []).map((p: any) => ({
            ...p,
            category: 'Bandwidth',
            location: site.location,
            provider: p.provider,
            limit:
                p.bandwidth_limit != null && p.bandwidth_limit > 0
                    ? Number(p.bandwidth_limit)
                    : null,
            download:
                p.avg_download != null ? Number(p.avg_download) : null,
            upload: p.avg_upload != null ? Number(p.avg_upload) : null,
        })),
    );
});

// Failed Devices
const failedDevices = computed(() => {
    if (!reportData.value) return [];
    const d = reportData.value;
    const out: any[] = [];
    const seen = new Set<string>();
    const push = (cat: string, rows: any[]) => {
        for (const row of rows ?? []) {
            for (const f of row.failed_list ?? []) {
                const key = `${cat}_${f.ip_address ?? ''}_${f.device_name ?? ''}`;
                if (!seen.has(key)) {
                    seen.add(key);
                    out.push({ category: cat, site: row.location, ...f });
                }
            }
        }
    };
    push('Network', d.network);
    push('NVR', d.nvr);
    push('CCTV', d.cctv);
    push('Server', d.server);
    return out;
});

// Top KPI Metrics
const systemUptimeAvg = computed(() => {
    const avgObj = branchHealthAvg.value;
    const val =
        (avgObj.network + avgObj.nvr + avgObj.cctv + avgObj.server) / 4;
    return Math.round(val * 10) / 10;
});

const avgBandwidth = computed(() => {
    const rows = bandwidthSnapshotRows.value;
    if (!rows.length) return { dl: 0, ul: 0 };
    const dlRows = rows.filter(
        (r: any) => r.download != null && r.download > 0,
    );
    const ulRows = rows.filter(
        (r: any) => r.upload != null && r.upload > 0,
    );
    const dl = dlRows.length
        ? Math.round(
              (dlRows.reduce(
                  (a: number, b: any) => a + b.download,
                  0,
              ) /
                  dlRows.length) *
                  10,
          ) / 10
        : 0;
    const ul = ulRows.length
        ? Math.round(
              (ulRows.reduce(
                  (a: number, b: any) => a + b.upload,
                  0,
              ) /
                  ulRows.length) *
                  10,
          ) / 10
        : 0;
    return { dl, ul };
});

const pcIssues = computed(() => {
    if (!reportData.value?.helpdesk) return { closed: 0, total: 0 };
    let total = 0;
    let closed = 0;
    for (const h of reportData.value.helpdesk) {
        total += Number(h.case ?? 0);
        closed += Number(h.closed ?? 0);
    }
    return { closed, total };
});

const failedDevicesCount = computed(() => failedDevices.value.length);

const catBadge: Record<string, string> = {
    Network: 'bg-blue-50 text-blue-700 border-blue-200',
    NVR: 'bg-violet-50 text-violet-700 border-violet-200',
    CCTV: 'bg-sky-50 text-sky-700 border-sky-200',
    Server: 'bg-orange-50 text-orange-700 border-orange-200',
    Bandwidth: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    Helpdesk: 'bg-rose-50 text-rose-700 border-rose-200',
};
</script>

<template>
    <div class="space-y-6">
        <!-- LOADING STATE -->
        <div
            v-if="loading"
            class="flex flex-col items-center justify-center gap-4 py-32"
        >
            <Loader2 class="size-8 animate-spin text-[#003628] opacity-20" />
            <p
                class="text-[10px] font-black tracking-[0.2em] text-slate-400 uppercase"
            >
                Menghitung data infrastruktur...
            </p>
        </div>

        <!-- MAIN DATA TEMPLATE -->
        <template v-else-if="reportData">
            <!-- ERROR MESSAGE (TOP) -->
            <div
                v-if="errorMsg"
                class="flex items-start gap-3 rounded-2xl border border-rose-100 bg-rose-50 px-5 py-4 text-rose-800 shadow-sm"
            >
                <AlertCircle class="mt-0.5 size-4 shrink-0 text-rose-500" />
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-black tracking-wider uppercase">
                        Terjadi Masalah Data
                    </p>
                    <p
                        class="mt-1 font-mono text-[10px] leading-relaxed break-all opacity-80"
                    >
                        {{ errorMsg }}
                    </p>
                </div>
                <button
                    @click="loadData"
                    class="h-7 rounded-lg bg-rose-100 px-3 text-[9px] font-black text-rose-700 uppercase transition-all hover:bg-rose-200"
                >
                    Coba Lagi
                </button>
            </div>

            <!-- DASHBOARD CONTAINER -->
            <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm space-y-6">
                <!-- 1. TITLE HEADER -->
                <div class="border-b border-slate-100 pb-4">
                    <h1 class="text-xl md:text-2xl font-black tracking-tight text-slate-900 uppercase">
                        ZINUS IDN | WEEKLY INFRA REPORT
                    </h1>
                    <p class="text-xs text-slate-500 italic mt-0.5">
                        Infrastructure health &amp; incident overview &bull; {{ formattedDateRange }}
                    </p>
                </div>

                <!-- 2. TOP KPI SUMMARY -->
                <div class="rounded-xl border border-slate-200/70 bg-slate-50/50 p-4">
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 divide-y sm:divide-y-0 sm:divide-x divide-slate-200/60 text-center">
                        <!-- Metric 1: System Uptime -->
                        <div class="px-2 py-1 flex flex-col items-center justify-center">
                            <span class="text-[11px] font-extrabold tracking-wider text-slate-500 uppercase">
                                SYSTEM UPTIME
                            </span>
                            <span class="mt-1 text-2xl md:text-3xl font-black text-emerald-600 tabular-nums">
                                {{ systemUptimeAvg.toFixed(1) }}%
                            </span>
                        </div>

                        <!-- Metric 2: Avg Bandwidth -->
                        <div class="px-2 py-1 flex flex-col items-center justify-center">
                            <span class="text-[11px] font-extrabold tracking-wider text-slate-500 uppercase">
                                AVG BANDWIDTH (Mbps)
                            </span>
                            <span class="mt-1 text-2xl md:text-3xl font-black text-teal-600 tabular-nums">
                                {{ avgBandwidth.dl }} &darr; / {{ avgBandwidth.ul }} &uarr;
                            </span>
                        </div>

                        <!-- Metric 3: PC Issues Resolved -->
                        <div class="px-2 py-1 flex flex-col items-center justify-center">
                            <span class="text-[11px] font-extrabold tracking-wider text-slate-500 uppercase">
                                PC ISSUES RESOLVED
                            </span>
                            <span class="mt-1 text-2xl md:text-3xl font-black text-emerald-600 tabular-nums">
                                {{ pcIssues.closed }} / {{ pcIssues.total }}
                            </span>
                        </div>

                        <!-- Metric 4: Failed Devices -->
                        <div class="px-2 py-1 flex flex-col items-center justify-center">
                            <span class="text-[11px] font-extrabold tracking-wider text-slate-500 uppercase">
                                FAILED DEVICES
                            </span>
                            <span class="mt-1 text-2xl md:text-3xl font-black text-rose-600 tabular-nums">
                                {{ failedDevicesCount }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- 3. MIDDLE SECTION: 2 COLUMNS (BRANCH HEALTH & BANDWIDTH SNAPSHOT) -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
                    <!-- Left: Branch Health -->
                    <div class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm">
                        <div class="bg-[#003628] px-4 py-2.5 text-white">
                            <h2 class="text-xs font-black tracking-widest uppercase">
                                BRANCH HEALTH
                            </h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="bg-[#C88528] text-white">
                                        <th class="px-4 py-2 font-black">Branch</th>
                                        <th class="px-3 py-2 text-center font-black">Network</th>
                                        <th class="px-3 py-2 text-center font-black">NVR</th>
                                        <th class="px-3 py-2 text-center font-black">CCTV</th>
                                        <th class="px-3 py-2 text-center font-black">Server</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-700">
                                    <tr
                                        v-for="row in branchHealthRows"
                                        :key="row.location"
                                        class="hover:bg-slate-50/70 transition-colors"
                                    >
                                        <td class="px-4 py-2.5 font-bold text-slate-900">
                                            {{ cleanLocation(row.location) }}
                                        </td>
                                        <td class="px-3 py-2.5 text-center font-bold tabular-nums">
                                            {{ Number(row.network).toFixed(1) }}%
                                        </td>
                                        <td class="px-3 py-2.5 text-center font-bold tabular-nums">
                                            {{ Number(row.nvr).toFixed(1) }}%
                                        </td>
                                        <td class="px-3 py-2.5 text-center font-bold tabular-nums">
                                            {{ Number(row.cctv).toFixed(1) }}%
                                        </td>
                                        <td class="px-3 py-2.5 text-center font-bold tabular-nums">
                                            {{ Number(row.server).toFixed(1) }}%
                                        </td>
                                    </tr>
                                    <!-- Average Row -->
                                    <tr class="border-t-2 border-slate-200 bg-slate-50/50 font-black text-slate-900">
                                        <td class="px-4 py-2.5">Average</td>
                                        <td class="px-3 py-2.5 text-center tabular-nums">
                                            {{ branchHealthAvg.network.toFixed(1) }}%
                                        </td>
                                        <td class="px-3 py-2.5 text-center tabular-nums">
                                            {{ branchHealthAvg.nvr.toFixed(1) }}%
                                        </td>
                                        <td class="px-3 py-2.5 text-center tabular-nums">
                                            {{ branchHealthAvg.cctv.toFixed(1) }}%
                                        </td>
                                        <td class="px-3 py-2.5 text-center tabular-nums">
                                            {{ branchHealthAvg.server.toFixed(1) }}%
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Right: Bandwidth Snapshot -->
                    <div class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm">
                        <div class="bg-[#003628] px-4 py-2.5 text-white">
                            <h2 class="text-xs font-black tracking-widest uppercase">
                                BANDWIDTH SNAPSHOT
                            </h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="bg-[#C88528] text-white">
                                        <th class="px-4 py-2 font-black">Branch</th>
                                        <th class="px-3 py-2 font-black">ISP</th>
                                        <th class="px-3 py-2 text-center font-black">Capacity</th>
                                        <th class="px-3 py-2 text-right font-black">Down</th>
                                        <th class="px-3 py-2 text-right font-black">Up</th>
                                        <th class="px-2 py-2 text-center font-black w-8"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-700">
                                    <tr
                                        v-for="(row, idx) in bandwidthSnapshotRows"
                                        :key="idx"
                                        class="hover:bg-slate-50/70 transition-colors"
                                    >
                                        <td class="px-4 py-2.5 font-bold text-slate-900">
                                            {{ cleanLocation(row.location) }}
                                        </td>
                                        <td class="px-3 py-2.5 font-medium text-slate-700">
                                            {{ row.provider }}
                                        </td>
                                        <td class="px-3 py-2.5 text-center font-medium tabular-nums">
                                            {{ row.limit != null && row.limit > 0 ? row.limit.toFixed(1) : '-' }}
                                        </td>
                                        <td class="px-3 py-2.5 text-right font-bold tabular-nums text-emerald-700">
                                            {{ row.download != null ? row.download.toFixed(1) + ' Mbps' : '-' }}
                                        </td>
                                        <td class="px-3 py-2.5 text-right font-bold tabular-nums text-sky-700">
                                            {{ row.upload != null ? row.upload.toFixed(1) + ' Mbps' : '-' }}
                                        </td>
                                        <td class="px-2 py-2.5 text-center">
                                            <button
                                                type="button"
                                                title="Edit Remark"
                                                class="text-slate-400 hover:text-[#003628] transition-colors p-1"
                                                @click="openEditLog(row)"
                                            >
                                                <Pencil class="size-3" />
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="!bandwidthSnapshotRows.length">
                                        <td colspan="6" class="px-4 py-6 text-center text-slate-400 italic">
                                            Belum ada data bandwidth
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 4. BOTTOM SECTION: FAILED DEVICES -->
                <div class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm">
                    <div class="bg-[#003628] px-4 py-2.5 text-white flex items-center justify-between">
                        <h2 class="text-xs font-black tracking-widest uppercase">
                            ACTION REQUIRED &bull; FAILED DEVICES
                        </h2>
                        <Link
                            href="/infra-report/device-settings?tab=failed"
                            class="inline-flex items-center gap-1.5 text-[10px] font-black text-emerald-300 hover:text-white uppercase transition-colors"
                        >
                            <Settings class="size-3" /> Kelola di Settings &rarr;
                        </Link>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-[#991B1B] text-white">
                                    <th class="px-4 py-2 font-black">Location</th>
                                    <th class="px-3 py-2 font-black">Date</th>
                                    <th class="px-3 py-2 font-black">Category</th>
                                    <th class="px-3 py-2 font-black">Device Name</th>
                                    <th class="px-3 py-2 font-black">IP Address</th>
                                    <th class="px-3 py-2 text-center font-black">Downtime</th>
                                    <th class="px-4 py-2 font-black">Remark</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                <tr
                                    v-for="(dev, idx) in failedDevices"
                                    :key="idx"
                                    class="hover:bg-rose-50/30 transition-colors"
                                >
                                    <td class="px-4 py-2.5 font-bold text-slate-900">
                                        {{ cleanLocation(dev.site) }}
                                    </td>
                                    <td class="px-3 py-2.5 font-mono text-slate-600 whitespace-nowrap">
                                        {{ formatDate(dev.report_date) }}
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <span
                                            class="rounded border px-2 py-0.5 text-[10px] font-extrabold uppercase"
                                            :class="catBadge[dev.category] ?? 'bg-slate-50 text-slate-600 border-slate-200'"
                                        >
                                            {{ dev.category }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2.5 font-bold text-slate-800">
                                        {{ dev.device_name }}
                                    </td>
                                    <td class="px-3 py-2.5 font-mono text-slate-600">
                                        {{ dev.ip_address || '-' }}
                                    </td>
                                    <td class="px-3 py-2.5 text-center font-mono text-slate-600 tabular-nums">
                                        {{ dev.duration || '-' }}
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-600 italic max-w-xs truncate" :title="dev.remark">
                                        {{ dev.remark || '-' }}
                                    </td>
                                </tr>
                                <tr v-if="!failedDevices.length">
                                    <td colspan="7" class="px-4 py-8 text-center text-slate-400">
                                        <div class="flex flex-col items-center justify-center gap-1.5">
                                            <CheckCircle2 class="size-6 text-emerald-500" />
                                            <p class="font-bold text-slate-600">Semua Perangkat Normal</p>
                                            <p class="text-[11px] text-slate-400">
                                                Tidak ada perangkat yang mengalami downtime pada periode ini
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 5. FOOTER CAPTION -->
                <p class="text-[11px] text-slate-400 italic">
                    Source: Weekly Infra Report data &bull; Dashboard is linked to the Data sheet.
                </p>
            </div>
        </template>

        <!-- EMPTY STATE -->
        <div
            v-else
            class="flex flex-col items-center justify-center gap-3 py-32"
        >
            <AlertCircle class="size-8 text-slate-200" />
            <p
                class="text-[10px] font-black tracking-widest text-slate-300 uppercase"
            >
                Belum ada data untuk periode ini
            </p>
            <button
                type="button"
                @click="loadData"
                class="mt-2 h-8 rounded-xl border border-slate-200 bg-white px-4 text-[9px] font-black tracking-widest text-slate-500 uppercase transition-all hover:bg-slate-50"
            >
                Refresh
            </button>
        </div>
    </div>
</template>
