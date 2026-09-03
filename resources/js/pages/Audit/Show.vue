<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { 
    Search, 
    QrCode, 
    CheckCircle2, 
    XCircle, 
    AlertCircle, 
    ArrowLeft, 
    Save, 
    RefreshCw, 
    MapPin, 
    User, 
    Monitor,
    Package,
    ShieldCheck,
    History,
    Info,
    Trash2,
    Check,
    Download,
    Filter,
    Smartphone,
    Camera,
    Zap
} from 'lucide-vue-next';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';

interface AuditItem {
    id: number;
    asset_tag: string;
    serial: string | null;
    asset_name: string | null;
    physical_location: string | null;
    expected_location: string | null;
    physical_user: string | null;
    expected_user: string | null;
    status: string;
    verified_at: string | null;
    is_synced: boolean;
    note: string | null;
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

const filteredItems = computed(() => {
    return props.session.items.filter(item => {
        const matchesSearch = !historyFilter.value || 
            (item.asset_name?.toLowerCase().includes(historyFilter.value.toLowerCase()) || 
             item.asset_tag.toLowerCase().includes(historyFilter.value.toLowerCase()) ||
             item.serial?.toLowerCase().includes(historyFilter.value.toLowerCase()));
        
        const matchesStatus = !statusFilter.value || item.status === statusFilter.value;
        
        return matchesSearch && matchesStatus;
    });
});

// Scanning Logic
const scanInput = ref('');
const scanning = ref(false);
const scanError = ref('');
const currentScan = ref<any>(null);
const isMobileView = ref(false);
const useQrScanner = ref(false);

const scanForm = useForm({
    physical_location: '',
    physical_user: '',
    note: '',
    status: 'Match'
});

const handleScan = async () => {
    if (!scanInput.value || scanning.value) return;
    
    const scanTag = scanInput.value.trim();
    
    // Check for duplicate scan (already verified)
    const duplicate = props.session.items.find(item => 
        item.asset_tag === scanTag && item.verified_at
    );
    
    if (duplicate) {
        scanError.value = `âš ï¸ Aset ${scanTag} sudah diverifikasi pada ${new Date(duplicate.verified_at!).toLocaleTimeString('id-ID')}`;
        scanInput.value = '';
        // Auto-clear error after 3 seconds
        setTimeout(() => {
            scanError.value = '';
        }, 3000);
        return;
    }
    
    scanning.value = true;
    scanError.value = '';
    currentScan.value = null;
    
    try {
        const res = await axios.post(`/audit/${props.session.id}/scan`, {
            search: scanTag
        });
        
        currentScan.value = res.data.asset;
        scanForm.physical_location = res.data.asset.location || '';
        scanForm.physical_user = res.data.asset.assigned_to || '';
        scanForm.status = 'Match';
        
        // Auto focus note field for user to add notes if needed
    } catch (err: any) {
        scanError.value = err.response?.data?.message || 'Aset tidak ditemukan dalam sistem Snipe-IT.';
    } finally {
        scanning.value = false;
        scanInput.value = '';
    }
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
        scanForm.status = 'Mismatch';
        e.preventDefault();
    }
    // Alt+X = Missing
    else if (e.altKey && e.key.toLowerCase() === 'x') {
        scanForm.status = 'Missing';
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
    
    try {
        await axios.post(`/audit/${props.session.id}/verify`, {
            snipeit_asset_id: currentScan.value.id,
            asset_tag: currentScan.value.tag,
            serial: currentScan.value.serial,
            physical_location: scanForm.physical_location,
            physical_user: scanForm.physical_user,
            status: scanForm.status,
            note: scanForm.note,
            expected_location: currentScan.value.location,
            expected_user: currentScan.value.user
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
        alert('Gagal menyimpan verifikasi: ' + (err.response?.data?.message || 'Unknown error'));
    }
};

const completeSession = () => {
    if (!confirm('Apakah Anda yakin ingin menyelesaikan sesi audit ini? Semua data yang belum disinkronkan akan ditandai untuk diproses.')) return;
    router.post(`/audit/${props.session.id}/complete`);
};

const syncItemToSnipe = async (item: AuditItem) => {
    if (!confirm(`Sinkronkan lokasi fisik (${item.physical_location}) ke Snipe-IT untuk aset ${item.asset_tag}?`)) return;
    
    try {
        await axios.post(`/audit/${props.session.id}/sync-item/${item.id}`);
        router.reload({ only: ['session'] });
        alert('Data berhasil disinkronkan ke Snipe-IT.');
    } catch (err: any) {
        alert('Gagal sinkronisasi: ' + (err.response?.data?.message || 'Error'));
    }
};

const getStatusBadge = (status: string) => {
    switch (status) {
        case 'Match': return 'bg-emerald-100 text-emerald-700 border-emerald-200';
        case 'Mismatch': return 'bg-amber-100 text-amber-700 border-amber-200';
        case 'Missing': return 'bg-rose-100 text-rose-700 border-rose-200';
        default: return 'bg-slate-100 text-slate-700 border-slate-200';
    }
};

const scanInputRef = ref<HTMLInputElement | null>(null);
onMounted(() => {
    // Check mobile view
    isMobileView.value = window.innerWidth < 1024;
    window.addEventListener('resize', () => {
        isMobileView.value = window.innerWidth < 1024;
    });
    
    scanInputRef.value?.focus();
    // Add keyboard shortcut listener
    window.addEventListener('keydown', handleKeyboardShortcut);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyboardShortcut);
});
</script>

<template>
    <Head :title="session.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="app-page-shell">
            <!-- Header Section -->
            <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-8">
                <div>
                    <div class="flex items-center gap-3 mb-2">
                        <Link href="/audit" class="h-8 w-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 hover:bg-[#003628] hover:text-white transition-all">
                            <ArrowLeft class="w-4 h-4" />
                        </Link>
                        <span class="px-3 py-1 rounded-full bg-[#003628]/5 text-[#003628] text-[10px] font-black uppercase tracking-widest border border-[#003628]/10">{{ session.status }}</span>
                    </div>
                    <h1 class="text-3xl font-black text-[#003628] tracking-tight uppercase">{{ session.name }}</h1>
                    <p class="text-sm font-medium text-slate-400 mt-1">{{ session.description || 'Sesi stock opname aktif.' }}</p>
                </div>

                <div v-if="session.status === 'Open'" class="flex items-center gap-3">
                    <a :href="route('audit.export', session.id)" target="_blank" class="h-12 px-6 rounded-2xl border border-slate-200 bg-white flex items-center gap-2 text-slate-600 hover:text-[#003628] transition-all text-xs font-black uppercase tracking-widest shadow-sm">
                        <Download class="w-4 h-4" /> Export Report
                    </a>
                    <Button @click="completeSession" variant="outline" class="h-12 rounded-2xl border-slate-200 text-slate-600 hover:bg-emerald-50 hover:text-emerald-700 hover:border-emerald-200 text-xs font-black uppercase tracking-widest px-8 transition-all">
                        <CheckCircle2 class="w-4 h-4 mr-2" /> Selesaikan Audit
                    </Button>
                </div>
                <div v-else class="flex items-center gap-3">
                    <a :href="route('audit.export', session.id)" target="_blank" class="h-12 px-6 rounded-2xl bg-[#003628] flex items-center gap-2 text-white hover:brightness-110 transition-all text-xs font-black uppercase tracking-widest shadow-xl shadow-emerald-900/20">
                        <Download class="w-4 h-4" /> Download Final Report
                    </a>
                </div>
            </div>

            <!-- Mobile vs Desktop Layout -->
            <div v-if="isMobileView" class="space-y-8">
                <!-- Mobile Optimized Layout: Full-screen scanning -->
                <div v-if="session.status === 'Open'" class="bg-[#003628] rounded-[40px] p-6 text-white relative overflow-hidden shadow-2xl shadow-emerald-900/20">
                    <div class="absolute -top-10 -right-10 w-40 h-40 bg-white/5 rounded-full blur-3xl"></div>
                    <div class="absolute -bottom-10 -left-10 w-40 h-40 bg-[#d99528]/10 rounded-full blur-3xl"></div>
                    
                    <div class="relative z-10 space-y-6">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center">
                                <Smartphone class="w-6 h-6 text-emerald-200" />
                            </div>
                            <h3 class="text-xl font-black uppercase tracking-tight">Mobile Scanner</h3>
                        </div>

                        <div class="relative group">
                            <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none text-emerald-200/40 group-focus-within:text-emerald-200">
                                <Search class="w-5 h-5" />
                            </div>
                            <input 
                                ref="scanInputRef"
                                v-model="scanInput"
                                type="text" 
                                placeholder="Scan atau ketik Asset Tag..." 
                                class="w-full h-16 pl-12 pr-4 rounded-2xl border-2 border-emerald-400/20 bg-white/10 text-white placeholder:text-emerald-200/30 focus:bg-white/20 focus:border-emerald-400/40 transition-all outline-none font-bold text-lg"
                                @keydown.enter="handleScan"
                            />
                            <div v-if="scanning" class="absolute right-4 top-1/2 -translate-y-1/2">
                                <RefreshCw class="w-6 h-6 animate-spin text-emerald-200" />
                            </div>
                        </div>

                        <div class="text-[9px] font-bold text-emerald-200/50 px-2">
                            ðŸ’¡ Gunakan barcode scanner atau ketik tag asset
                        </div>

                        <div v-if="scanError" class="p-4 bg-rose-500/20 rounded-2xl border border-rose-500/30 flex items-start gap-3 animate-in fade-in slide-in-from-top-2">
                            <AlertCircle class="w-5 h-5 text-rose-300 shrink-0 mt-0.5" />
                            <p class="text-[11px] font-bold text-rose-100 leading-relaxed">{{ scanError }}</p>
                        </div>

                        <!-- Current Asset Found (Mobile Optimized) -->
                        <div v-if="currentScan" class="mt-6 space-y-4 animate-in fade-in slide-in-from-bottom-4 duration-500">
                            <div class="p-4 bg-white/5 rounded-2xl border border-white/10">
                                <div class="flex items-center gap-3 mb-3">
                                    <div class="w-12 h-12 rounded-xl bg-white/10 flex items-center justify-center border border-white/10">
                                        <Monitor class="w-6 h-6 text-emerald-200" />
                                    </div>
                                    <div class="flex-1">
                                        <h4 class="text-sm font-black text-white uppercase tracking-tight line-clamp-1">{{ currentScan.name }}</h4>
                                        <p class="text-[10px] font-bold text-emerald-200/60 uppercase tracking-widest">{{ currentScan.asset_tag }}</p>
                                    </div>
                                </div>

                                <div class="space-y-2 text-[10px] font-bold text-emerald-200/60">
                                    <div>ðŸ“ Expected: {{ currentScan.location || '-' }}</div>
                                    <div>ðŸ‘¤ Expected: {{ currentScan.assigned_to || '-' }}</div>
                                </div>
                            </div>

                            <div class="space-y-3">
                                <div class="space-y-1">
                                    <label class="text-[9px] font-black uppercase tracking-widest text-emerald-200/60">Location (found)</label>
                                    <input v-model="scanForm.physical_location" type="text" class="app-input-shell h-12 w-full px-4 rounded-xl border-white/10 bg-white/5 text-base font-bold text-white focus:bg-white/10" />
                                </div>

                                <div class="space-y-1">
                                    <label class="text-[9px] font-black uppercase tracking-widest text-emerald-200/60">User (found)</label>
                                    <input v-model="scanForm.physical_user" type="text" class="app-input-shell h-12 w-full px-4 rounded-xl border-white/10 bg-white/5 text-base font-bold text-white focus:bg-white/10" />
                                </div>

                                <div class="space-y-1">
                                    <label class="text-[9px] font-black uppercase tracking-widest text-emerald-200/60">Status</label>
                                    <div class="grid grid-cols-3 gap-2">
                                        <button 
                                            v-for="s in ['Match', 'Mismatch', 'Missing']" 
                                            :key="s"
                                            @click="scanForm.status = s"
                                            type="button"
                                            class="h-12 rounded-xl text-xs font-black uppercase tracking-widest transition-all border font-bold"
                                            :class="scanForm.status === s ? 'bg-[#d99528] border-[#d99528] text-white' : 'bg-white/5 border-white/10 text-emerald-200/60'"
                                        >
                                            {{ s }}
                                        </button>
                                    </div>
                                </div>

                                <div class="space-y-1">
                                    <label class="text-[9px] font-black uppercase tracking-widest text-emerald-200/60">Note</label>
                                    <input v-model="scanForm.note" type="text" class="app-input-shell h-12 w-full px-4 rounded-xl border-white/10 bg-white/5 text-sm font-medium text-white focus:bg-white/10" placeholder="Kondisi, kelengkapan, dll..." />
                                </div>

                                <Button @click="submitVerification" class="w-full h-14 rounded-2xl bg-white text-[#003628] hover:bg-emerald-100 text-sm font-black uppercase tracking-[0.2em] shadow-xl shadow-black/20 mt-2">
                                    âœ“ Simpan & Lanjut
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mobile Statistics -->
                <div class="bg-white rounded-[30px] p-6 border border-slate-100 shadow-lg">
                    <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest mb-4">Ringkasan Audit</h3>

                    <div class="space-y-3">
                        <div class="flex items-center justify-between p-3 bg-emerald-50 rounded-xl border border-emerald-100">
                            <span class="text-xs font-bold text-slate-600">âœ… Match</span>
                            <span class="text-2xl font-black text-emerald-600">{{ session.items.filter(i => i.status === 'Match').length }}</span>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-amber-50 rounded-xl border border-amber-100">
                            <span class="text-xs font-bold text-slate-600">âš ï¸ Mismatch</span>
                            <span class="text-2xl font-black text-amber-600">{{ session.items.filter(i => i.status === 'Mismatch').length }}</span>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-rose-50 rounded-xl border border-rose-100">
                            <span class="text-xs font-bold text-slate-600">âŒ Missing</span>
                            <span class="text-2xl font-black text-rose-600">{{ session.items.filter(i => i.status === 'Missing').length }}</span>
                        </div>
                    </div>

                    <div class="mt-4 pt-4 border-t border-slate-100">
                        <div class="text-[10px] font-black text-slate-400 uppercase mb-2">Items Verified</div>
                        <div class="text-3xl font-black text-[#003628]">{{ session.items.length }}</div>
                    </div>
                </div>

                <!-- Mobile Activity List -->
                <div class="bg-white rounded-[30px] border border-slate-100 shadow-lg overflow-hidden">
                    <div class="p-6 border-b border-slate-50">
                        <h3 class="text-sm font-black text-slate-800 uppercase tracking-tight">Recent Activity</h3>
                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-[0.2em] mt-1">Terakhir diverifikasi</p>
                    </div>

                    <div class="divide-y divide-slate-50 max-h-96 overflow-y-auto">
                        <div v-for="item in filteredItems.slice(0, 10)" :key="item.id" class="p-4">
                            <div class="flex items-start gap-3 mb-2">
                                <div class="w-10 h-10 rounded-lg bg-slate-50 flex items-center justify-center shrink-0 border border-slate-100">
                                    <Package class="w-5 h-5 text-slate-300" />
                                </div>
                                
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between mb-1">
                                        <h4 class="text-xs font-black text-slate-800 uppercase tracking-tight line-clamp-1">{{ item.asset_name || 'Unnamed' }}</h4>
                                        <span class="text-[8px] font-black text-slate-400">#{{ item.asset_tag }}</span>
                                    </div>
                                    <span class="inline-block px-2 py-0.5 rounded-lg text-[8px] font-black uppercase tracking-widest border" :class="getStatusBadge(item.status)">{{ item.status }}</span>
                                </div>
                            </div>
                            
                            <div class="text-[9px] font-bold text-slate-500 space-y-0.5">
                                <div>ðŸ“ {{ item.physical_location || '-' }}</div>
                                <div v-if="item.note" class="italic text-slate-400">{{ item.note }}</div>
                            </div>
                        </div>

                        <div v-if="filteredItems.length === 0" class="p-12 text-center">
                            <div class="text-slate-300 mb-2">ðŸ“¦</div>
                            <p class="text-[10px] font-bold text-slate-400">No items verified yet</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Desktop Layout -->
            <div v-else class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                <!-- Left Column: Scanner & Verification -->
                <div class="lg:col-span-5 space-y-8">
                    <div v-if="session.status === 'Open'" class="bg-[#003628] rounded-[40px] p-8 text-white relative overflow-hidden shadow-2xl shadow-emerald-900/20">
                        <div class="absolute -top-10 -right-10 w-40 h-40 bg-white/5 rounded-full blur-3xl"></div>
                        <div class="absolute -bottom-10 -left-10 w-40 h-40 bg-[#d99528]/10 rounded-full blur-3xl"></div>
                        
                        <div class="relative z-10">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center">
                                    <QrCode class="w-6 h-6 text-emerald-200" />
                                </div>
                                <h3 class="text-xl font-black uppercase tracking-tight">Scan Asset</h3>
                            </div>

                            <div class="relative group mb-6">
                                <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none text-emerald-200/40 group-focus-within:text-emerald-200">
                                    <Search class="w-5 h-5" />
                                </div>
                                <input 
                                    ref="scanInputRef"
                                    v-model="scanInput"
                                    type="text" 
                                    placeholder="Scan Asset Tag atau Serial..." 
                                    class="w-full h-14 pl-12 pr-4 rounded-2xl border-2 border-emerald-400/20 bg-white/10 text-white placeholder:text-emerald-200/30 focus:bg-white/20 focus:border-emerald-400/40 transition-all outline-none font-bold"
                                    @keydown.enter="handleScan"
                                />
                                <div v-if="scanning" class="absolute right-4 top-1/2 -translate-y-1/2">
                                    <RefreshCw class="w-5 h-5 animate-spin text-emerald-200" />
                                </div>
                            </div>

                            <div class="text-[9px] font-bold text-emerald-200/50 px-2 mb-4">
                                ðŸ’¡ Shortcuts: Alt+M (Match) | Alt+D (Mismatch) | Alt+X (Missing) | Alt+Enter (Submit)
                            </div>

                            <div v-if="scanError" class="p-4 bg-rose-500/20 rounded-2xl border border-rose-500/30 flex items-start gap-3 animate-in fade-in slide-in-from-top-2">
                                <AlertCircle class="w-5 h-5 text-rose-300 shrink-0 mt-0.5" />
                                <p class="text-[11px] font-bold text-rose-100 leading-relaxed">{{ scanError }}</p>
                            </div>

                            <!-- Current Asset Found -->
                            <div v-if="currentScan" class="mt-8 space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-500">
                                <div class="p-5 bg-white/5 rounded-3xl border border-white/10">
                                    <div class="flex items-center gap-4 mb-4">
                                        <div class="w-14 h-14 rounded-2xl bg-white/10 flex items-center justify-center border border-white/10">
                                            <Monitor class="w-7 h-7 text-emerald-200" />
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-black text-white uppercase tracking-tight">{{ currentScan.name }}</h4>
                                            <p class="text-[10px] font-bold text-emerald-200/60 uppercase tracking-widest">{{ currentScan.asset_tag }} â€¢ {{ currentScan.serial || 'No Serial' }}</p>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-3 mb-2">
                                        <div class="bg-black/20 p-3 rounded-xl border border-white/5">
                                            <p class="text-[9px] font-black text-emerald-200/40 uppercase tracking-widest mb-1">Expected Location</p>
                                            <p class="text-xs font-bold text-emerald-100 truncate">{{ currentScan.location || '-' }}</p>
                                        </div>
                                        <div class="bg-black/20 p-3 rounded-xl border border-white/5">
                                            <p class="text-[9px] font-black text-emerald-200/40 uppercase tracking-widest mb-1">Expected User</p>
                                            <p class="text-xs font-bold text-emerald-100 truncate">{{ currentScan.assigned_to || '-' }}</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-4">
                                    <div class="grid grid-cols-2 gap-4">
                                        <div class="space-y-1.5">
                                            <label class="text-[9px] font-black uppercase tracking-widest text-emerald-200/60 ml-1">Physical Location</label>
                                            <input v-model="scanForm.physical_location" type="text" class="app-input-shell h-11 w-full px-4 rounded-xl border-white/10 bg-white/5 text-sm font-bold text-white focus:bg-white/10" />
                                        </div>
                                        <div class="space-y-1.5">
                                            <label class="text-[9px] font-black uppercase tracking-widest text-emerald-200/60 ml-1">Physical User</label>
                                            <input v-model="scanForm.physical_user" type="text" class="app-input-shell h-11 w-full px-4 rounded-xl border-white/10 bg-white/5 text-sm font-bold text-white focus:bg-white/10" />
                                        </div>
                                    </div>

                                    <div class="space-y-1.5">
                                        <label class="text-[9px] font-black uppercase tracking-widest text-emerald-200/60 ml-1">Audit Status</label>
                                        <div class="grid grid-cols-3 gap-2">
                                            <button 
                                                v-for="s in ['Match', 'Mismatch', 'Missing']" 
                                                :key="s"
                                                @click="scanForm.status = s"
                                                type="button"
                                                class="h-10 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all border"
                                                :class="scanForm.status === s ? 'bg-[#d99528] border-[#d99528] text-white' : 'bg-white/5 border-white/10 text-emerald-200/60 hover:bg-white/10'"
                                            >
                                                {{ s }}
                                            </button>
                                        </div>
                                    </div>

                                    <div class="space-y-1.5">
                                        <label class="text-[9px] font-black uppercase tracking-widest text-emerald-200/60 ml-1">Verificator Note</label>
                                        <input v-model="scanForm.note" type="text" class="app-input-shell h-11 w-full px-4 rounded-xl border-white/10 bg-white/5 text-sm font-medium text-white focus:bg-white/10" placeholder="Kondisi fisik, kelengkapan, dll..." />
                                    </div>

                                    <Button @click="submitVerification" class="w-full h-12 rounded-2xl bg-white text-[#003628] hover:bg-emerald-100 text-xs font-black uppercase tracking-[0.2em] shadow-xl shadow-black/20">
                                        Simpan Verifikasi
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Statistics Card -->
                    <div class="bg-white rounded-[40px] p-8 border border-slate-100 shadow-xl shadow-[#003628]/5">
                        <div class="flex items-center justify-between mb-8">
                            <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest">Audit Summary</h3>
                            <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-slate-50 text-[10px] font-black text-slate-400">
                                <History class="w-3 h-3" /> Realtime Data
                            </div>
                        </div>

                        <div class="space-y-6">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                                        <CheckCircle2 class="w-5 h-5" />
                                    </div>
                                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Match</span>
                                </div>
                                <span class="text-lg font-black text-slate-800">{{ session.items.filter(i => i.status === 'Match').length }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                                        <AlertCircle class="w-5 h-5" />
                                    </div>
                                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Mismatch</span>
                                </div>
                                <span class="text-lg font-black text-slate-800">{{ session.items.filter(i => i.status === 'Mismatch').length }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center">
                                        <XCircle class="w-5 h-5" />
                                    </div>
                                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Missing</span>
                                </div>
                                <span class="text-lg font-black text-slate-800">{{ session.items.filter(i => i.status === 'Missing').length }}</span>
                            </div>
                        </div>

                        <div class="mt-8 pt-8 border-t border-slate-50">
                            <div class="flex items-center justify-between text-[10px] font-black uppercase tracking-[0.2em] mb-3">
                                <span class="text-slate-400">Progress</span>
                                <span class="text-[#003628]">{{ session.items.length }} Items Verified</span>
                            </div>
                            <div class="h-2 w-full bg-slate-50 rounded-full overflow-hidden">
                                <div class="h-full bg-[#003628] rounded-full transition-all duration-1000" :style="{ width: '100%' }"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Verification History -->
                <div class="lg:col-span-7">
                    <div class="bg-white rounded-[40px] border border-slate-100 shadow-xl shadow-[#003628]/5 overflow-hidden">
                        <div class="p-8 border-b border-slate-50 flex flex-col md:flex-row md:items-center justify-between gap-4">
                            <div>
                                <h3 class="text-lg font-black text-slate-800 uppercase tracking-tight">Recent Activity</h3>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] mt-1">Daftar verifikasi terakhir</p>
                            </div>
                            
                            <div class="flex items-center gap-3">
                                <div class="relative">
                                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400" />
                                    <input v-model="historyFilter" type="text" placeholder="Filter hasil..." class="h-9 pl-9 pr-4 rounded-xl border border-slate-100 bg-slate-50 text-[10px] font-bold placeholder:text-slate-400 focus:ring-0 focus:border-[#003628]/20 w-40 transition-all" />
                                </div>
                                <select v-model="statusFilter" class="h-9 px-3 rounded-xl border border-slate-100 bg-slate-50 text-[10px] font-black uppercase tracking-widest text-slate-500 focus:ring-0 outline-none">
                                    <option value="">All Status</option>
                                    <option value="Match">Match</option>
                                    <option value="Mismatch">Mismatch</option>
                                    <option value="Missing">Missing</option>
                                </select>
                            </div>
                        </div>

                        <div class="divide-y divide-slate-50">
                            <div v-for="item in filteredItems" :key="item.id" class="p-6 hover:bg-slate-50/50 transition-all group">
                                <div class="flex items-start gap-5">
                                    <div class="w-12 h-12 rounded-xl bg-slate-50 flex items-center justify-center shrink-0 border border-slate-100 group-hover:bg-white transition-colors">
                                        <Package class="w-6 h-6 text-slate-300" />
                                    </div>
                                    
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between mb-2">
                                            <div class="flex items-center gap-3">
                                                <h4 class="text-sm font-black text-slate-800 uppercase tracking-tight">{{ item.asset_name || 'Unnamed Asset' }}</h4>
                                                <span class="px-2 py-0.5 rounded-lg text-[9px] font-black uppercase tracking-widest border" :class="getStatusBadge(item.status)">{{ item.status }}</span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <div v-if="item.is_synced" class="flex items-center gap-1 text-[9px] font-black text-emerald-500 uppercase tracking-widest bg-emerald-50 px-2 py-0.5 rounded-md">
                                                    <Check class="w-3 h-3" /> Synced
                                                </div>
                                                <button 
                                                    v-else-if="item.status !== 'Missing' && item.physical_location !== item.expected_location"
                                                    @click="syncItemToSnipe(item)"
                                                    class="flex items-center gap-1 text-[9px] font-black text-[#003628] uppercase tracking-widest bg-emerald-50 px-2 py-0.5 rounded-md hover:bg-[#003628] hover:text-white transition-all border border-emerald-100"
                                                >
                                                    <RefreshCw class="w-3 h-3" /> Fix Sync
                                                </button>
                                                <span class="text-[9px] font-bold text-slate-300">#{{ item.asset_tag }}</span>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div class="space-y-1.5">
                                                <div class="flex items-center gap-2 text-[10px] font-bold text-slate-400">
                                                    <MapPin class="w-3 h-3" /> <span class="uppercase tracking-widest">Location:</span> 
                                                    <span class="text-slate-600 font-black">{{ item.physical_location || '-' }}</span>
                                                    <span v-if="item.physical_location !== item.expected_location" class="text-[8px] font-black text-rose-400 italic">(Exp: {{ item.expected_location }})</span>
                                                </div>
                                                <div class="flex items-center gap-2 text-[10px] font-bold text-slate-400">
                                                    <User class="w-3 h-3" /> <span class="uppercase tracking-widest">User:</span> 
                                                    <span class="text-slate-600 font-black">{{ item.physical_user || '-' }}</span>
                                                </div>
                                            </div>
                                            <div v-if="item.note" class="bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                                                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1 flex items-center gap-1">
                                                    <Info class="w-2.5 h-2.5" /> Note
                                                </p>
                                                <p class="text-[10px] font-medium text-slate-500 leading-tight">{{ item.note }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="filteredItems.length === 0" class="p-20 text-center flex flex-col items-center">
                                <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center text-slate-200 mb-4">
                                    <History class="w-8 h-8" />
                                </div>
                                <h4 class="text-base font-black text-slate-800 uppercase tracking-tight">No Items Found</h4>
                                <p class="text-xs text-slate-400 max-w-[200px] mx-auto mt-1">Gunakan filter atau scan aset untuk melihat hasil verifikasi.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.app-page-shell {
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
}
</style>
