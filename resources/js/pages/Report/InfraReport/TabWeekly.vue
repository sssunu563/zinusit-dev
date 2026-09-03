<script setup lang="ts">
import { ref, onMounted, watch, computed } from 'vue';
import {
    Activity,
    Shield,
    Camera,
    Wifi,
    Server,
    LifeBuoy,
    CheckCircle2,
    AlertTriangle,
    Loader2,
    MapPin,
    ArrowDown,
    ArrowUp,
    AlertCircle,
    Zap,
    Pencil,
    X,
    Save,
    Plus,
} from 'lucide-vue-next';

const props = defineProps<{
    from: string | null;
    to: string | null;
    applyTrigger: number;
}>();

const loading = ref(false);
const reportData = ref<any>(null);
const errorMsg = ref<string | null>(null);
const activeLogTab = ref<'device' | 'helpdesk' | 'bandwidth'>('device');

async function loadData() {
    if (!props.from || !props.to) return;
    loading.value = true;
    errorMsg.value = null;
    try {
        const csrf =
            (
                document.querySelector(
                    'meta[name="csrf-token"]',
                ) as HTMLMetaElement
            )?.content ?? '';
        const res = await fetch('/infra-report/data', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({ from: props.from, to: props.to }),
        });
        if (!res.ok) throw new Error('API Error: ' + res.status);
        const json = await res.json();

        if (json.error) {
            errorMsg.value =
                json.error +
                (json.file ? ' (' + json.file + ':' + json.line + ')' : '');
            // We still set reportData to show the rest of the UI if some data arrived
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

// -- Edit Maintenance Log Modal ----------------------------------------
const showEditModal = ref(false);
const editingLog = ref<any>(null);
const editSaving = ref(false);
const editForm = ref({
    status: 'open',
    event_type: 'maintenance',
    started_at: '',
    resolved_at: '',
    notes: '',
    case: '', // For Helpdesk
    remark: '', // For Bandwidth/Helpdesk
    device_id: '' as string | number, // For new logs
});
const EVENT_LABELS: Record<string, string> = {
    maintenance: 'Maintenance',
    restart: 'Restart',
    down: 'Down',
    auto_detected: 'Auto Detected',
};

// Auto-update status saat resolved_at diisi/dihapus
watch(
    () => editForm.value.resolved_at,
    (newVal) => {
        if (newVal && newVal.trim() !== '') {
            editForm.value.status = 'closed';
        } else {
            editForm.value.status = 'open';
        }
    },
);
function categoryToApi(category: string): string {
    if (category === 'Network') return '/uptime/maintenance-logs';
    if (category === 'Server') return '/server-operation/maintenance-logs';
    if (category === 'CCTV' || category === 'NVR')
        return '/cctv-operation/maintenance-logs';
    if (category === 'Bandwidth') return '/infra-report/bandwidth/remark';
    if (category === 'Helpdesk') return '/infra-report/helpdesk/remark';
    return '';
}
function openEditLog(log: any) {
    editingLog.value = log;

    const formatDT = (dt: string | null) => {
        if (!dt) return '';
        const d = new Date(dt);
        if (isNaN(d.getTime())) return dt;
        return d.toISOString().slice(0, 16);
    };

    editForm.value = {
        status: log.status ?? 'open',
        event_type: log.event_type ?? 'maintenance',
        started_at: formatDT(log.started_at ?? log.report_date),
        resolved_at: formatDT(log.resolved_at),
        notes: log.notes ?? log.remark ?? '',
        case: log.case ?? log.title ?? '',
        remark: log.remark ?? log.notes ?? '',
        device_id: log.device_id || '',
    };
    showEditModal.value = true;
}

function openAddLog(category: string) {
    const log = {
        category,
        id: null,
        device_name: 'New ' + category + ' Entry',
        site: 'F1 Bogor', // Default
    };
    openEditLog(log);
}
async function saveEditLog() {
    if (!editingLog.value) return;
    editSaving.value = true;
    const csrf =
        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)
            ?.content ?? '';
    const category = editingLog.value.category;

    try {
        const url = categoryToApi(category);
        let method = 'POST';
        let body: any = {};

        if (category === 'Bandwidth') {
            body = { id: editingLog.value.id, remark: editForm.value.remark };
        } else if (category === 'Helpdesk') {
            body = {
                id: editingLog.value.id,
                case: editForm.value.case,
                remark: editForm.value.remark,
            };
        } else {
            // Maintenance logs
            const isNew = !editingLog.value.id;
            const fullUrl = url + (isNew ? '' : '/' + editingLog.value.id);
            const fullMethod = isNew ? 'POST' : 'PUT';

            body = {
                event_type: editForm.value.event_type,
                notes: editForm.value.notes || null,
                status: editForm.value.status,
                device_id: editForm.value.device_id, // Use from form
                started_at: editForm.value.started_at,
            };
            if (editForm.value.resolved_at)
                body.resolved_at = editForm.value.resolved_at;

            const res = await fetch(fullUrl, {
                method: fullMethod,
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify(body),
            });
            if (res.ok) {
                showEditModal.value = false;
                await loadData();
            } else {
                const err = await res.json();
                alert('Gagal menyimpan: ' + (err.message || 'Error unknown'));
            }
            return;
        }

        // Bandwidth / Helpdesk specialized endpoints
        const res = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify(body),
        });
        if (res.ok) {
            showEditModal.value = false;
            await loadData();
        }
    } catch (e) {
        console.error('Save error:', e);
    } finally {
        editSaving.value = false;
    }
}

// ── Colour helpers ────────────────────────────────────────────────────────────
const uc = (v: number) =>
    v >= 90 ? 'text-emerald-600' : v >= 80 ? 'text-amber-500' : 'text-rose-500';
const ub = (v: number) =>
    v >= 90 ? 'bg-emerald-400' : v >= 80 ? 'bg-amber-400' : 'bg-rose-400';
const ubadge = (v: number) =>
    v >= 90
        ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
        : v >= 80
          ? 'bg-amber-50 text-amber-700 border-amber-200'
          : 'bg-rose-50 text-rose-700 border-rose-200';
const ulabel = (v: number) =>
    v >= 90 ? 'Normal' : v >= 80 ? 'Warning' : 'Critical';
const pc = (v: number) =>
    v >= 90 ? 'text-emerald-600' : v >= 70 ? 'text-amber-500' : 'text-rose-500';
const pb = (v: number) =>
    v >= 90 ? 'bg-emerald-400' : v >= 70 ? 'bg-amber-400' : 'bg-rose-400';

const bwColor = (usage: number, limit: number) => {
    if (!limit) return 'bg-emerald-400';
    const pct = (usage / limit) * 100;
    if (pct >= 90) return 'bg-rose-500';
    if (pct >= 75) return 'bg-amber-400';
    return 'bg-emerald-400';
};
const bwText = (usage: number, limit: number) => {
    if (!limit) return 'text-slate-700';
    const pct = (usage / limit) * 100;
    if (pct >= 90) return 'text-rose-600';
    if (pct >= 75) return 'text-amber-600';
    return 'text-emerald-600';
};

function avg(data: any[], key = 'uptime'): number {
    if (!Array.isArray(data) || !data.length) return 0;
    const sum = data.reduce((s, d) => {
        const val = parseFloat(d[key]);
        return s + (isNaN(val) ? 0 : val);
    }, 0);
    return Math.round((sum / data.length) * 100) / 100;
}

// ── KPI cards ─────────────────────────────────────────────────────────────────
const kpiCards = computed(() => {
    if (!reportData.value) return [];
    const d = reportData.value;

    const bwRows = (d.bandwidth ?? []).flatMap((b: any) => b.providers ?? []);
    const bwDl = bwRows.length
        ? Math.round(
              (bwRows.reduce(
                  (s: number, p: any) => s + (parseFloat(p.avg_download) || 0),
                  0,
              ) /
                  bwRows.length) *
                  10,
          ) / 10
        : 0;

    const hdAvg = (d.helpdesk ?? []).length
        ? Math.round(
              ((d.helpdesk ?? []).reduce(
                  (s: number, h: any) => s + (parseFloat(h.performance) || 0),
                  0,
              ) /
                  d.helpdesk.length) *
                  100,
          ) / 100
        : 0;

    return [
        {
            label: 'Network H/W',
            icon: Shield,
            value: avg(d.network),
            unit: '%',
            type: 'uptime',
        },
        {
            label: 'NVR Status',
            icon: Activity,
            value: avg(d.nvr),
            unit: '%',
            type: 'uptime',
        },
        {
            label: 'CCTV Status',
            icon: Camera,
            value: avg(d.cctv),
            unit: '%',
            type: 'uptime',
        },
        {
            label: 'Server Check',
            icon: Server,
            value: avg(d.server),
            unit: '%',
            type: 'uptime',
        },
        {
            label: 'Internet',
            icon: Wifi,
            value: bwDl,
            unit: ' Mbps',
            type: 'bw',
        },
        {
            label: 'Helpdesk',
            icon: LifeBuoy,
            value: hdAvg,
            unit: '%',
            type: 'perf',
        },
    ];
});

// ── Global failed list (all categories merged) ────────────────────────────────
const deviceLogs = computed(() => {
    if (!reportData.value) return [];
    const d = reportData.value;
    const out: any[] = [];
    const push = (cat: string, rows: any[]) => {
        for (const row of rows ?? []) {
            for (const f of row.failed_list ?? []) {
                out.push({ category: cat, site: row.location, ...f });
            }
        }
    };
    push('Network', d.network);
    push('NVR', d.nvr);
    push('CCTV', d.cctv);
    push('Server', d.server);
    return out;
});

const helpdeskLogs = computed(() => {
    if (!reportData.value?.helpdesk) return [];
    return reportData.value.helpdesk.flatMap((site: any) =>
        (site.tickets ?? []).map((t: any) => ({
            ...t,
            category: 'Helpdesk',
            location: site.location,
        })),
    );
});

const bandwidthLogs = computed(() => {
    if (!reportData.value?.bandwidth) return [];
    return reportData.value.bandwidth.flatMap((site: any) =>
        (site.providers ?? []).map((p: any) => ({
            ...p,
            category: 'Bandwidth',
            location: site.location,
        })),
    );
});

const allDevicesForCategory = computed(() => {
    if (!reportData.value || !editingLog.value) return [];
    const cat = editingLog.value.category.toLowerCase();
    // Simplified: in a real app, you might want to fetch all devices via API
    // Here we'll just try to collect them from what's currently in reportData if available
    // For manual add, it's better to have a search/select from all devices.
    return [];
});

// ── Bandwidth max for bar scaling ─────────────────────────────────────────────
const bwMax = computed(() => {
    if (!reportData.value?.bandwidth) return 100;
    let m = 1;
    for (const b of reportData.value.bandwidth) {
        for (const p of b.providers ?? []) {
            if ((p.avg_download ?? 0) > m) m = p.avg_download;
            if ((p.avg_upload ?? 0) > m) m = p.avg_upload;
        }
    }
    return m;
});

// ── Category badge colours ────────────────────────────────────────────────────
const catBadge: Record<string, string> = {
    Network: 'bg-blue-50 text-blue-700 border-blue-200',
    NVR: 'bg-violet-50 text-violet-700 border-violet-200',
    CCTV: 'bg-sky-50 text-sky-700 border-sky-200',
    Server: 'bg-orange-50 text-orange-700 border-orange-200',
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
                class="mb-6 flex items-start gap-3 rounded-2xl border border-rose-100 bg-rose-50 px-5 py-4 text-rose-800 shadow-sm"
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

            <!-- ═══════════════════════════════════════════════════════════════ -->
            <!-- ROW 1 — KPI SUMMARY CARDS                                      -->
            <!-- ═══════════════════════════════════════════════════════════════ -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <div
                    v-for="card in kpiCards"
                    :key="card.label"
                    class="rounded-2xl border bg-white p-4 transition-all hover:shadow-sm"
                >
                    <div class="mb-3 flex items-center justify-between">
                        <div
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl border border-slate-100 bg-slate-50"
                        >
                            <component
                                :is="card.icon"
                                class="size-4 text-slate-500"
                            />
                        </div>
                        <span
                            v-if="card.type === 'uptime' && card.value !== null"
                            class="rounded-full border px-1.5 py-0.5 text-[8px] font-black"
                            :class="ubadge(card.value)"
                        >
                            {{ ulabel(card.value) }}
                        </span>
                    </div>
                    <p
                        class="truncate text-[9px] font-black tracking-widest text-slate-400 uppercase"
                    >
                        {{ card.label }}
                    </p>
                    <p
                        class="mt-0.5 text-[20px] leading-tight font-black tabular-nums"
                        :class="
                            card.type === 'uptime'
                                ? uc(card.value ?? 0)
                                : card.type === 'perf'
                                  ? pc(card.value ?? 0)
                                  : 'text-slate-700'
                        "
                    >
                        {{ card.value !== null ? card.value + card.unit : '—' }}
                    </p>
                    <div
                        v-if="card.type !== 'bw' && card.value !== null"
                        class="mt-2.5 h-1 overflow-hidden rounded-full bg-slate-100"
                    >
                        <div
                            class="h-full rounded-full transition-all duration-700"
                            :class="
                                card.type === 'uptime'
                                    ? ub(card.value)
                                    : pb(card.value)
                            "
                            :style="{ width: Math.min(card.value, 100) + '%' }"
                        />
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════════════ -->
            <!-- ROW 2 — UPTIME SECTIONS (Network, NVR, CCTV, Server)          -->
            <!-- ═══════════════════════════════════════════════════════════════ -->
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div
                    v-for="section in [
                        {
                            label: 'Network H/W Status Check',
                            icon: Shield,
                            key: 'network',
                            color: 'blue',
                        },
                        {
                            label: 'NVR Status',
                            icon: Activity,
                            key: 'nvr',
                            color: 'violet',
                        },
                        {
                            label: 'CCTV Status Check',
                            icon: Camera,
                            key: 'cctv',
                            color: 'sky',
                        },
                        {
                            label: 'Server Check',
                            icon: Server,
                            key: 'server',
                            color: 'orange',
                        },
                    ]"
                    :key="section.key"
                    class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm"
                >
                    <!-- Section header -->
                    <div
                        class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-3.5"
                    >
                        <div
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-[#003628]/5"
                        >
                            <component
                                :is="section.icon"
                                class="size-4 text-[#003628]"
                            />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-black text-slate-800">
                                {{ section.label }}
                            </p>
                            <p class="mt-0.5 text-[9px] text-slate-400">
                                Target Uptime &gt; 90%
                            </p>
                        </div>
                        <span
                            class="rounded-lg border px-2.5 py-1 text-[10px] font-black tabular-nums"
                            :class="ubadge(avg(reportData[section.key] ?? []))"
                        >
                            Avg {{ avg(reportData[section.key] ?? []) }}%
                        </span>
                    </div>

                    <!-- Summary table -->
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50">
                                <th
                                    class="px-4 py-2 text-left text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                >
                                    Lokasi
                                </th>
                                <th
                                    class="px-4 py-2 text-center text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                >
                                    Qty
                                </th>
                                <th
                                    class="px-4 py-2 text-center text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                >
                                    Target
                                </th>
                                <th
                                    class="px-4 py-2 text-right text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                >
                                    Uptime
                                </th>
                                <th
                                    class="px-4 py-2 text-center text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                >
                                    Status
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <tr
                                v-for="row in reportData[section.key] ?? []"
                                :key="row.location"
                                class="transition-colors hover:bg-slate-50/50"
                            >
                                <td class="px-4 py-2.5">
                                    <div class="flex items-center gap-1.5">
                                        <MapPin
                                            class="size-3 shrink-0 text-slate-300"
                                        />
                                        <span
                                            class="text-[10px] font-bold text-slate-700"
                                            >{{ row.location }}</span
                                        >
                                    </div>
                                </td>
                                <td
                                    class="px-4 py-2.5 text-center text-[10px] font-bold text-slate-500 tabular-nums"
                                >
                                    {{ row.qty }}
                                </td>
                                <td
                                    class="px-4 py-2.5 text-center text-[9px] font-bold text-slate-400"
                                >
                                    &gt; 90%
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <div class="flex flex-col items-end gap-1">
                                        <span
                                            class="text-[11px] font-black tabular-nums"
                                            :class="uc(row.uptime)"
                                            >{{ row.uptime }}%</span
                                        >
                                        <div
                                            class="h-1 w-16 overflow-hidden rounded-full bg-slate-100"
                                        >
                                            <div
                                                class="h-full rounded-full"
                                                :class="ub(row.uptime)"
                                                :style="{
                                                    width:
                                                        Math.min(
                                                            row.uptime,
                                                            100,
                                                        ) + '%',
                                                }"
                                            />
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-2.5 text-center">
                                    <span
                                        class="rounded-lg border px-2 py-0.5 text-[8px] font-black uppercase"
                                        :class="ubadge(row.uptime)"
                                    >
                                        {{ ulabel(row.uptime) }}
                                    </span>
                                </td>
                            </tr>
                            <!-- Average row -->
                            <tr
                                class="border-t border-amber-100 bg-amber-50/60"
                            >
                                <td
                                    class="px-4 py-2 text-[9px] font-black tracking-widest text-amber-700 uppercase"
                                    colspan="3"
                                >
                                    Average
                                </td>
                                <td
                                    class="px-4 py-2 text-right text-[11px] font-black tabular-nums"
                                    :class="
                                        uc(avg(reportData[section.key] ?? []))
                                    "
                                >
                                    {{ avg(reportData[section.key] ?? []) }}%
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <span
                                        class="rounded-lg border px-2 py-0.5 text-[8px] font-black uppercase"
                                        :class="
                                            ubadge(
                                                avg(
                                                    reportData[section.key] ??
                                                        [],
                                                ),
                                            )
                                        "
                                    >
                                        {{
                                            ulabel(
                                                avg(
                                                    reportData[section.key] ??
                                                        [],
                                                ),
                                            )
                                        }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════════════ -->
            <!-- ROW 4 — INTERNET USAGE (BANDWIDTH)                            -->
            <!-- ═══════════════════════════════════════════════════════════════ -->
            <div
                class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm"
            >
                <div
                    class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-3.5"
                >
                    <div
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-[#003628]/5"
                    >
                        <Wifi class="size-4 text-[#003628]" />
                    </div>
                    <div>
                        <p class="text-[11px] font-black text-slate-800">
                            Internet Usage — Bandwidth Check
                        </p>
                        <p class="mt-0.5 text-[9px] text-slate-400">
                            Avg Download / Upload per ISP per Lokasi
                        </p>
                    </div>
                </div>

                <div
                    class="grid grid-cols-1 divide-y divide-slate-100 sm:grid-cols-3 sm:divide-x sm:divide-y-0"
                >
                    <div
                        v-for="bw in reportData.bandwidth ?? []"
                        :key="bw.location"
                        class="space-y-4 p-5"
                    >
                        <!-- Location header -->
                        <div class="flex items-center gap-1.5">
                            <MapPin class="size-3 shrink-0 text-slate-400" />
                            <p
                                class="text-[9px] font-black tracking-widest text-slate-600 uppercase"
                            >
                                {{ bw.location }}
                            </p>
                        </div>

                        <div v-if="bw.providers?.length" class="space-y-4">
                            <div
                                v-for="prov in bw.providers"
                                :key="prov.provider"
                                class="space-y-2.5"
                            >
                                <!-- Provider header + Action -->
                                <div
                                    class="flex items-center justify-between gap-1.5"
                                >
                                    <div
                                        class="flex min-w-0 items-center gap-1.5"
                                    >
                                        <Zap
                                            class="size-3 shrink-0 text-slate-300"
                                        />
                                        <p
                                            class="truncate text-[9px] font-black tracking-widest text-slate-500 uppercase"
                                        >
                                            {{ prov.provider }}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        class="flex h-6 w-6 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-400 transition-all hover:text-[#003628]"
                                        @click="
                                            openEditLog({
                                                ...prov,
                                                category: 'Bandwidth',
                                            })
                                        "
                                    >
                                        <Pencil class="size-2.5" />
                                    </button>
                                </div>

                                <div class="space-y-1">
                                    <p
                                        class="text-[8px] font-black tracking-tighter text-slate-300 uppercase"
                                    >
                                        Remark
                                    </p>
                                    <p
                                        class="line-clamp-2 rounded-lg border border-slate-100 bg-slate-50/50 p-1.5 text-[10px] leading-relaxed text-slate-600 italic"
                                    >
                                        {{ prov.remark || '-' }}
                                    </p>
                                </div>

                                <!-- Download bar -->
                                <div class="space-y-1">
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <div class="flex items-center gap-1">
                                            <ArrowDown
                                                class="size-2.5 shrink-0 text-emerald-500"
                                            />
                                            <span
                                                class="text-[8px] font-bold text-slate-400 uppercase"
                                                >Download</span
                                            >
                                        </div>
                                        <span
                                            class="text-[9px] font-black tabular-nums"
                                            :class="bwText(prov.avg_download, prov.bandwidth_limit)"
                                        >
                                            {{
                                                prov.avg_download != null
                                                    ? prov.avg_download +
                                                      ' Mbps'
                                                    : '—'
                                            }}
                                        </span>
                                    </div>
                                    <div
                                        class="h-2 overflow-hidden rounded-full bg-slate-100"
                                    >
                                        <div
                                            class="h-full rounded-full transition-all duration-700"
                                            :class="bwColor(prov.avg_download, prov.bandwidth_limit)"
                                            :style="{
                                                width:
                                                    prov.avg_download != null
                                                        ? Math.min(
                                                              (prov.avg_download /
                                                                  (prov.bandwidth_limit || bwMax)) *
                                                                  100,
                                                              100,
                                                          ) + '%'
                                                        : '0%',
                                            }"
                                        />
                                    </div>
                                </div>

                                <!-- Upload bar -->
                                <div class="space-y-1">
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <div class="flex items-center gap-1">
                                            <ArrowUp
                                                class="size-2.5 shrink-0 text-sky-500"
                                            />
                                            <span
                                                class="text-[8px] font-bold text-slate-400 uppercase"
                                                >Upload</span
                                            >
                                        </div>
                                        <span
                                            class="text-[9px] font-black tabular-nums"
                                            :class="bwText(prov.avg_upload, prov.bandwidth_limit)"
                                        >
                                            {{
                                                prov.avg_upload != null
                                                    ? prov.avg_upload + ' Mbps'
                                                    : '—'
                                            }}
                                        </span>
                                    </div>
                                    <div
                                        class="h-2 overflow-hidden rounded-full bg-slate-100"
                                    >
                                        <div
                                            class="h-full rounded-full transition-all duration-700"
                                            :class="bwColor(prov.avg_upload, prov.bandwidth_limit)"
                                            :style="{
                                                width:
                                                    prov.avg_upload != null
                                                        ? Math.min(
                                                              (prov.avg_upload /
                                                                  (prov.bandwidth_limit || bwMax)) *
                                                                  100,
                                                              100,
                                                          ) + '%'
                                                        : '0%',
                                            }"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="py-6 text-center">
                            <p
                                class="text-[9px] font-black tracking-widest text-slate-300 uppercase"
                            >
                                Belum ada data
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════════════ -->
            <!-- ROW 5 — HELPDESK DAILY                                        -->
            <!-- ═══════════════════════════════════════════════════════════════ -->
            <div
                class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm"
            >
                <div
                    class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-3.5"
                >
                    <div
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-[#003628]/5"
                    >
                        <LifeBuoy class="size-4 text-[#003628]" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-[11px] font-black text-slate-800">
                            Helpdesk Daily — End User PC Issue Handling
                        </p>
                        <p class="mt-0.5 text-[9px] text-slate-400">
                            Resolution rate per lokasi
                        </p>
                    </div>
                    <span
                        class="rounded-lg border px-2.5 py-1 text-[10px] font-black tabular-nums"
                        :class="
                            ubadge(
                                avg(reportData.helpdesk ?? [], 'performance'),
                            )
                        "
                    >
                        Avg {{ avg(reportData.helpdesk ?? [], 'performance') }}%
                    </span>
                </div>

                <table class="w-full border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50">
                            <th
                                class="px-5 py-2.5 text-left text-[9px] font-black tracking-widest text-slate-400 uppercase"
                            >
                                Lokasi
                            </th>
                            <th
                                class="px-5 py-2.5 text-center text-[9px] font-black tracking-widest text-slate-400 uppercase"
                            >
                                Total Case
                            </th>
                            <th
                                class="px-5 py-2.5 text-center text-[9px] font-black tracking-widest text-slate-400 uppercase"
                            >
                                Closed
                            </th>
                            <th
                                class="px-5 py-2.5 text-center text-[9px] font-black tracking-widest text-slate-400 uppercase"
                            >
                                Open
                            </th>
                            <th
                                class="px-5 py-2.5 text-right text-[9px] font-black tracking-widest text-slate-400 uppercase"
                            >
                                Resolution Rate
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr
                            v-for="row in reportData.helpdesk ?? []"
                            :key="row.location"
                            class="transition-colors hover:bg-slate-50/50"
                        >
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-1.5">
                                    <MapPin
                                        class="size-3 shrink-0 text-slate-300"
                                    />
                                    <span
                                        class="text-[10px] font-bold text-slate-700"
                                        >{{ row.location }}</span
                                    >
                                </div>
                            </td>
                            <td
                                class="px-5 py-3 text-center text-[10px] font-bold text-slate-600 tabular-nums"
                            >
                                {{ row.case }}
                            </td>
                            <td class="px-5 py-3 text-center">
                                <span
                                    class="text-[10px] font-black text-emerald-600 tabular-nums"
                                    >{{ row.closed }}</span
                                >
                            </td>
                            <td class="px-5 py-3 text-center">
                                <span
                                    class="text-[10px] font-black tabular-nums"
                                    :class="
                                        row.case - row.closed > 0
                                            ? 'text-rose-500'
                                            : 'text-slate-300'
                                    "
                                >
                                    {{ row.case - row.closed }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <div class="flex flex-col items-end gap-1">
                                    <span
                                        class="text-[11px] font-black tabular-nums"
                                        :class="pc(row.performance)"
                                    >
                                        {{ row.performance }}%
                                    </span>
                                    <div
                                        class="h-1.5 w-20 overflow-hidden rounded-full bg-slate-100"
                                    >
                                        <div
                                            class="h-full rounded-full transition-all duration-700"
                                            :class="pb(row.performance)"
                                            :style="{
                                                width:
                                                    Math.min(
                                                        row.performance,
                                                        100,
                                                    ) + '%',
                                            }"
                                        />
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <!-- Average row -->
                        <tr class="border-t border-amber-100 bg-amber-50/60">
                            <td
                                class="px-5 py-2 text-[9px] font-black tracking-widest text-amber-700 uppercase"
                                colspan="4"
                            >
                                Average
                            </td>
                            <td
                                class="px-5 py-2 text-right text-[11px] font-black tabular-nums"
                                :class="
                                    pc(
                                        avg(
                                            reportData.helpdesk ?? [],
                                            'performance',
                                        ),
                                    )
                                "
                            >
                                {{
                                    avg(
                                        reportData.helpdesk ?? [],
                                        'performance',
                                    )
                                }}%
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Helpdesk Details (All tickets in period) -->
                <div class="border-t border-slate-100 bg-slate-50/20 px-5 py-3">
                    <p
                        class="mb-3 flex items-center gap-1.5 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                    >
                        Helpdesk Support Log
                    </p>
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-left">
                            <thead>
                                <tr class="border-b border-slate-100">
                                    <th
                                        class="pb-2 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        Lokasi
                                    </th>
                                    <th
                                        class="pb-2 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        Tanggal
                                    </th>
                                    <th
                                        class="pb-2 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        Case
                                    </th>
                                    <th
                                        class="pb-2 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        Remark
                                    </th>
                                    <th
                                        class="pb-2 text-center text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <template
                                    v-for="row in reportData.helpdesk ?? []"
                                    :key="row.location"
                                >
                                    <tr
                                        v-for="(t, ti) in row.pending_list"
                                        :key="ti"
                                        class="group transition-colors hover:bg-white"
                                    >
                                        <td
                                            class="py-2.5 pr-4 text-[10px] font-bold text-slate-600"
                                        >
                                            {{ t.location }}
                                        </td>
                                        <td
                                            class="py-2.5 pr-4 font-mono text-[9px] text-slate-500 tabular-nums"
                                        >
                                            {{ t.date }}
                                        </td>
                                        <td
                                            class="max-w-[200px] truncate py-2.5 pr-4 text-[10px] font-medium text-slate-800"
                                            :title="t.case"
                                        >
                                            {{ t.case }}
                                        </td>
                                        <td
                                            class="max-w-[200px] truncate py-2.5 pr-4 text-[10px] text-slate-400 italic"
                                            :title="t.remark"
                                        >
                                            {{ t.remark }}
                                        </td>
                                        <td class="py-2.5 text-center">
                                            <button
                                                type="button"
                                                class="mx-auto flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-400 opacity-0 transition-all group-hover:opacity-100 hover:text-[#003628]"
                                                @click="
                                                    openEditLog({
                                                        ...t,
                                                        category: 'Helpdesk',
                                                    })
                                                "
                                            >
                                                <Pencil class="size-3" />
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════════════ -->
            <!-- ROW 6 — GLOBAL MAINTENANCE / FAILED DEVICES LOG               -->
            <!-- ═══════════════════════════════════════════════════════════════ -->
            <div
                class="mt-6 overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-xl"
            >
                <!-- Header with Tabs -->
                <div class="border-b border-slate-100 bg-slate-50/50 px-6 py-5">
                    <div
                        class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
                    >
                        <div>
                            <p
                                class="text-[11px] font-black tracking-[0.2em] text-slate-900 uppercase"
                            >
                                Maintenance & Issue Logs
                            </p>
                            <p class="mt-1 text-[9px] font-bold text-slate-400">
                                Kelola catatan downtime, tiket helpdesk, dan
                                gangguan ISP
                            </p>
                        </div>

                        <!-- Sub-tabs navigation -->
                        <div
                            class="flex items-center rounded-xl bg-slate-200/50 p-1"
                        >
                            <button
                                v-for="t in ['device', 'helpdesk', 'bandwidth']"
                                :key="t"
                                @click="activeLogTab = t as any"
                                class="rounded-lg px-4 py-1.5 text-[9px] font-black tracking-widest uppercase transition-all"
                                :class="
                                    activeLogTab === t
                                        ? 'bg-white text-[#003628] shadow-sm'
                                        : 'text-slate-500 hover:text-slate-700'
                                "
                            >
                                {{
                                    t === 'device'
                                        ? 'Devices'
                                        : t === 'helpdesk'
                                          ? 'Helpdesk'
                                          : 'Internet'
                                }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tab Content: Device Logs -->
                <div v-if="activeLogTab === 'device'">
                    <div
                        class="flex items-center justify-between gap-3 border-b border-slate-100 bg-white p-4"
                    >
                        <p
                            class="text-[9px] font-black tracking-widest text-slate-400 uppercase"
                        >
                            Total: {{ deviceLogs.length }} Perangkat Bermasalah
                        </p>
                        <div class="flex items-center gap-2">
                            <button
                                v-for="cat in ['Network', 'Server', 'CCTV']"
                                :key="cat"
                                @click="openAddLog(cat)"
                                class="flex h-7 items-center gap-1.5 rounded-lg border border-[#003628]/10 bg-[#003628]/5 px-3 text-[9px] font-black tracking-widest text-[#003628] uppercase transition-all hover:bg-[#003628] hover:text-white"
                            >
                                <Plus class="size-3" /> Add {{ cat }}
                            </button>
                        </div>
                    </div>

                    <div v-if="deviceLogs.length" class="overflow-x-auto">
                        <table class="w-full border-collapse text-left">
                            <thead>
                                <tr
                                    class="border-b border-slate-100 bg-slate-50"
                                >
                                    <th
                                        class="px-4 py-2.5 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        Kategori
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        Lokasi
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        Tanggal
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        IP Address
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        Device Name
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-right text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        Duration
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        Remark
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-center text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <tr
                                    v-for="(f, i) in deviceLogs"
                                    :key="i"
                                    class="group transition-colors"
                                    :class="
                                        f.resolved_at
                                            ? 'bg-slate-50/30'
                                            : 'hover:bg-rose-50/20'
                                    "
                                >
                                    <td class="px-4 py-2.5">
                                        <span
                                            class="rounded-lg border px-2 py-0.5 text-[8px] font-black uppercase"
                                            :class="
                                                f.resolved_at
                                                    ? 'border-slate-200 bg-slate-100 text-slate-500'
                                                    : (catBadge[f.category] ??
                                                      'border-slate-200 bg-slate-50 text-slate-600')
                                            "
                                        >
                                            {{ f.category }}
                                        </span>
                                    </td>
                                    <td
                                        class="px-4 py-2.5 text-[10px] font-bold text-slate-600"
                                    >
                                        {{ f.site }}
                                    </td>
                                    <td
                                        class="px-4 py-2.5 font-mono text-[9px] text-slate-400 tabular-nums"
                                    >
                                        {{ f.report_date }}
                                    </td>
                                    <td
                                        class="px-4 py-2.5 font-mono text-[9px] text-slate-400 tabular-nums"
                                    >
                                        {{ f.ip_address }}
                                    </td>
                                    <td
                                        class="px-4 py-2.5 text-[10px] font-bold text-slate-800"
                                    >
                                        {{ f.device_name }}
                                    </td>
                                    <td class="px-4 py-2.5 text-right">
                                        <span
                                            class="rounded-lg border px-2 py-0.5 text-[9px] font-black tabular-nums"
                                            :class="
                                                f.resolved_at
                                                    ? 'border-emerald-100 bg-emerald-50 text-emerald-600 opacity-60'
                                                    : 'border-rose-100 bg-rose-50 text-rose-600'
                                            "
                                        >
                                            {{ f.duration || '0s' }}
                                        </span>
                                    </td>
                                    <td
                                        class="max-w-[200px] truncate px-4 py-2.5 text-[9px] text-slate-600 italic"
                                        :title="f.remark"
                                    >
                                        {{ f.remark || '-' }}
                                    </td>
                                    <td class="px-4 py-2.5 text-center">
                                        <button
                                            type="button"
                                            @click="openEditLog(f)"
                                            class="mx-auto flex h-7 w-7 items-center justify-center rounded-lg bg-slate-100 text-slate-400 transition-all hover:text-[#003628]"
                                        >
                                            <Pencil class="size-3" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div
                        v-else
                        class="flex flex-col items-center gap-2 py-12 opacity-30"
                    >
                        <CheckCircle2 class="size-8 text-emerald-500" />
                        <p
                            class="text-[10px] font-black tracking-widest uppercase"
                        >
                            Semua Perangkat Normal
                        </p>
                    </div>
                </div>

                <!-- Tab Content: Helpdesk Logs -->
                <div v-if="activeLogTab === 'helpdesk'">
                    <div v-if="helpdeskLogs.length" class="overflow-x-auto">
                        <table class="w-full border-collapse text-left">
                            <thead>
                                <tr
                                    class="border-b border-slate-100 bg-slate-50"
                                >
                                    <th
                                        class="px-4 py-2.5 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        Ticket ID
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        Lokasi
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        Case
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        Solution
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-center text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        Perf.
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-center text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50 text-[10px]">
                                <tr
                                    v-for="t in helpdeskLogs"
                                    :key="t.id"
                                    class="group transition-colors hover:bg-slate-50/50"
                                >
                                    <td
                                        class="px-4 py-2.5 font-bold text-slate-500"
                                    >
                                        #{{ t.ticket_no }}
                                    </td>
                                    <td
                                        class="px-4 py-2.5 font-bold text-slate-600"
                                    >
                                        {{ t.location }}
                                    </td>
                                    <td
                                        class="max-w-[200px] truncate px-4 py-2.5 font-medium text-slate-800"
                                        :title="t.case"
                                    >
                                        {{ t.case }}
                                    </td>
                                    <td
                                        class="max-w-[200px] truncate px-4 py-2.5 text-slate-500 italic"
                                        :title="t.remark"
                                    >
                                        {{ t.remark || '-' }}
                                    </td>
                                    <td class="px-4 py-2.5 text-center">
                                        <span
                                            class="rounded bg-slate-100 px-1.5 py-0.5 font-bold tabular-nums"
                                            :class="pc(t.performance)"
                                            >{{ t.performance }}%</span
                                        >
                                    </td>
                                    <td class="px-4 py-2.5 text-center">
                                        <button
                                            type="button"
                                            @click="openEditLog(t)"
                                            class="mx-auto flex h-7 w-7 items-center justify-center rounded-lg bg-slate-100 text-slate-400 transition-all hover:text-[#003628]"
                                        >
                                            <Pencil class="size-3" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div
                        v-else
                        class="flex flex-col items-center gap-2 py-12 opacity-30"
                    >
                        <LifeBuoy class="size-8 text-blue-500" />
                        <p
                            class="text-[10px] font-black tracking-widest uppercase"
                        >
                            Tidak Ada Tiket Terbuka
                        </p>
                    </div>
                </div>

                <!-- Tab Content: Bandwidth Logs -->
                <div v-if="activeLogTab === 'bandwidth'">
                    <div v-if="bandwidthLogs.length" class="overflow-x-auto">
                        <table class="w-full border-collapse text-left">
                            <thead>
                                <tr
                                    class="border-b border-slate-100 bg-slate-50"
                                >
                                    <th
                                        class="px-4 py-2.5 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        Provider
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        Lokasi
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-center text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        Download
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-center text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        Upload
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        Manual Remark
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-center text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                    >
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50 text-[10px]">
                                <tr
                                    v-for="b in bandwidthLogs"
                                    :key="b.id"
                                    class="group transition-colors hover:bg-slate-50/50"
                                >
                                    <td
                                        class="px-4 py-2.5 font-black text-[#003628]"
                                    >
                                        {{ b.provider }}
                                    </td>
                                    <td
                                        class="px-4 py-2.5 font-bold text-slate-600"
                                    >
                                        {{ b.location }}
                                    </td>
                                    <td
                                        class="px-4 py-2.5 text-center font-mono tabular-nums"
                                    >
                                        {{ b.avg_download }} Mbps
                                    </td>
                                    <td
                                        class="px-4 py-2.5 text-center font-mono tabular-nums"
                                    >
                                        {{ b.avg_upload }} Mbps
                                    </td>
                                    <td
                                        class="max-w-[250px] truncate px-4 py-2.5 text-slate-500 italic"
                                        :title="b.remark"
                                    >
                                        {{ b.remark || '-' }}
                                    </td>
                                    <td class="px-4 py-2.5 text-center">
                                        <button
                                            type="button"
                                            @click="openEditLog(b)"
                                            class="mx-auto flex h-7 w-7 items-center justify-center rounded-lg bg-slate-100 text-slate-400 transition-all hover:text-[#003628]"
                                        >
                                            <Pencil class="size-3" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div
                        v-else
                        class="flex flex-col items-center gap-2 py-12 opacity-30"
                    >
                        <Wifi class="size-8 text-[#003628]" />
                        <p
                            class="text-[10px] font-black tracking-widest uppercase"
                        >
                            Data Bandwidth Tidak Tersedia
                        </p>
                    </div>
                </div>
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

        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <!-- EDIT MAINTENANCE LOG MODAL                                         -->
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
            >
                <div
                    v-if="showEditModal"
                    class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
                    @click.self="showEditModal = false"
                >
                    <!-- Backdrop -->
                    <div
                        class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm"
                        @click="showEditModal = false"
                    />

                    <!-- Modal Card -->
                    <div
                        class="relative w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-900/20"
                    >
                        <!-- Header -->
                        <div
                            class="flex items-center justify-between border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white px-6 py-4"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#003628]/10"
                                >
                                    <Pencil class="size-4 text-[#003628]" />
                                </div>
                                <div>
                                    <h3
                                        class="text-sm font-black text-slate-800"
                                    >
                                        Edit Maintenance Log
                                    </h3>
                                    <p
                                        class="mt-0.5 max-w-[260px] truncate font-mono text-[9px] text-slate-400"
                                    >
                                        {{ editingLog?.device_name }} —
                                        {{ editingLog?.category }}
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                class="flex h-8 w-8 items-center justify-center rounded-xl border border-slate-200 text-slate-400 transition-all hover:border-slate-300 hover:text-slate-700"
                                @click="showEditModal = false"
                            >
                                <X class="size-4" />
                            </button>
                        </div>

                        <!-- Modal Body -->
                        <div class="space-y-4 p-6">
                            <!-- Device/Context info strip -->
                            <div
                                class="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50 px-4 py-3"
                            >
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="truncate text-[11px] font-black text-slate-800"
                                    >
                                        {{
                                            editingLog?.device_name ||
                                            editingLog?.location ||
                                            'Manual Entry'
                                        }}
                                    </p>
                                    <p
                                        class="font-mono text-[9px] text-slate-400"
                                    >
                                        {{ editingLog?.ip_address || '-' }} ·
                                        {{
                                            editingLog?.site ||
                                            editingLog?.location
                                        }}
                                    </p>
                                </div>
                                <span
                                    class="rounded-xl border px-2.5 py-1 text-[8px] font-black tracking-widest uppercase"
                                    :class="
                                        catBadge[editingLog?.category] ??
                                        'border-slate-200 bg-slate-50 text-slate-600'
                                    "
                                >
                                    {{ editingLog?.category }}
                                </span>
                            </div>

                            <!-- CONDITIONAL FIELDS -->

                            <!-- 0. DEVICE SELECTOR (Only for new logs) -->
                            <div
                                v-if="
                                    !editingLog?.id &&
                                    [
                                        'Network',
                                        'Server',
                                        'CCTV',
                                        'NVR',
                                    ].includes(editingLog?.category)
                                "
                                class="space-y-1.5"
                            >
                                <label
                                    class="text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                    >Pilih Perangkat</label
                                >
                                <select
                                    v-model="editForm.device_id"
                                    class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-[11px] font-bold text-slate-700 focus:ring-2 focus:ring-[#003628]/20 focus:outline-none"
                                >
                                    <option value="">-- Pilih Device --</option>
                                    <template
                                        v-if="
                                            editingLog?.category === 'Network'
                                        "
                                    >
                                        <optgroup
                                            v-for="site in reportData.network"
                                            :key="site.location"
                                            :label="site.location"
                                        >
                                            <option
                                                v-for="d in site.devices"
                                                :key="d.id"
                                                :value="d.id"
                                            >
                                                {{ d.device_name }} ({{
                                                    d.ip_address
                                                }})
                                            </option>
                                        </optgroup>
                                    </template>
                                    <template
                                        v-if="editingLog?.category === 'Server'"
                                    >
                                        <optgroup
                                            v-for="site in reportData.server"
                                            :key="site.location"
                                            :label="site.location"
                                        >
                                            <option
                                                v-for="d in site.devices"
                                                :key="d.id"
                                                :value="d.id"
                                            >
                                                {{ d.device_name }} ({{
                                                    d.ip_address
                                                }})
                                            </option>
                                        </optgroup>
                                    </template>
                                    <template
                                        v-if="
                                            editingLog?.category === 'CCTV' ||
                                            editingLog?.category === 'NVR'
                                        "
                                    >
                                        <optgroup
                                            v-for="site in reportData.cctv"
                                            :key="site.location"
                                            :label="site.location"
                                        >
                                            <option
                                                v-for="d in site.devices"
                                                :key="d.id"
                                                :value="d.id"
                                            >
                                                {{ d.device_name }} ({{
                                                    d.ip_address
                                                }})
                                            </option>
                                        </optgroup>
                                    </template>
                                </select>
                            </div>
                            <!-- 1. HELPDESK FIELDS -->
                            <template
                                v-if="editingLog?.category === 'Helpdesk'"
                            >
                                <div class="space-y-1.5">
                                    <label
                                        class="text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                        >Case / Issue Description</label
                                    >
                                    <textarea
                                        v-model="editForm.case"
                                        rows="2"
                                        class="w-full resize-none rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-[11px] font-medium text-slate-700 focus:ring-2 focus:ring-[#003628]/20 focus:outline-none"
                                    />
                                </div>
                                <div class="space-y-1.5">
                                    <label
                                        class="text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                        >Remark / Action Taken</label
                                    >
                                    <textarea
                                        v-model="editForm.remark"
                                        rows="3"
                                        class="w-full resize-none rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-[11px] font-medium text-slate-700 focus:ring-2 focus:ring-[#003628]/20 focus:outline-none"
                                    />
                                </div>
                            </template>

                            <!-- 2. BANDWIDTH FIELDS -->
                            <template
                                v-else-if="editingLog?.category === 'Bandwidth'"
                            >
                                <div class="space-y-1.5">
                                    <label
                                        class="text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                        >Manual Remark</label
                                    >
                                    <textarea
                                        v-model="editForm.remark"
                                        rows="4"
                                        placeholder="Enter manual notes for this provider link..."
                                        class="w-full resize-none rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-[11px] font-medium text-slate-700 focus:ring-2 focus:ring-[#003628]/20 focus:outline-none"
                                    />
                                </div>
                            </template>

                            <!-- 3. MAINTENANCE LOG FIELDS (Network/Server/CCTV) -->
                            <template v-else>
                                <div class="grid grid-cols-2 gap-3">
                                    <!-- Status -->
                                    <div class="space-y-1.5">
                                        <label
                                            class="text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                            >Status</label
                                        >
                                        <select
                                            v-model="editForm.status"
                                            class="h-9 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-[11px] font-bold text-slate-700 focus:ring-2 focus:ring-[#003628]/20 focus:outline-none"
                                        >
                                            <option value="open">Open</option>
                                            <option value="closed">
                                                Closed
                                            </option>
                                        </select>
                                    </div>

                                    <!-- Event Type -->
                                    <div class="space-y-1.5">
                                        <label
                                            class="text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                            >Tipe Kejadian</label
                                        >
                                        <select
                                            v-model="editForm.event_type"
                                            class="h-9 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-[11px] font-bold text-slate-700 focus:ring-2 focus:ring-[#003628]/20 focus:outline-none"
                                        >
                                            <option
                                                v-for="(
                                                    label, val
                                                ) in EVENT_LABELS"
                                                :key="val"
                                                :value="val"
                                            >
                                                {{ label }}
                                            </option>
                                        </select>
                                    </div>

                                    <!-- Started At -->
                                    <div class="space-y-1.5">
                                        <label
                                            class="text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                            >Waktu Mulai</label
                                        >
                                        <input
                                            v-model="editForm.started_at"
                                            type="datetime-local"
                                            class="h-9 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-[11px] font-bold text-slate-700 focus:ring-2 focus:ring-[#003628]/20 focus:outline-none"
                                        />
                                    </div>

                                    <!-- Resolved At -->
                                    <div class="space-y-1.5">
                                        <label
                                            class="text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                            >Waktu Selesai</label
                                        >
                                        <input
                                            v-model="editForm.resolved_at"
                                            type="datetime-local"
                                            class="h-9 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-[11px] font-bold text-slate-700 focus:ring-2 focus:ring-[#003628]/20 focus:outline-none"
                                        />
                                    </div>
                                </div>

                                <!-- Notes -->
                                <div class="space-y-1.5">
                                    <label
                                        class="text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                        >Catatan / Remark</label
                                    >
                                    <textarea
                                        v-model="editForm.notes"
                                        rows="3"
                                        placeholder="Deskripsi masalah atau tindakan yang dilakukan..."
                                        class="w-full resize-none rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-[11px] font-medium text-slate-700 focus:ring-2 focus:ring-[#003628]/20 focus:outline-none"
                                    />
                                </div>
                            </template>
                        </div>

                        <!-- Status indicator -->
                        <div
                            v-if="editForm.status === 'closed'"
                            class="flex items-center gap-2 rounded-xl border border-emerald-100 bg-emerald-50 px-3 py-2"
                        >
                            <CheckCircle2
                                class="size-3.5 shrink-0 text-emerald-500"
                            />
                            <p class="text-[9px] font-bold text-emerald-700">
                                Log ini akan ditandai sebagai selesai (Closed)
                            </p>
                        </div>
                        <div
                            v-else
                            class="flex items-center gap-2 rounded-xl border border-rose-100 bg-rose-50 px-3 py-2"
                        >
                            <AlertTriangle
                                class="size-3.5 shrink-0 text-rose-500"
                            />
                            <p class="text-[9px] font-bold text-rose-700">
                                Log ini masih berstatus Open (belum selesai)
                            </p>
                        </div>

                        <!-- Footer -->
                        <div
                            class="flex items-center justify-between border-t border-slate-100 bg-slate-50/50 px-6 py-4"
                        >
                            <button
                                type="button"
                                class="h-9 rounded-xl border border-slate-200 bg-white px-4 text-[10px] font-black tracking-widest text-slate-600 uppercase transition-all hover:bg-slate-50"
                                @click="showEditModal = false"
                            >
                                Batal
                            </button>
                            <button
                                type="button"
                                :disabled="editSaving"
                                class="flex h-9 items-center gap-2 rounded-xl bg-[#003628] px-5 text-[10px] font-black tracking-widest text-white uppercase shadow-md shadow-[#003628]/20 transition-all hover:brightness-110 disabled:opacity-50"
                                @click="saveEditLog"
                            >
                                <Loader2
                                    v-if="editSaving"
                                    class="size-3 animate-spin"
                                />
                                <Save v-else class="size-3" />
                                Simpan Perubahan
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
