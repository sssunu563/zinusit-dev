<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import axios from 'axios';
import {
    Search,
    Plus,
    Printer,
    Edit3,
    Trash2,
    Calendar,
    MapPin,
    UserCheck,
    CheckCircle2,
    AlertCircle,
    FileText,
    RefreshCw,
    SlidersHorizontal,
    Camera,
    Shield,
    Eye,
    Lock,
} from 'lucide-vue-next';
import CctvRegularReportFormModal from './CctvRegularReportFormModal.vue';

interface ReportItem {
    id: number;
    doc_no: string;
    location: string;
    checked_by: string;
    checked_date: string;
    week_number: number;
    year: number;
    status: string;
    areas_data?: any[];
    resolved_areas?: any[];
    loading_area_data?: any;
    beacukai_data?: any;
    maintenance_data?: any;
    signature_dept1_signer?: string;
    signature_dept1_image?: string;
    signature_dept2_signer?: string;
    signature_dept2_image?: string;
    created_at: string;
}

const reports = ref<ReportItem[]>([]);
const stats = ref<{ total: number; this_year: number; latest_doc?: string }>({
    total: 0,
    this_year: 0,
    latest_doc: '-',
});

const isLoading = ref(false);
const searchQuery = ref('');
const selectedLocation = ref('');
const selectedStatus = ref('');
const selectedYear = ref<number | ''>(new Date().getFullYear());
const selectedWeek = ref<number | ''>('');
const currentPage = ref(1);
const totalPages = ref(1);
const totalItems = ref(0);

const isModalOpen = ref(false);
const editingReportId = ref<number | null>(null);

const locationOptions = ['ZGI BGR F1', 'ZGI KRW F2', 'ZGI TNG F3'];
const currentYear = new Date().getFullYear();
const yearOptions = [currentYear - 2, currentYear - 1, currentYear, currentYear + 1];

const fetchReports = async () => {
    isLoading.value = true;
    try {
        const { data } = await axios.get('/infra-report/cctv-regular/data', {
            params: {
                page: currentPage.value,
                search: searchQuery.value || undefined,
                location: selectedLocation.value || undefined,
                status: selectedStatus.value || undefined,
                year: selectedYear.value || undefined,
                week: selectedWeek.value || undefined,
            },
        });

        if (data && data.reports) {
            reports.value = data.reports.data || [];
            currentPage.value = data.reports.current_page || 1;
            totalPages.value = data.reports.last_page || 1;
            totalItems.value = data.reports.total || 0;
        }

        if (data && data.stats) {
            stats.value = data.stats;
        }
    } catch (e) {
        console.error('Failed to load CCTV regular reports', e);
    } finally {
        isLoading.value = false;
    }
};

let debounceTimer: ReturnType<typeof setTimeout> | null = null;
watch([searchQuery, selectedLocation, selectedStatus, selectedYear, selectedWeek], () => {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        currentPage.value = 1;
        fetchReports();
    }, 300);
});

const resetFilters = () => {
    searchQuery.value = '';
    selectedLocation.value = '';
    selectedStatus.value = '';
    selectedYear.value = new Date().getFullYear();
    selectedWeek.value = '';
    currentPage.value = 1;
    fetchReports();
};

const openCreateModal = () => {
    editingReportId.value = null;
    isModalOpen.value = true;
};

const openEditModal = (id: number) => {
    editingReportId.value = id;
    isModalOpen.value = true;
};

const completeReport = async (id: number, docNo: string) => {
    const confirmed = window.confirm(
        `Verifikasi dan selesaikan laporan ${docNo}?\n\n` +
        `Dokumen yang telah diverifikasi/selesai akan terkunci sehingga hanya dapat dilihat dan dicetak/disimpan PDF.\n` +
        `Anda tetap dapat membuka kunci (unlock) sewaktu-waktu jika perlu diedit kembali.`
    );
    if (!confirmed) return;

    try {
        await axios.post(`/infra-report/cctv-regular/${id}/complete`);
        fetchReports();
    } catch (e: any) {
        alert(e.response?.data?.message || 'Gagal menyelesaikan laporan.');
    }
};

const deleteReport = async (id: number, docNo: string) => {
    if (!confirm(`Apakah Anda yakin ingin menghapus laporan ${docNo}? Data yang dihapus tidak dapat dikembalikan.`)) {
        return;
    }

    try {
        await axios.delete(`/infra-report/cctv-regular/${id}`);
        fetchReports();
    } catch (e: any) {
        alert(e.response?.data?.message || 'Gagal menghapus laporan.');
    }
};

const openPrint = (id: number) => {
    window.open(`/infra-report/cctv-regular/${id}/print`, '_blank');
};

onMounted(() => {
    fetchReports();
});
</script>

<template>
    <div class="space-y-6">
        <!-- Stats Row -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="p-5 rounded-2xl bg-slate-50/70 border border-slate-200/80 flex items-center justify-between">
                <div>
                    <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Total Arsip Laporan</span>
                    <p class="text-2xl font-black text-slate-800 tabular-nums mt-0.5">{{ stats.total }}</p>
                </div>
                <div class="h-10 w-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-[#003628] shadow-xs">
                    <FileText class="size-5" />
                </div>
            </div>

            <div class="p-5 rounded-2xl bg-slate-50/70 border border-slate-200/80 flex items-center justify-between">
                <div>
                    <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Tahun Berjalan ({{ currentYear }})</span>
                    <p class="text-2xl font-black text-slate-800 tabular-nums mt-0.5">{{ stats.this_year }}</p>
                </div>
                <div class="h-10 w-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-emerald-700 shadow-xs">
                    <Calendar class="size-5" />
                </div>
            </div>

            <div class="p-5 rounded-2xl bg-slate-50/70 border border-slate-200/80 flex items-center justify-between">
                <div>
                    <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Dokumen Terbaru</span>
                    <p class="text-xs font-black text-[#003628] mt-1 truncate max-w-[180px]">{{ stats.latest_doc || '-' }}</p>
                </div>
                <div class="h-10 w-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-blue-600 shadow-xs">
                    <Shield class="size-5" />
                </div>
            </div>
        </div>

        <!-- Filter & Action Toolbar -->
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 p-4 rounded-2xl bg-white border border-slate-200/80 shadow-xs">
            <div class="flex flex-wrap items-center gap-2.5 flex-1">
                <!-- Search Input -->
                <div class="relative min-w-[220px] flex-1 max-w-sm">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-slate-400" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Cari Doc ID, PIC, Lokasi..."
                        class="w-full h-9 pl-9 pr-3 rounded-xl border border-slate-200 bg-slate-50/40 text-xs text-slate-800 placeholder:text-slate-400 focus:bg-white focus:border-[#003628] outline-none"
                    />
                </div>

                <!-- Location Select -->
                <select
                    v-model="selectedLocation"
                    class="h-9 px-3 rounded-xl border border-slate-200 bg-slate-50/40 text-xs font-medium text-slate-700 outline-none focus:bg-white focus:border-[#003628]"
                >
                    <option value="">Semua Lokasi</option>
                    <option v-for="loc in locationOptions" :key="loc" :value="loc">{{ loc }}</option>
                </select>

                <!-- Status Filter -->
                <select
                    v-model="selectedStatus"
                    class="h-9 px-3 rounded-xl border border-slate-200 bg-slate-50/40 text-xs font-medium text-slate-700 outline-none focus:bg-white focus:border-[#003628]"
                >
                    <option value="">Semua Status</option>
                    <option value="completed">Completed (Terkunci)</option>
                    <option value="draft">Draft (Dapat Diedit)</option>
                </select>

                <!-- Year Select -->
                <select
                    v-model="selectedYear"
                    class="h-9 px-3 rounded-xl border border-slate-200 bg-slate-50/40 text-xs font-medium text-slate-700 outline-none focus:bg-white focus:border-[#003628]"
                >
                    <option value="">Semua Tahun</option>
                    <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
                </select>

                <!-- Week Number Input -->
                <input
                    v-model.number="selectedWeek"
                    type="number"
                    min="1"
                    max="53"
                    placeholder="Minggu (1-53)"
                    class="h-9 w-28 px-3 rounded-xl border border-slate-200 bg-slate-50/40 text-xs text-slate-800 outline-none focus:bg-white focus:border-[#003628]"
                />

                <!-- Reset button -->
                <button
                    type="button"
                    @click="resetFilters"
                    class="h-9 px-3 rounded-xl border border-slate-200 text-[10px] font-bold uppercase tracking-wider text-slate-500 hover:text-slate-800 hover:bg-slate-50 transition-colors flex items-center gap-1 cursor-pointer"
                >
                    <RefreshCw class="size-3" /> Reset
                </button>
            </div>

            <!-- New Report Button -->
            <button
                type="button"
                @click="openCreateModal"
                class="inline-flex items-center justify-center gap-2 h-9 px-4 rounded-xl bg-[#003628] text-white text-xs font-black uppercase tracking-wider shadow-lg shadow-[#003628]/20 hover:bg-[#004d39] transition-all active:scale-95 shrink-0 cursor-pointer"
            >
                <Plus class="size-4" />
                Buat Laporan Baru
            </button>
        </div>

        <!-- Table Container -->
        <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50/80 text-[10px] font-black uppercase tracking-wider text-slate-500">
                            <th class="py-3 px-4">Dokumen ID / Status</th>
                            <th class="py-3 px-4">Minggu &amp; Tanggal</th>
                            <th class="py-3 px-4">Checked By (PIC)</th>
                            <th class="py-3 px-4">Area Pemeriksaan (CCTV)</th>
                            <th class="py-3 px-4">Maintenance</th>
                            <th class="py-3 px-4">Pengesahan</th>
                            <th class="py-3 px-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <tr v-for="item in reports" :key="item.id" class="hover:bg-slate-50/60 transition-colors">
                            <td class="py-3.5 px-4">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-bold text-slate-900">{{ item.doc_no }}</span>
                                    <span
                                        v-if="item.status === 'completed'"
                                        class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full text-[9px] font-extrabold bg-emerald-50 text-emerald-700 border border-emerald-200"
                                        title="Laporan Terkunci"
                                    >
                                        <Lock class="size-2.5" /> Selesai
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full text-[9px] font-bold bg-amber-50 text-amber-700 border border-amber-200"
                                    >
                                        Draft
                                    </span>
                                </div>
                                <div class="flex items-center gap-1 text-[10px] text-slate-400 mt-0.5">
                                    <MapPin class="size-3" /> {{ item.location }}
                                </div>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-slate-100 text-[11px] font-black text-slate-800">
                                    Week {{ item.week_number }} &bull; {{ item.year }}
                                </div>
                                <div class="text-[10px] text-slate-400 mt-1">
                                    {{ item.checked_date }}
                                </div>
                            </td>
                            <td class="py-3.5 px-4 font-bold text-slate-800">
                                {{ item.checked_by }}
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="flex flex-col gap-1.5 max-w-xs">
                                    <div
                                        v-for="(area, aIdx) in (item.resolved_areas || [])"
                                        :key="aIdx"
                                        class="flex items-center justify-between gap-2 p-1.5 rounded-lg bg-slate-50 border border-slate-200/60 text-[11px]"
                                    >
                                        <div class="flex items-center gap-1.5 truncate">
                                            <span
                                                class="h-2 w-2 shrink-0 rounded-full"
                                                :class="(area.not_ok_qty ?? area.problem_cam ?? 0) > 0 ? 'bg-rose-500' : 'bg-emerald-500'"
                                            />
                                            <span class="font-semibold truncate text-slate-800">{{ area.name || `Area ${aIdx + 1}` }}</span>
                                        </div>
                                        <div class="flex items-center gap-1 shrink-0 text-[10px] font-medium text-slate-500">
                                            <span>{{ area.ok_qty ?? area.normal_cam ?? '-' }}/{{ area.camera_qty ?? area.total_cam ?? '-' }} OK</span>
                                            <span v-if="area.record_days || area.retention_days" class="px-1 py-0.2 rounded bg-amber-100 text-amber-800 font-bold">
                                                {{ area.record_days || area.retention_days }}h
                                            </span>
                                        </div>
                                    </div>
                                    <div v-if="!item.resolved_areas?.length" class="text-[11px] text-slate-400 italic">
                                        Tidak ada area
                                    </div>
                                </div>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="text-[11px] font-semibold text-slate-700">
                                    {{ item.maintenance_data?.items?.length || 0 }} Tindakan
                                </div>
                                <div class="text-[10px] text-slate-400 mt-0.5">
                                    {{ item.maintenance_data?.nvr_id || 'Rutin' }}
                                </div>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="flex flex-col gap-1 text-[9px] font-bold">
                                    <div class="flex items-center gap-1">
                                        <span class="text-slate-400">IT:</span>
                                        <span v-if="item.signature_dept1_image" class="text-emerald-700 flex items-center gap-0.5">
                                            <CheckCircle2 class="size-3" /> Signed
                                        </span>
                                        <span v-else class="text-slate-400">Pending</span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <span class="text-slate-400">Exim:</span>
                                        <span v-if="item.signature_dept2_image" class="text-emerald-700 flex items-center gap-0.5">
                                            <CheckCircle2 class="size-3" /> Signed
                                        </span>
                                        <span v-else class="text-slate-400">Pending</span>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="flex items-center justify-center gap-1.5">
                                    <button
                                        type="button"
                                        @click="openPrint(item.id)"
                                        class="flex h-8 items-center gap-1 px-2.5 rounded-lg border border-slate-200 bg-white text-slate-600 hover:text-[#003628] hover:border-[#003628]/40 text-[10px] font-bold transition-all shadow-2xs cursor-pointer"
                                        title="Cetak / Simpan PDF"
                                    >
                                        <Printer class="size-3.5" /> PDF
                                    </button>

                                    <!-- If Completed: View only, PDF, No edit, No delete -->
                                    <template v-if="item.status === 'completed'">
                                        <button
                                            type="button"
                                            @click="openEditModal(item.id)"
                                            class="flex h-8 items-center gap-1 px-2.5 rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 text-[10px] font-bold transition-all shadow-2xs cursor-pointer"
                                            title="Lihat Detail Laporan (Terkunci)"
                                        >
                                            <Eye class="size-3.5 text-slate-600" /> Lihat
                                        </button>
                                        <button
                                            type="button"
                                            disabled
                                            class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-100 bg-slate-50 text-slate-300 text-[10px] cursor-not-allowed"
                                            title="Laporan telah selesai & terkunci permanen (tidak dapat diedit maupun dihapus)"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </button>
                                    </template>

                                    <!-- If Draft: Edit, Complete (Icon Checklist), Delete -->
                                    <template v-else>
                                        <button
                                            type="button"
                                            @click="openEditModal(item.id)"
                                            class="flex h-8 items-center gap-1.5 px-2.5 rounded-lg border border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100 text-[10px] font-bold transition-all shadow-2xs cursor-pointer"
                                            title="Buka Halaman Edit Laporan"
                                        >
                                            <Edit3 class="size-3.5 text-blue-600" /> Edit
                                        </button>
                                        <button
                                            type="button"
                                            @click="completeReport(item.id, item.doc_no)"
                                            class="flex h-8 items-center gap-1.5 px-2.5 rounded-lg border border-emerald-300 bg-emerald-600 hover:bg-emerald-700 text-white text-[10px] font-bold transition-all shadow-2xs cursor-pointer"
                                            title="Tandai Selesai / Ceklist Verifikasi (Kunci Dokumen)"
                                        >
                                            <CheckCircle2 class="size-3.5 text-white" /> Complete
                                        </button>
                                        <button
                                            type="button"
                                            @click="deleteReport(item.id, item.doc_no)"
                                            class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-400 hover:text-rose-600 text-[10px] transition-all shadow-2xs cursor-pointer"
                                            title="Hapus Laporan Draft"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </button>
                                    </template>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!reports.length && !isLoading">
                            <td colspan="7" class="py-12 text-center text-slate-400 text-xs">
                                <FileText class="size-8 mx-auto mb-2 text-slate-300" />
                                Belum ada laporan CCTV Regular Check yang tersimpan.
                            </td>
                        </tr>
                        <tr v-if="isLoading">
                            <td colspan="7" class="py-10 text-center text-slate-400 text-xs">
                                <RefreshCw class="size-5 mx-auto mb-1 animate-spin text-[#003628]" />
                                Memuat data laporan...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <div v-if="totalPages > 1" class="flex items-center justify-between p-4 border-t border-slate-100 text-xs text-slate-500">
                <span>Menampilkan halaman {{ currentPage }} dari {{ totalPages }} ({{ totalItems }} total laporan)</span>
                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        :disabled="currentPage <= 1"
                        @click="currentPage--; fetchReports()"
                        class="px-3 py-1.5 rounded-lg border border-slate-200 font-medium disabled:opacity-40"
                    >
                        Sebelumnya
                    </button>
                    <button
                        type="button"
                        :disabled="currentPage >= totalPages"
                        @click="currentPage++; fetchReports()"
                        class="px-3 py-1.5 rounded-lg border border-slate-200 font-medium disabled:opacity-40"
                    >
                        Selanjutnya
                    </button>
                </div>
            </div>
        </div>

        <!-- Form Modal Dialog -->
        <CctvRegularReportFormModal
            v-model:open="isModalOpen"
            :report-id="editingReportId"
            @saved="fetchReports"
        />
    </div>
</template>
