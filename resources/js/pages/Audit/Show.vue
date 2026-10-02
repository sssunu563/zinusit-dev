<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Search,
    QrCode,
    CheckCircle2,
    XCircle,
    AlertCircle,
    ArrowLeft,
    RefreshCw,
    MapPin,
    User,
    Monitor,
    Package,
    History,
    Info,
    Check,
    Download,
    FileText,
    Camera,
    Zap,
    ExternalLink,
    Trash2,
} from 'lucide-vue-next';
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue';
import { Html5Qrcode } from 'html5-qrcode';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';

interface AuditItem {
    id: number;
    asset_tag: string;
    serial: string | null;
    asset_name: string | null;
    physical_location: string | null;
    expected_location: string | null;
    expected_department: string | null;
    physical_user: string | null;
    expected_user: string | null;
    status: string;
    verified_at: string | null;
    is_synced: boolean;
    notes: string | null;
    verifier?: { name?: string } | null;
}

interface AuditSession {
    id: number;
    name: string;
    description: string | null;
    status: string;
    items: AuditItem[];
}

const props = defineProps<{
    session: AuditSession;
}>();

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Stock Opname', href: '/audit' },
    { title: props.session.name, href: `/audit/${props.session.id}` },
];

// Filtering History
const historyFilter = ref('');
const statusFilter = ref('');
const locationFilter = ref('');
const departmentFilter = ref('');

const locationOptions = computed(() =>
    [
        ...new Set(
            (props.session.items || [])
                .map((item) => item.expected_location)
                .filter(Boolean),
        ),
    ].sort(),
);
const departmentOptions = computed(() =>
    [
        ...new Set(
            (props.session.items || [])
                .map((item) => item.expected_department)
                .filter(Boolean),
        ),
    ].sort(),
);

const filteredItems = computed(() => {
    return (props.session.items || []).filter((item) => {
        const matchesSearch =
            !historyFilter.value ||
            item.asset_name
                ?.toLowerCase()
                .includes(historyFilter.value.toLowerCase()) ||
            item.asset_tag
                ?.toLowerCase()
                .includes(historyFilter.value.toLowerCase()) ||
            item.serial
                ?.toLowerCase()
                .includes(historyFilter.value.toLowerCase());

        const matchesStatus =
            !statusFilter.value || item.status === statusFilter.value;
        const matchesLocation =
            !locationFilter.value ||
            item.expected_location === locationFilter.value;
        const matchesDepartment =
            !departmentFilter.value ||
            item.expected_department === departmentFilter.value;

        return (
            matchesSearch &&
            matchesStatus &&
            matchesLocation &&
            matchesDepartment
        );
    });
});

// Scanning Logic
const scanInput = ref('');
const scanning = ref(false);
const scanError = ref('');
const currentScan = ref<any>(null);
const exportMenuOpen = ref(false);

// ── Custom Confirm Modal ───────────────────────────────────
interface ConfirmModalOptions {
    title: string;
    message?: string;
    body?: string; // rich HTML-safe lines
    confirmText?: string;
    cancelText?: string;
    variant?: 'danger' | 'warning' | 'success';
    onConfirm: () => void;
}
const confirmModal = ref<ConfirmModalOptions | null>(null);
const showConfirmModal = (opts: ConfirmModalOptions) => {
    confirmModal.value = opts;
};
const closeConfirmModal = () => {
    confirmModal.value = null;
};
const handleConfirm = () => {
    confirmModal.value?.onConfirm();
    closeConfirmModal();
};

// ── Notification toast (replaces alert()) ──────────────────
interface Toast { message: string; variant: 'success' | 'error' }
const toast = ref<Toast | null>(null);
let toastTimer: ReturnType<typeof setTimeout> | null = null;
const showToast = (message: string, variant: 'success' | 'error' = 'success') => {
    toast.value = { message, variant };
    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toast.value = null; }, 4000);
};

// Camera QR Scanner State
const isCameraOpen = ref(false);
const html5QrCode = ref<Html5Qrcode | null>(null);
const cameraError = ref('');
const isCameraStarting = ref(false);
const continuousScan = ref(true);
const hasTorch = ref(false);
const isTorchOn = ref(false);

const playBeep = () => {
    try {
        const AudioCtx =
            window.AudioContext || (window as any).webkitAudioContext;
        if (!AudioCtx) return;
        const ctx = new AudioCtx();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(880, ctx.currentTime);
        gain.gain.setValueAtTime(0.2, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.15);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.15);
    } catch {
        // ignore audio policy
    }
    if (typeof navigator !== 'undefined' && 'vibrate' in navigator) {
        navigator.vibrate(80);
    }
};

const startCamera = async () => {
    isCameraOpen.value = true;
    cameraError.value = '';
    isCameraStarting.value = true;

    await nextTick();

    try {
        if (!html5QrCode.value) {
            html5QrCode.value = new Html5Qrcode('camera-qr-reader');
        }

        if (html5QrCode.value.isScanning) {
            await html5QrCode.value.stop();
        }

        await html5QrCode.value.start(
            { facingMode: 'environment' },
            {
                fps: 15,
                qrbox: (viewfinderWidth, viewfinderHeight) => {
                    const minDim = Math.min(viewfinderWidth, viewfinderHeight);
                    const size = Math.floor(minDim * 0.75);
                    return { width: size, height: size };
                },
                aspectRatio: 1.0,
            },
            (decodedText) => {
                onQrCodeScanned(decodedText);
            },
            () => {
                // scanning frame error ignored
            },
        );

        try {
            const track = (
                html5QrCode.value as any
            )?.getRunningTrackCameraCapabilities?.();
            hasTorch.value = Boolean(track?.torchFeature?.()?.isSupported?.());
        } catch {
            hasTorch.value = false;
        }
    } catch (err: any) {
        cameraError.value =
            'Tidak dapat mengakses kamera. Pastikan izin kamera telah diizinkan pada browser Anda.';
    } finally {
        isCameraStarting.value = false;
    }
};

const stopCamera = async () => {
    if (html5QrCode.value && html5QrCode.value.isScanning) {
        try {
            await html5QrCode.value.stop();
        } catch (e) {
            console.error('Error stopping scanner', e);
        }
    }
    isCameraOpen.value = false;
    isTorchOn.value = false;
};

const toggleTorch = async () => {
    if (!html5QrCode.value || !hasTorch.value) return;
    try {
        isTorchOn.value = !isTorchOn.value;
        await (html5QrCode.value as any)?.applyVideoConstraints({
            advanced: [{ torch: isTorchOn.value }],
        });
    } catch (e) {
        console.error('Torch error', e);
    }
};

const onQrCodeScanned = (decodedText: string) => {
    playBeep();
    stopCamera();
    
    const rawInput = decodedText.trim();
    
    console.log('📷 QR Scanned:', { rawInput });

    // Support scanned QR code URLs (e.g. http://127.0.0.1:8000/a/test111 or /a/test111)
    const urlMatch = rawInput.match(/\/a\/([^/?#\s]+)/) || rawInput.match(/[?&]tag=([^&#\s]+)/);
    const searchRef = urlMatch ? decodeURIComponent(urlMatch[1]) : rawInput;

    console.log('🔍 Extracted:', { searchRef });

    // Check for duplicate scan (already verified)
    const duplicate = (props.session.items || []).find(
        (item) =>
            (item.asset_tag?.toLowerCase() === searchRef.toLowerCase() ||
                item.serial?.toLowerCase() === searchRef.toLowerCase()) &&
            item.verified_at,
    );

    if (duplicate) {
        const verifiedBy =
            (duplicate as AuditItem & { verifier?: { name?: string } }).verifier
                ?.name || 'user lain';
        const verifiedAt = new Date(duplicate.verified_at!).toLocaleString(
            'id-ID',
            {
                dateStyle: 'short',
                timeStyle: 'short',
            },
        );
        scanError.value = `Asset ${duplicate.asset_tag} sudah diaudit oleh ${verifiedBy} pada ${verifiedAt}.`;
        setTimeout(() => {
            scanError.value = '';
        }, 3500);
        return;
    }

    // Set scanInput untuk manual search juga bisa pakai
    scanInput.value = searchRef;
    
    // Langsung execute scan
    executeScan(searchRef);
};

const scanForm = useForm({
    physical_location: '',
    physical_user: '',
    note: '',
    status: 'Match',
});

const executeScan = async (searchRef: string) => {
    if (!searchRef || scanning.value) return;

    console.log('📤 Executing scan:', { searchRef, sessionId: props.session.id });

    scanning.value = true;
    scanError.value = '';
    currentScan.value = null;

    try {
        const res = await axios.post(`/audit/${props.session.id}/scan`, {
            search: searchRef,
        });

        console.log('✅ Scan response:', res.data);

        const asset = res.data.asset || res.data;

        if (asset.session_item_id) {
            router.visit(
                `/audit/${props.session.id}/asset/${asset.session_item_id}/edit`,
            );
            return;
        }

        currentScan.value = asset;
        scanForm.physical_location = asset.location || '';
        scanForm.physical_user = asset.assigned_to || asset.user || '';
        scanForm.status = 'Match';
        scanForm.note = '';
    } catch (err: any) {
        console.error('❌ Scan error:', err);
        console.error('Error response:', err.response?.data);
        scanError.value =
            err.response?.data?.message ||
            'Aset tidak ditemukan dalam sistem Snipe-IT.';
    } finally {
        scanning.value = false;
        scanInput.value = '';
    }
};

const handleScan = async () => {
    if (!scanInput.value || scanning.value) return;

    const rawInput = scanInput.value.trim();

    console.log('⌨️ Manual scan:', { rawInput });

    // Support scanned QR code URLs (e.g. http://127.0.0.1:8000/a/test111 or /a/test111)
    const urlMatch = rawInput.match(/\/a\/([^/?#\s]+)/) || rawInput.match(/[?&]tag=([^&#\s]+)/);
    const searchRef = urlMatch ? decodeURIComponent(urlMatch[1]) : rawInput;

    console.log('🔍 Extracted from manual:', { searchRef });

    // Check for duplicate scan (already verified)
    const duplicate = (props.session.items || []).find(
        (item) =>
            (item.asset_tag?.toLowerCase() === searchRef.toLowerCase() ||
                item.serial?.toLowerCase() === searchRef.toLowerCase()) &&
            item.verified_at,
    );

    if (duplicate) {
        const verifiedBy =
            (duplicate as AuditItem & { verifier?: { name?: string } }).verifier
                ?.name || 'user lain';
        const verifiedAt = new Date(duplicate.verified_at!).toLocaleString(
            'id-ID',
            {
                dateStyle: 'short',
                timeStyle: 'short',
            },
        );
        scanError.value = `Asset ${duplicate.asset_tag} sudah diaudit oleh ${verifiedBy} pada ${verifiedAt}.`;
        setTimeout(() => {
            scanError.value = '';
        }, 3500);
        return;
    }

    executeScan(searchRef);
};

// Keyboard shortcuts
const handleKeyboardShortcut = (e: KeyboardEvent) => {
    if (!currentScan.value) return;

    // Alt+M = Match
    if (e.altKey && e.key.toLowerCase() === 'm') {
        scanForm.status = 'Match';
        e.preventDefault();
    }
    // Alt+D = Mismatch
    else if (e.altKey && e.key.toLowerCase() === 'd') {
        openAssetEdit();
        e.preventDefault();
    }
    // Alt+Enter = Submit
    else if (e.altKey && e.key === 'Enter') {
        submitVerification();
        e.preventDefault();
    }
};

const submitVerification = async () => {
    if (!currentScan.value) return;

    if (scanForm.status === 'Mismatch') {
        openAssetEdit();
        return;
    }

    try {
        await axios.post(`/audit/${props.session.id}/verify`, {
            snipeit_asset_id: currentScan.value.id,
            asset_tag:
                currentScan.value.asset_tag || currentScan.value.tag || '',
            serial: currentScan.value.serial || '',
            physical_location: scanForm.physical_location,
            physical_user: scanForm.physical_user,
            status: scanForm.status,
            note: scanForm.note,
            expected_location: currentScan.value.location,
            expected_user:
                currentScan.value.assigned_to || currentScan.value.user,
        });

        // Refresh session data
        router.reload({ only: ['session'] });

        // Reset form
        currentScan.value = null;
        scanForm.reset();

        // Reset and refocus input for next scan
        scanInput.value = '';
        scanInputRef.value?.focus();
    } catch (err: any) {
        alert(
            'Gagal menyimpan verifikasi: ' +
                (err.response?.data?.message || 'Unknown error'),
        );
    }
};

const openAssetEdit = () => {
    if (!currentScan.value?.session_item_id) return;

    router.visit(
        `/asset/${currentScan.value.id}/edit?type=assets&audit_session=${props.session.id}&audit_item=${currentScan.value.session_item_id}`,
    );
};

const page = usePage();
const completeError = computed(() => (page.props.errors as any)?.audit ?? null);

const missingCount = computed(() =>
    (props.session.items || []).filter((i) => !i.verified_at).length,
);
const verifiedCount = computed(() =>
    (props.session.items || []).filter((i) => i.verified_at).length,
);

const completeSession = () => {
    const total = (props.session.items || []).length;
    const missing = missingCount.value;
    const verified = verifiedCount.value;
    showConfirmModal({
        title: 'Selesaikan Audit?',
        message:
            missing > 0
                ? `${missing} asset tidak ditemukan (Missing) dan akan dicatat sebagai tidak ditemukan dalam laporan.`
                : `Semua ${total} asset telah discan dan terverifikasi.`,
        body:
            missing > 0
                ? `✅ Sudah discan: <strong>${verified}</strong> asset &nbsp;|&nbsp; ❌ Missing: <strong>${missing}</strong> asset`
                : undefined,
        confirmText: 'Ya, Selesaikan',
        variant: missing > 0 ? 'warning' : 'success',
        onConfirm: () => router.post(`/audit/${props.session.id}/complete`),
    });
};

const canCancelSession = computed(
    () =>
        props.session.status === 'Open' &&
        (props.session.items || []).every((item) => !item.verified_at),
);

const cancelSession = () => {
    if (!canCancelSession.value) return;
    showConfirmModal({
        title: 'Batalkan Sesi Audit?',
        message: 'Seluruh daftar asset di dalam sesi akan dihapus dan tidak dapat dikembalikan.',
        confirmText: 'Ya, Batalkan',
        cancelText: 'Tidak',
        variant: 'danger',
        onConfirm: () => router.delete(`/audit/${props.session.id}`),
    });
};

const syncItemToSnipe = async (item: AuditItem) => {
    showConfirmModal({
        title: 'Sinkronisasi ke Snipe-IT?',
        message: `Lokasi fisik <strong>${item.physical_location}</strong> akan diperbarui di Snipe-IT untuk aset <strong>${item.asset_tag}</strong>.`,
        confirmText: 'Sinkronkan',
        variant: 'success',
        onConfirm: async () => {
            try {
                await axios.post(`/audit/${props.session.id}/sync-item/${item.id}`);
                router.reload({ only: ['session'] });
                showToast('Data berhasil disinkronkan ke Snipe-IT.');
            } catch (err: any) {
                showToast(
                    'Gagal sinkronisasi: ' + (err.response?.data?.message || 'Error'),
                    'error',
                );
            }
        },
    });
};


const getStatusBadge = (status: string) => {
    switch (status) {
        case 'Match':
            return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        case 'Mismatch':
            return 'bg-amber-50 text-amber-700 border-amber-200';
        case 'Missing':
            return 'bg-rose-50 text-rose-700 border-rose-200';
        default:
            return 'bg-gray-50 text-gray-700 border-gray-200';
    }
};

const scanInputRef = ref<HTMLInputElement | null>(null);

onMounted(() => {
    scanInputRef.value?.focus();
    window.addEventListener('keydown', handleKeyboardShortcut);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyboardShortcut);
    stopCamera();
});
</script>

<template>
    <Head :title="session.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6">
            <!-- Header Section -->
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="space-y-1">
                    <div class="flex items-center gap-2.5">
                        <Link
                            href="/audit"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 transition-colors hover:bg-gray-50 hover:text-gray-800"
                        >
                            <ArrowLeft class="h-4 w-4" />
                        </Link>
                        <span
                            class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium"
                            :class="
                                session.status === 'Open'
                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                    : 'border-blue-200 bg-blue-50 text-blue-700'
                            "
                        >
                            {{ session.status }}
                        </span>
                    </div>
                    <h1 class="text-xl font-semibold text-gray-800">
                        {{ session.name }}
                    </h1>
                    <p class="text-sm text-gray-400">
                        {{ session.description || 'Sesi stock opname aktif.' }}
                    </p>
                </div>

                <div class="flex items-center gap-2.5">
                    <!-- Export dropdown -->
                    <div class="relative" v-if="exportMenuOpen" @click.self="exportMenuOpen = false" style="position:fixed;inset:0;z-index:40"></div>
                    <div class="relative">
                        <button
                            @click="exportMenuOpen = !exportMenuOpen"
                            class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3.5 text-xs font-medium text-gray-600 shadow-xs transition-colors hover:bg-gray-50 hover:text-gray-900"
                        >
                            <Download class="h-3.5 w-3.5" />
                            <span>Export Laporan</span>
                            <svg class="h-3 w-3 ml-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                        </button>

                        <div
                            v-if="exportMenuOpen"
                            class="absolute right-0 top-full mt-1.5 z-50 w-44 rounded-xl border border-gray-200 bg-white shadow-lg py-1"
                        >
                            <a
                                :href="`/audit/${session.id}/export`"
                                target="_blank"
                                @click="exportMenuOpen = false"
                                class="flex items-center gap-2.5 px-3.5 py-2 text-xs text-gray-700 hover:bg-gray-50 transition-colors"
                            >
                                <Download class="h-3.5 w-3.5 text-emerald-600" />
                                <span>Download Excel (.xlsx)</span>
                            </a>
                            <a
                                :href="`/audit/${session.id}/export-pdf`"
                                target="_blank"
                                @click="exportMenuOpen = false"
                                class="flex items-center gap-2.5 px-3.5 py-2 text-xs text-gray-700 hover:bg-gray-50 transition-colors"
                            >
                                <FileText class="h-3.5 w-3.5 text-rose-500" />
                                <span>Download PDF</span>
                            </a>
                        </div>
                    </div>
                    <Button
                        v-if="session.status === 'Open'"
                        @click="completeSession"
                        variant="outline"
                        class="h-9 rounded-xl border-gray-200 px-3.5 text-xs font-medium text-gray-600 transition-colors hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700"
                    >
                        <CheckCircle2 class="mr-1.5 h-3.5 w-3.5" />
                        <span>Selesaikan Audit</span>
                        <span
                            v-if="missingCount > 0"
                            class="ml-1.5 rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-700"
                        >{{ missingCount }} Missing</span>
                    </Button>
                    <Button
                        v-if="canCancelSession"
                        type="button"
                        variant="outline"
                        class="h-9 rounded-xl border-rose-200 px-3.5 text-xs font-medium text-rose-600 transition-colors hover:border-rose-300 hover:bg-rose-50"
                        @click="cancelSession"
                    >
                        <Trash2 class="mr-1.5 h-3.5 w-3.5" />
                        <span>Batalkan Audit</span>
                    </Button>
                </div>
            </div>

            <!-- Complete Error Banner -->
            <div
                v-if="completeError"
                class="mx-4 mb-0 mt-2 flex items-start gap-2.5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800"
            >
                <AlertCircle class="mt-0.5 h-4 w-4 shrink-0 text-amber-500" />
                <span>{{ completeError }}</span>
            </div>

            <!-- Top Section: Scanner (Left) & Ringkasan Audit (Right) with Equal Heights -->
            <div
                v-if="session.status === 'Open'"

                class="grid grid-cols-1 items-stretch gap-6 lg:grid-cols-12"
            >
                <!-- Left: Scan Asset Card -->
                <div
                    class="flex flex-col justify-between space-y-4 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm lg:col-span-7"
                >
                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"
                            >
                                <QrCode class="h-4 w-4" />
                            </div>
                            <div>
                                <h3 class="text-sm font-semibold text-gray-800">
                                    Scan Asset
                                </h3>
                                <p class="text-xs text-gray-400">
                                    Scan QR kamera atau ketik tag / serial
                                </p>
                            </div>
                        </div>

                        <!-- Camera Scan Button -->
                        <button
                            type="button"
                            class="flex h-10 w-full items-center justify-center gap-2 rounded-xl border border-emerald-200/70 bg-emerald-50 text-xs font-medium text-emerald-700 transition-colors hover:bg-emerald-100/80"
                            @click="startCamera"
                        >
                            <Camera class="h-4 w-4" />
                            <span>Buka Scanner Kamera HP / Laptop</span>
                        </button>

                        <!-- Divider -->
                        <div class="relative flex items-center justify-center">
                            <div class="w-full border-t border-gray-100"></div>
                            <span
                                class="absolute bg-white px-2 text-[11px] text-gray-400"
                                >atau ketik manual</span
                            >
                        </div>

                        <!-- Manual Scan Input -->
                        <div class="flex gap-2">
                            <div class="relative min-w-0 flex-1">
                                <div
                                    class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400"
                                >
                                    <Search class="h-4 w-4" />
                                </div>
                                <input
                                    ref="scanInputRef"
                                    v-model="scanInput"
                                    type="text"
                                    placeholder="Scan atau ketik Asset Tag / Serial..."
                                    class="h-10 w-full rounded-xl border border-gray-200 bg-white pr-8 pl-9 text-sm text-gray-800 transition-all placeholder:text-gray-300 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100 focus:outline-none"
                                    @keydown.enter="handleScan"
                                />
                                <div
                                    v-if="scanning"
                                    class="absolute top-1/2 right-3 -translate-y-1/2"
                                >
                                    <RefreshCw
                                        class="h-4 w-4 animate-spin text-emerald-600"
                                    />
                                </div>
                            </div>
                            <button
                                type="button"
                                class="inline-flex h-10 shrink-0 items-center justify-center gap-1.5 rounded-xl bg-gray-900 px-4 text-xs font-semibold text-white transition-colors hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="scanning || !scanInput.trim()"
                                @click="handleScan"
                            >
                                <Search class="h-3.5 w-3.5" />
                                <span>Cari Asset</span>
                            </button>
                        </div>

                        <!-- Scan Error Alert -->
                        <div
                            v-if="scanError"
                            class="flex animate-in items-start gap-2.5 rounded-xl border border-rose-100 bg-rose-50 p-3 text-xs text-rose-700 fade-in"
                        >
                            <AlertCircle
                                class="mt-0.5 h-4 w-4 shrink-0 text-rose-500"
                            />
                            <p class="leading-relaxed">{{ scanError }}</p>
                        </div>

                        <!-- Current Scanned Asset Details -->
                        <div
                            v-if="currentScan"
                            class="fixed inset-0 z-[80] flex max-h-screen animate-in items-start justify-center overflow-y-auto bg-slate-950/35 p-4 pt-10 duration-200 fade-in"
                        >
                            <div
                                class="w-full max-w-3xl space-y-4 rounded-2xl bg-white p-5 shadow-2xl"
                            >
                                <div
                                    class="flex items-center justify-between border-b border-gray-100 pb-3"
                                >
                                    <div>
                                        <h3
                                            class="text-sm font-semibold text-gray-800"
                                        >
                                            Detail Asset & Verifikasi
                                        </h3>
                                        <p class="text-xs text-gray-400">
                                            Pilih Match untuk mengunci data,
                                            atau Mismatch untuk mengedit data
                                            lapangan.
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-700"
                                        title="Tutup detail asset"
                                        @click="currentScan = null"
                                    >
                                        <XCircle class="h-5 w-5" />
                                    </button>
                                </div>

                                <div
                                    class="space-y-2.5 rounded-xl border border-gray-100 bg-gray-50 p-3.5"
                                >
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-gray-200/80 bg-white"
                                        >
                                            <img
                                                v-if="currentScan.image"
                                                :src="currentScan.image"
                                                class="h-full w-full object-cover"
                                            />
                                            <Monitor
                                                v-else
                                                class="h-5 w-5 text-gray-400"
                                            />
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div
                                                class="flex items-center justify-between gap-1"
                                            >
                                                <h4
                                                    class="truncate text-xs font-semibold text-gray-800"
                                                >
                                                    {{
                                                        currentScan.name ||
                                                        'Unnamed Asset'
                                                    }}
                                                </h4>
                                                <a
                                                    :href="`/a/${encodeURIComponent(currentScan.asset_tag || currentScan.tag || currentScan.serial)}`"
                                                    target="_blank"
                                                    class="p-1 text-gray-400 transition-colors hover:text-emerald-600"
                                                    title="Lihat detail aset"
                                                >
                                                    <ExternalLink
                                                        class="h-3.5 w-3.5"
                                                    />
                                                </a>
                                            </div>
                                            <p
                                                class="truncate text-[11px] text-gray-400"
                                            >
                                                {{
                                                    currentScan.asset_tag ||
                                                    currentScan.tag
                                                }}
                                                &bull;
                                                {{
                                                    currentScan.serial ||
                                                    'No Serial'
                                                }}
                                            </p>
                                        </div>
                                    </div>

                                    <div
                                        class="grid grid-cols-2 gap-2 border-t border-gray-200/60 pt-1 text-[11px]"
                                    >
                                        <div>
                                            <span
                                                class="block text-[10px] text-gray-400"
                                                >Lokasi Snipe-IT:</span
                                            >
                                            <span
                                                class="block truncate font-medium text-gray-700"
                                                >{{
                                                    currentScan.location || '-'
                                                }}</span
                                            >
                                        </div>
                                        <div>
                                            <span
                                                class="block text-[10px] text-gray-400"
                                                >User Snipe-IT:</span
                                            >
                                            <span
                                                class="block truncate font-medium text-gray-700"
                                                >{{
                                                    currentScan.assigned_to ||
                                                    currentScan.user ||
                                                    '-'
                                                }}</span
                                            >
                                        </div>
                                    </div>
                                </div>

                                <!-- Verification Form Inputs -->
                                <div class="space-y-3">
                                    <div
                                        class="grid grid-cols-1 gap-3 sm:grid-cols-2"
                                    >
                                        <div class="space-y-1">
                                            <label class="text-xs text-gray-500"
                                                >Nama Asset</label
                                            >
                                            <input
                                                v-model="currentScan.name"
                                                type="text"
                                                :disabled="
                                                    scanForm.status === 'Match'
                                                "
                                                class="h-9 w-full rounded-lg border border-gray-200 bg-white px-3 text-xs text-gray-800 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
                                            />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs text-gray-500"
                                                >Asset Tag</label
                                            >
                                            <input
                                                v-model="currentScan.asset_tag"
                                                type="text"
                                                :disabled="
                                                    scanForm.status === 'Match'
                                                "
                                                class="h-9 w-full rounded-lg border border-gray-200 bg-white px-3 text-xs text-gray-800 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
                                            />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs text-gray-500"
                                                >Serial</label
                                            >
                                            <input
                                                v-model="currentScan.serial"
                                                type="text"
                                                :disabled="
                                                    scanForm.status === 'Match'
                                                "
                                                class="h-9 w-full rounded-lg border border-gray-200 bg-white px-3 text-xs text-gray-800 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
                                            />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs text-gray-500"
                                                >Model / Kategori</label
                                            >
                                            <input
                                                :value="`${currentScan.model || '-'} / ${currentScan.category || '-'}`"
                                                type="text"
                                                disabled
                                                class="h-9 w-full rounded-lg border border-gray-200 bg-gray-100 px-3 text-xs text-gray-500"
                                            />
                                        </div>
                                    </div>

                                    <div
                                        class="grid grid-cols-1 gap-3 sm:grid-cols-2"
                                    >
                                        <div class="space-y-1">
                                            <label class="text-xs text-gray-500"
                                                >Lokasi Fisik (Temuan)</label
                                            >
                                            <input
                                                v-model="
                                                    scanForm.physical_location
                                                "
                                                type="text"
                                                :disabled="
                                                    scanForm.status === 'Match'
                                                "
                                                class="h-9 w-full rounded-lg border border-gray-200 bg-white px-3 text-xs text-gray-800 transition-all focus:border-emerald-400 focus:ring-1 focus:ring-emerald-200 focus:outline-none"
                                                placeholder="Contoh: IT Room Lt 2"
                                            />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs text-gray-500"
                                                >Pengguna Fisik (Temuan)</label
                                            >
                                            <input
                                                v-model="scanForm.physical_user"
                                                type="text"
                                                :disabled="
                                                    scanForm.status === 'Match'
                                                "
                                                class="h-9 w-full rounded-lg border border-gray-200 bg-white px-3 text-xs text-gray-800 transition-all focus:border-emerald-400 focus:ring-1 focus:ring-emerald-200 focus:outline-none"
                                                placeholder="Nama pemegang fisik"
                                            />
                                        </div>
                                    </div>

                                    <div class="space-y-1">
                                        <label class="text-xs text-gray-500"
                                            >Status Verifikasi</label
                                        >
                                        <div class="grid grid-cols-3 gap-2">
                                            <button
                                                type="button"
                                                @click="
                                                    scanForm.status = 'Match'
                                                "
                                                class="flex h-8 items-center justify-center gap-1 rounded-lg border text-xs font-medium transition-colors"
                                                :class="
                                                    scanForm.status === 'Match'
                                                        ? 'border-emerald-600 bg-emerald-600 text-white'
                                                        : 'border-gray-200 bg-gray-50 text-gray-600 hover:bg-gray-100'
                                                "
                                            >
                                                <CheckCircle2 class="h-3 w-3" />
                                                <span>Match</span>
                                            </button>
                                            <button
                                                type="button"
                                                @click="openAssetEdit"
                                                class="flex h-8 items-center justify-center gap-1 rounded-lg border text-xs font-medium transition-colors"
                                                :class="
                                                    scanForm.status ===
                                                    'Mismatch'
                                                        ? 'border-amber-600 bg-amber-600 text-white'
                                                        : 'border-gray-200 bg-gray-50 text-gray-600 hover:bg-gray-100'
                                                "
                                            >
                                                <AlertCircle class="h-3 w-3" />
                                                <span>Mismatch</span>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="space-y-1">
                                        <label class="text-xs text-gray-500"
                                            >Catatan Verifikator
                                            (Opsional)</label
                                        >
                                        <input
                                            v-model="scanForm.note"
                                            type="text"
                                            :disabled="
                                                scanForm.status === 'Match'
                                            "
                                            class="h-9 w-full rounded-lg border border-gray-200 bg-white px-3 text-xs text-gray-800 transition-all placeholder:text-gray-300 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-200 focus:outline-none"
                                            placeholder="Kondisi, kelengkapan, dll..."
                                        />
                                    </div>

                                    <div class="flex gap-2 pt-1">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            class="h-9 flex-1 rounded-xl border-gray-200 text-xs font-medium text-gray-600"
                                            @click="currentScan = null"
                                        >
                                            Kembali ke Audit
                                        </Button>
                                        <Button
                                            type="button"
                                            @click="submitVerification"
                                            class="h-9 flex-[1.5] rounded-xl bg-gray-900 text-xs font-medium text-white shadow-xs hover:bg-gray-800"
                                        >
                                            <Check class="mr-1.5 h-3.5 w-3.5" />
                                            <span>Konfirmasi & Kembali</span>
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Left Bottom Hint -->
                    <div
                        class="mt-auto flex items-center justify-between border-t border-gray-100/70 pt-2 text-[11px] text-gray-400"
                    >
                        <span>Tekan Enter untuk scan</span>
                        <span class="hidden sm:inline">Alt+M / Alt+D</span>
                    </div>
                </div>

                <!-- Right: Ringkasan Audit Card -->
                <div
                    class="flex flex-col justify-between space-y-4 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm lg:col-span-5"
                >
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-800">
                                    Ringkasan Audit
                                </h3>
                                <p class="text-xs text-gray-400">
                                    Statistik hasil verifikasi sesi ini
                                </p>
                            </div>
                            <span
                                class="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-700"
                            >
                                {{ session.items?.length || 0 }} Aset
                            </span>
                        </div>

                        <div class="grid grid-cols-3 gap-2.5">
                            <div
                                class="rounded-xl border border-emerald-100/60 bg-emerald-50/60 p-3.5 text-center"
                            >
                                <div
                                    class="text-xs font-medium text-emerald-700"
                                >
                                    Match
                                </div>
                                <div
                                    class="mt-1 text-2xl font-semibold text-emerald-700"
                                >
                                    {{
                                        (session.items || []).filter(
                                            (i) => i.status === 'Match',
                                        ).length
                                    }}
                                </div>
                            </div>
                            <div
                                class="rounded-xl border border-amber-100/60 bg-amber-50/60 p-3.5 text-center"
                            >
                                <div class="text-xs font-medium text-amber-700">
                                    Mismatch
                                </div>
                                <div
                                    class="mt-1 text-2xl font-semibold text-amber-700"
                                >
                                    {{
                                        (session.items || []).filter(
                                            (i) => i.status === 'Mismatch',
                                        ).length
                                    }}
                                </div>
                            </div>
                            <div
                                class="rounded-xl border border-rose-100/60 bg-rose-50/60 p-3.5 text-center"
                            >
                                <div class="text-xs font-medium text-rose-700">
                                    Missing
                                </div>
                                <div
                                    class="mt-1 text-2xl font-semibold text-rose-700"
                                >
                                    {{
                                        (session.items || []).filter(
                                            (i) => i.status === 'Missing',
                                        ).length
                                    }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Summary Info (Aligned to Bottom) -->
                    <div
                        class="mt-auto space-y-2 border-t border-gray-100/70 pt-3 text-xs text-gray-500"
                    >
                        <div class="flex items-center justify-between">
                            <span>Tingkat Kesesuaian (Match)</span>
                            <span class="font-semibold text-gray-800">
                                {{
                                    session.items?.length
                                        ? Math.round(
                                              (session.items.filter(
                                                  (i) => i.status === 'Match',
                                              ).length /
                                                  session.items.length) *
                                                  100,
                                          )
                                        : 0
                                }}%
                            </span>
                        </div>
                        <div
                            class="h-1.5 w-full overflow-hidden rounded-full bg-gray-100"
                        >
                            <div
                                class="h-full rounded-full bg-emerald-500 transition-all duration-500"
                                :style="{
                                    width: `${session.items?.length ? Math.round((session.items.filter((i) => i.status === 'Match').length / session.items.length) * 100) : 0}%`,
                                }"
                            ></div>
                        </div>
                        <div
                            class="flex items-center justify-between text-[11px] text-gray-400"
                        >
                            <span>Perlu Tindak Lanjut</span>
                            <span class="font-medium text-amber-600">
                                {{
                                    (session.items || []).filter(
                                        (i) =>
                                            i.status === 'Mismatch' ||
                                            i.status === 'Missing',
                                    ).length
                                }}
                                item
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Section fallback if Session is NOT Open (Completed/Cancelled): Ringkasan Audit full width -->
            <div
                v-else
                class="space-y-4 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800">
                            Ringkasan Audit
                        </h3>
                        <p class="text-xs text-gray-400">
                            Statistik hasil akhir verifikasi sesi ini
                        </p>
                    </div>
                    <span
                        class="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-xs font-medium text-gray-700"
                    >
                        {{ session.items?.length || 0 }} Total Aset
                        Terverifikasi
                    </span>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div
                        class="rounded-xl border border-emerald-100/60 bg-emerald-50/60 p-4 text-center"
                    >
                        <div class="text-xs font-medium text-emerald-700">
                            Match
                        </div>
                        <div
                            class="mt-1 text-2xl font-semibold text-emerald-700"
                        >
                            {{
                                (session.items || []).filter(
                                    (i) => i.status === 'Match',
                                ).length
                            }}
                        </div>
                    </div>
                    <div
                        class="rounded-xl border border-amber-100/60 bg-amber-50/60 p-4 text-center"
                    >
                        <div class="text-xs font-medium text-amber-700">
                            Mismatch
                        </div>
                        <div class="mt-1 text-2xl font-semibold text-amber-700">
                            {{
                                (session.items || []).filter(
                                    (i) => i.status === 'Mismatch',
                                ).length
                            }}
                        </div>
                    </div>
                    <div
                        class="rounded-xl border border-rose-100/60 bg-rose-50/60 p-4 text-center"
                    >
                        <div class="text-xs font-medium text-rose-700">
                            Missing
                        </div>
                        <div class="mt-1 text-2xl font-semibold text-rose-700">
                            {{
                                (session.items || []).filter(
                                    (i) => i.status === 'Missing',
                                ).length
                            }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Section: Riwayat Verifikasi (Full Width) -->
            <div
                class="w-full overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm"
            >
                <!-- Table Filter Header -->
                <div
                    class="flex flex-col justify-between gap-3 border-b border-gray-100 p-4 sm:flex-row sm:items-center"
                >
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800">
                            Riwayat Verifikasi
                        </h3>
                        <p class="text-xs text-gray-400">
                            Daftar aset yang telah diperiksa pada sesi ini
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <div class="relative">
                            <Search
                                class="absolute top-1/2 left-2.5 h-3.5 w-3.5 -translate-y-1/2 text-gray-400"
                            />
                            <input
                                v-model="historyFilter"
                                type="text"
                                placeholder="Cari aset / serial / tag..."
                                class="h-8 w-44 rounded-lg border border-gray-200 bg-white pr-3 pl-8 text-xs text-gray-800 transition-all placeholder:text-gray-300 focus:border-emerald-400 focus:outline-none sm:w-56"
                            />
                        </div>
                        <select
                            v-model="statusFilter"
                            class="h-8 rounded-lg border border-gray-200 bg-white px-2.5 text-xs text-gray-600 focus:border-emerald-400 focus:outline-none"
                        >
                            <option value="">Semua Status</option>
                            <option value="Match">Match</option>
                            <option value="Mismatch">Mismatch</option>
                            <option value="Missing">Missing</option>
                        </select>
                        <select
                            v-model="locationFilter"
                            class="h-8 max-w-40 rounded-lg border border-gray-200 bg-white px-2.5 text-xs text-gray-600 focus:border-emerald-400 focus:outline-none"
                        >
                            <option value="">Semua Lokasi</option>
                            <option
                                v-for="location in locationOptions"
                                :key="location"
                                :value="location"
                            >
                                {{ location }}
                            </option>
                        </select>
                        <select
                            v-model="departmentFilter"
                            class="h-8 max-w-40 rounded-lg border border-gray-200 bg-white px-2.5 text-xs text-gray-600 focus:border-emerald-400 focus:outline-none"
                        >
                            <option value="">Semua Departemen</option>
                            <option
                                v-for="department in departmentOptions"
                                :key="department"
                                :value="department"
                            >
                                {{ department }}
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Items List (Roomy Full-Width Layout) -->
                <div class="divide-y divide-gray-50">
                    <div
                        v-for="item in filteredItems"
                        :key="item.id"
                        class="p-4 transition-colors hover:bg-gray-50/60"
                    >
                        <div
                            class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"
                        >
                            <!-- Left: Asset Info -->
                            <div class="flex min-w-0 flex-1 items-start gap-3">
                                <div
                                    class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-gray-100 bg-gray-50 text-gray-400"
                                >
                                    <Package class="h-4 w-4" />
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div
                                        class="mb-0.5 flex flex-wrap items-center gap-2"
                                    >
                                        <h4
                                            class="text-xs font-semibold text-gray-800"
                                        >
                                            {{
                                                item.asset_name ||
                                                'Unnamed Asset'
                                            }}
                                        </h4>
                                        <span class="text-[11px] text-gray-400">
                                            Tag:
                                            <strong
                                                class="font-medium text-gray-600"
                                                >#{{ item.asset_tag }}</strong
                                            >
                                        </span>
                                        <span
                                            v-if="item.serial"
                                            class="text-[11px] text-gray-400"
                                        >
                                            &bull; SN:
                                            <span
                                                class="font-medium text-gray-600"
                                                >{{ item.serial }}</span
                                            >
                                        </span>
                                    </div>

                                    <!-- Details Row: Location & User -->
                                    <div
                                        class="mt-1 flex flex-wrap items-center gap-4 text-[11px] text-gray-500"
                                    >
                                        <div class="flex items-center gap-1.5">
                                            <MapPin
                                                class="h-3 w-3 shrink-0 text-gray-400"
                                            />
                                            <span
                                                >Lokasi Fisik:
                                                <strong
                                                    class="font-medium text-gray-700"
                                                    >{{
                                                        item.physical_location ||
                                                        '-'
                                                    }}</strong
                                                ></span
                                            >
                                            <span
                                                v-if="
                                                    item.physical_location !==
                                                    item.expected_location
                                                "
                                                class="text-[10px] text-amber-600"
                                            >
                                                (Exp:
                                                {{
                                                    item.expected_location ||
                                                    '-'
                                                }})
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <User
                                                class="h-3 w-3 shrink-0 text-gray-400"
                                            />
                                            <span
                                                >Pemegang:
                                                <strong
                                                    class="font-medium text-gray-700"
                                                    >{{
                                                        item.physical_user ||
                                                        '-'
                                                    }}</strong
                                                ></span
                                            >
                                        </div>
                                    </div>

                                    <div
                                        v-if="item.notes"
                                        class="mt-1 flex items-center gap-1 text-[11px] text-gray-400"
                                    >
                                        <Info
                                            class="h-3 w-3 shrink-0 text-gray-300"
                                        />
                                        <span>{{ item.notes }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Right: Status Badge & Actions -->
                            <div
                                class="flex shrink-0 items-center gap-2 pl-12 sm:self-center sm:pl-0"
                            >
                                <span
                                    class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium"
                                    :class="getStatusBadge(item.status)"
                                >
                                    {{ item.status }}
                                </span>

                                <span
                                    v-if="item.is_synced"
                                    class="inline-flex items-center gap-1 rounded border border-emerald-100 bg-emerald-50 px-2 py-0.5 text-[11px] text-emerald-600"
                                >
                                    <Check class="h-3 w-3" /> Synced
                                </span>
                                <button
                                    v-else-if="
                                        item.status !== 'Missing' &&
                                        item.physical_location !==
                                            item.expected_location
                                    "
                                    @click="syncItemToSnipe(item)"
                                    class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1 text-[11px] font-medium text-gray-700 transition-colors hover:bg-emerald-50 hover:text-emerald-700"
                                    title="Sinkronkan lokasi fisik ke Snipe-IT"
                                >
                                    <RefreshCw class="h-3 w-3" /> Sync Snipe-IT
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div
                        v-if="filteredItems.length === 0"
                        class="p-12 text-center"
                    >
                        <div
                            class="mx-auto mb-2.5 flex h-10 w-10 items-center justify-center rounded-full bg-gray-50 text-gray-300"
                        >
                            <History class="h-5 w-5" />
                        </div>
                        <h4 class="text-xs font-semibold text-gray-700">
                            Belum Ada Item Terverifikasi
                        </h4>
                        <p class="mt-0.5 text-[11px] text-gray-400">
                            Mulai scan QR atau masukkan tag aset untuk mencatat
                            hasil fisik.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Camera QR Scanner Modal -->
        <teleport to="body">
            <div
                v-if="isCameraOpen"
                class="fixed inset-0 z-[100] flex flex-col bg-gray-950 text-white"
            >
                <!-- Scanner Top Bar -->
                <div
                    class="z-20 flex items-center justify-between border-b border-white/10 bg-gray-950/90 p-4 backdrop-blur-sm"
                >
                    <div class="flex items-center gap-2.5">
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-500/20 text-emerald-400"
                        >
                            <Camera class="h-4 w-4" />
                        </div>
                        <div>
                            <p class="text-xs font-medium text-white">
                                Scanner Kamera QR
                            </p>
                            <p class="text-[10px] text-gray-400">
                                Arahkan ke label QR aset
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            v-if="hasTorch"
                            type="button"
                            class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/10 text-white transition-colors hover:bg-white/20"
                            :class="{ 'bg-amber-400 text-gray-950': isTorchOn }"
                            title="Senter"
                            @click="toggleTorch"
                        >
                            <Zap class="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/10 text-gray-300 transition-colors hover:bg-white/20 hover:text-white"
                            title="Tutup Kamera"
                            @click="stopCamera"
                        >
                            <XCircle class="h-5 w-5" />
                        </button>
                    </div>
                </div>

                <!-- Camera View Area -->
                <div
                    class="relative flex flex-1 flex-col items-center justify-center overflow-hidden bg-black p-4"
                >
                    <div
                        id="camera-qr-reader"
                        class="h-full max-h-[60vh] w-full max-w-sm overflow-hidden rounded-2xl border border-white/10 shadow-xl"
                    ></div>

                    <!-- Initializing Indicator -->
                    <div
                        v-if="isCameraStarting"
                        class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 bg-gray-950/85"
                    >
                        <RefreshCw
                            class="h-6 w-6 animate-spin text-emerald-400"
                        />
                        <p class="text-xs text-emerald-200">
                            Menghubungkan Kamera...
                        </p>
                    </div>

                    <!-- Error Alert -->
                    <div
                        v-if="cameraError"
                        class="absolute inset-x-6 top-1/2 z-20 -translate-y-1/2 space-y-3 rounded-2xl border border-rose-500/50 bg-gray-900 p-6 text-center shadow-xl"
                    >
                        <AlertCircle class="mx-auto h-8 w-8 text-rose-400" />
                        <p class="text-xs leading-relaxed text-rose-200">
                            {{ cameraError }}
                        </p>
                        <div class="flex justify-center gap-2 pt-2">
                            <button
                                type="button"
                                class="rounded-lg bg-white px-4 py-1.5 text-xs font-medium text-gray-900 hover:bg-gray-100"
                                @click="startCamera"
                            >
                                Coba Lagi
                            </button>
                            <button
                                type="button"
                                class="rounded-lg border border-white/20 bg-white/10 px-4 py-1.5 text-xs font-medium text-white hover:bg-white/20"
                                @click="stopCamera"
                            >
                                Tutup
                            </button>
                        </div>
                    </div>

                    <!-- Viewfinder Frame Overlay -->
                    <div
                        v-if="!isCameraStarting && !cameraError"
                        class="pointer-events-none absolute inset-0 z-10 flex flex-col items-center justify-center"
                    >
                        <div
                            class="relative h-64 w-64 overflow-hidden rounded-2xl border-2 border-emerald-400/60 shadow-[0_0_0_9999px_rgba(0,0,0,0.5)]"
                        >
                            <!-- Scanning Laser Line -->
                            <div class="laser-scanner-line" />
                            <!-- Corner Accents -->
                            <div
                                class="absolute top-2 left-2 h-4 w-4 rounded-tl border-t-2 border-l-2 border-emerald-400"
                            />
                            <div
                                class="absolute top-2 right-2 h-4 w-4 rounded-tr border-t-2 border-r-2 border-emerald-400"
                            />
                            <div
                                class="absolute bottom-2 left-2 h-4 w-4 rounded-bl border-b-2 border-l-2 border-emerald-400"
                            />
                            <div
                                class="absolute right-2 bottom-2 h-4 w-4 rounded-br border-r-2 border-b-2 border-emerald-400"
                            />
                        </div>
                        <p
                            class="mt-5 rounded-full border border-white/10 bg-gray-900/80 px-3.5 py-1 text-center text-xs text-emerald-200 backdrop-blur-xs"
                        >
                            Arahkan kamera ke QR Code label aset
                        </p>
                    </div>
                </div>

                <!-- Bottom Status / Control Bar -->
                <div
                    class="z-20 flex flex-col gap-3 border-t border-white/10 bg-gray-950/90 p-4 backdrop-blur-sm"
                >
                    <!-- If an asset was recognized -->
                    <div
                        v-if="currentScan"
                        class="flex animate-in items-center justify-between gap-3 rounded-xl border border-emerald-500/40 bg-gray-900 p-3 fade-in"
                    >
                        <div class="min-w-0">
                            <span
                                class="text-[10px] font-medium text-emerald-400"
                                >Aset Ditemukan:</span
                            >
                            <p class="truncate text-xs font-medium text-white">
                                {{ currentScan.name }}
                            </p>
                            <p class="truncate text-[10px] text-gray-400">
                                {{ currentScan.asset_tag }} &bull;
                                {{ currentScan.serial || 'Tanpa Serial' }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="shrink-0 rounded-lg bg-emerald-500 px-3 py-1.5 text-xs font-medium text-gray-950 hover:bg-emerald-400"
                            @click="stopCamera"
                        >
                            Verifikasi
                        </button>
                    </div>

                    <div
                        class="flex items-center justify-between text-xs text-gray-400"
                    >
                        <label
                            class="flex cursor-pointer items-center gap-2 select-none"
                        >
                            <input
                                v-model="continuousScan"
                                type="checkbox"
                                class="h-3.5 w-3.5 rounded border-gray-700 text-emerald-500 focus:ring-emerald-400"
                            />
                            <span class="text-xs"
                                >Mode Berkelanjutan (Keliling)</span
                            >
                        </label>
                        <button
                            type="button"
                            class="text-xs text-gray-400 hover:text-white"
                            @click="stopCamera"
                        >
                            Selesai Scan
                        </button>
                    </div>
                </div>
            </div>
        </teleport>

        <!-- ─── Custom Confirm Modal ─────────────────────────────── -->
        <teleport to="body">
            <transition name="modal-fade">
                <div
                    v-if="confirmModal"
                    class="fixed inset-0 z-[200] flex items-center justify-center p-4"
                    @click.self="closeConfirmModal"
                >
                    <!-- Backdrop -->
                    <div class="absolute inset-0 bg-gray-950/60 backdrop-blur-sm" @click="closeConfirmModal" />

                    <!-- Modal Card -->
                    <div class="relative z-10 w-full max-w-sm rounded-2xl bg-white shadow-2xl ring-1 ring-gray-200 overflow-hidden">
                        <!-- Top accent bar -->
                        <div
                            class="h-1 w-full"
                            :class="{
                                'bg-gradient-to-r from-rose-400 to-red-500': confirmModal.variant === 'danger',
                                'bg-gradient-to-r from-amber-400 to-orange-500': confirmModal.variant === 'warning',
                                'bg-gradient-to-r from-emerald-400 to-green-500': confirmModal.variant === 'success',
                            }"
                        />

                        <div class="p-6">
                            <!-- Icon + Title -->
                            <div class="flex items-start gap-3.5 mb-4">
                                <div
                                    class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl"
                                    :class="{
                                        'bg-rose-50 text-rose-500': confirmModal.variant === 'danger',
                                        'bg-amber-50 text-amber-500': confirmModal.variant === 'warning',
                                        'bg-emerald-50 text-emerald-600': confirmModal.variant === 'success',
                                    }"
                                >
                                    <svg v-if="confirmModal.variant === 'danger'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                                    <svg v-else-if="confirmModal.variant === 'warning'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" /></svg>
                                    <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                </div>
                                <div class="min-w-0">
                                    <h3 class="text-sm font-semibold text-gray-900">{{ confirmModal.title }}</h3>
                                    <p
                                        v-if="confirmModal.message"
                                        class="mt-1 text-xs leading-relaxed text-gray-500"
                                        v-html="confirmModal.message"
                                    />
                                </div>
                            </div>

                            <!-- Stats body (optional) -->
                            <div
                                v-if="confirmModal.body"
                                class="mb-4 rounded-xl border border-gray-100 bg-gray-50 px-4 py-3 text-xs text-gray-700"
                                v-html="confirmModal.body"
                            />

                            <!-- Actions -->
                            <div class="flex justify-end gap-2.5">
                                <button
                                    type="button"
                                    @click="closeConfirmModal"
                                    class="h-9 rounded-xl border border-gray-200 bg-white px-4 text-xs font-medium text-gray-600 transition-colors hover:bg-gray-50 hover:text-gray-900"
                                >
                                    {{ confirmModal.cancelText ?? 'Batal' }}
                                </button>
                                <button
                                    type="button"
                                    @click="handleConfirm"
                                    class="h-9 rounded-xl px-4 text-xs font-semibold text-white transition-colors focus:outline-none"
                                    :class="{
                                        'bg-rose-500 hover:bg-rose-600': confirmModal.variant === 'danger',
                                        'bg-amber-500 hover:bg-amber-600': confirmModal.variant === 'warning',
                                        'bg-emerald-600 hover:bg-emerald-700': confirmModal.variant === 'success' || !confirmModal.variant,
                                    }"
                                >
                                    {{ confirmModal.confirmText ?? 'Konfirmasi' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </transition>
        </teleport>

        <!-- ─── Toast Notification ────────────────────────────────── -->
        <teleport to="body">
            <transition name="toast-slide">
                <div
                    v-if="toast"
                    class="fixed bottom-6 right-6 z-[300] flex items-center gap-3 rounded-2xl px-4 py-3 shadow-xl ring-1 text-sm font-medium"
                    :class="toast.variant === 'success'
                        ? 'bg-emerald-600 text-white ring-emerald-700/30'
                        : 'bg-rose-500 text-white ring-rose-600/30'"
                >
                    <svg v-if="toast.variant === 'success'" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                    <svg v-else class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    <span>{{ toast.message }}</span>
                </div>
            </transition>
        </teleport>
    </AppLayout>
</template>

<style scoped>
.laser-scanner-line {
    position: absolute;
    left: 0;
    right: 0;
    height: 2px;
    background: linear-gradient(90deg, transparent, #34d399, transparent);
    box-shadow: 0 0 10px 1px #34d399;
    animation: scanLine 2s ease-in-out infinite;
}

@keyframes scanLine {
    0%,
    100% {
        top: 6%;
    }
    50% {
        top: 92%;
    }
}

/* Modal fade */
.modal-fade-enter-active,
.modal-fade-leave-active {
    transition: opacity 0.2s ease;
}
.modal-fade-enter-active .relative,
.modal-fade-leave-active .relative {
    transition: transform 0.2s ease, opacity 0.2s ease;
}
.modal-fade-enter-from,
.modal-fade-leave-to {
    opacity: 0;
}
.modal-fade-enter-from .relative {
    transform: scale(0.95) translateY(8px);
}

/* Toast slide */
.toast-slide-enter-active,
.toast-slide-leave-active {
    transition: transform 0.25s ease, opacity 0.25s ease;
}
.toast-slide-enter-from,
.toast-slide-leave-to {
    transform: translateY(12px);
    opacity: 0;
}
</style>
