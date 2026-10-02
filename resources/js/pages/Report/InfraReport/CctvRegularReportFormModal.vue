<script setup lang="ts">
import { ref, reactive, watch, computed, nextTick } from 'vue';
import axios from 'axios';
import {
    X,
    Save,
    UploadCloud,
    Trash2,
    Plus,
    CheckCircle2,
    AlertTriangle,
    Camera,
    Shield,
    Calendar,
    PenTool,
    Loader2,
    Layers,
    Wrench,
    FileText,
    Image,
    Lock,
    Printer,
} from 'lucide-vue-next';
import SignaturePad from '@/components/SignaturePad.vue';

interface ActionItem {
    camera_id: string;
    action: string;
    due_date: string;
    remarks: string;
}

interface AreaItem {
    id?: number | string;
    name: string;
    camera_qty: number;
    ok_qty: number;
    not_ok_qty: number;
    remarks: string;
    last_record_date: string;
    record_days: number;
    nvr_id: string;
    nvr_remarks: string;
    actions: ActionItem[];
    existingScreenshots: string[];
    newScreenshotFiles: File[];
    newScreenshotPreviews: string[];
}

interface MaintenanceRecordItem {
    camera_id: string;
    action: string;
    act_date: string;
    remarks: string;
    before_photo?: string | null;
    beforeFile?: File | null;
    beforePreview?: string | null;
    after_photo?: string | null;
    afterFile?: File | null;
    afterPreview?: string | null;
}

const props = defineProps<{
    open: boolean;
    reportId?: number | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'saved'): void;
}>();

const getCurrentIsoWeek = () => {
    const d = new Date();
    d.setHours(0, 0, 0, 0);
    d.setDate(d.getDate() + 3 - ((d.getDay() + 6) % 7));
    const week1 = new Date(d.getFullYear(), 0, 4);
    return (
        1 +
        Math.round(
            ((d.getTime() - week1.getTime()) / 86400000 -
                3 +
                ((week1.getDay() + 6) % 7)) /
                7,
        )
    );
};

const activeTab = ref<'info' | 'areas' | 'maintenance' | 'signatures'>('info');
const activeAreaIndex = ref(0);
const isSubmitting = ref(false);
const isCompleting = ref(false);
const isLoadingMeta = ref(false);
const errorMessage = ref<string | null>(null);
const modalBodyRef = ref<HTMLElement | null>(null);

// Form State
const form = reactive({
    status: 'draft',
    location: 'ZGI BGR F1',
    checked_by: '',
    checked_date: new Date().toISOString().split('T')[0],
    week_number: getCurrentIsoWeek(),
    year: new Date().getFullYear(),
    doc_no: '',

    maintenance_last_record_date: '',
    maintenance_nvr_id: '',

    signature_dept1_name: 'IT',
    signature_dept1_signer: '',
    signature_dept1_image: '',

    signature_dept2_name: 'Exim',
    signature_dept2_signer: '',
    signature_dept2_image: '',
});

const isLocked = computed(() => form.status === 'completed');

const openPrint = () => {
    if (props.reportId) {
        window.open(`/infra-report/cctv-regular/${props.reportId}/print`, '_blank');
    }
};

// Dynamic Areas List
const areas = ref<AreaItem[]>([]);

// Dynamic Maintenance Records List
const maintenanceItems = ref<MaintenanceRecordItem[]>([]);

// NVR Options & Location Options
const nvrList = ref<{ id: number; device_name: string }[]>([]);
const locationOptions = ref<string[]>([
    'ZGI BGR F1',
    'ZGI KRW F2',
    'ZDI TGR F3',
]);

const signaturePad1 = ref<any>(null);
const signaturePad2 = ref<any>(null);

watch(activeTab, async (newTab) => {
    if (newTab === 'signatures') {
        await nextTick();
        setTimeout(() => {
            signaturePad1.value?.setupCanvas?.();
            signaturePad2.value?.setupCanvas?.();
        }, 50);
        setTimeout(() => {
            signaturePad1.value?.setupCanvas?.();
            signaturePad2.value?.setupCanvas?.();
        }, 200);
    }
});

// Derive company code: ZDI for PT. ZINUS DREAM INDONESIA, ZGI for PT. ZINUS GLOBAL INDONESIA
const getCompanyCodeFromLocation = (location: string): 'ZGI' | 'ZDI' => {
    if (location && location.trim().toUpperCase().startsWith('ZDI')) {
        return 'ZDI';
    }
    return 'ZGI';
};

// Calculate ISO week number and ISO year accurately from a date string (YYYY-MM-DD)
const getIsoWeekAndYear = (dateStr: string) => {
    if (!dateStr) {
        const now = new Date();
        return { week: getCurrentIsoWeek(), year: now.getFullYear() };
    }
    const target = new Date(dateStr + 'T00:00:00');
    if (isNaN(target.getTime())) {
        const now = new Date();
        return { week: getCurrentIsoWeek(), year: now.getFullYear() };
    }
    const d = new Date(Date.UTC(target.getFullYear(), target.getMonth(), target.getDate()));
    const dayNum = d.getUTCDay() || 7;
    d.setUTCDate(d.getUTCDate() + 4 - dayNum);
    const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
    const weekNo = Math.ceil((((d.getTime() - yearStart.getTime()) / 86400000) + 1) / 7);
    return { week: weekNo, year: d.getUTCFullYear() };
};

// Automatically update Document ID: IR/CCTV/{LOC}/{YEAR}/{WEEK}
const updateDocNoFromYearWeek = (year: number, week: number) => {
    const code = getCompanyCodeFromLocation(form.location);
    if (!form.doc_no) {
        form.doc_no = `IR/CCTV/${code}/${year}/${week}`;
        return;
    }
    const parts = form.doc_no.split('/');
    if (parts.length >= 4) {
        form.doc_no = `IR/CCTV/${code}/${year}/${week}`;
    } else {
        form.doc_no = `IR/CCTV/${code}/${year}/${week}`;
    }
};

// Calculate retention days: (checked_date - last_record_date) in days
const calculateRetentionDays = (
    checkedDate: string,
    lastRecordDate: string,
): number => {
    if (!checkedDate || !lastRecordDate) return 0;
    const c = new Date(checkedDate);
    const r = new Date(lastRecordDate);
    if (isNaN(c.getTime()) || isNaN(r.getTime())) return 0;
    const diff = Math.floor(
        (c.getTime() - r.getTime()) / (1000 * 60 * 60 * 24),
    );
    return Math.max(0, diff);
};

// When checked_date changes: autofill week_number, year, and Document ID ending!
watch(
    () => form.checked_date,
    (newCheckedDate) => {
        if (!newCheckedDate) return;
        const { week, year } = getIsoWeekAndYear(newCheckedDate);
        form.week_number = week;
        form.year = year;
        updateDocNoFromYearWeek(year, week);

        // Recompute retention days for all areas
        areas.value.forEach((area) => {
            if (area.last_record_date) {
                area.record_days = calculateRetentionDays(
                    newCheckedDate,
                    area.last_record_date,
                );
            }
        });
    },
);

// When user manually adjusts year or week number directly, update doc_no
watch(
    () => [form.year, form.week_number],
    ([newYear, newWeek]) => {
        if (newYear && newWeek) {
            updateDocNoFromYearWeek(Number(newYear), Number(newWeek));
        }
    },
);

// When location changes, update doc_no prefix (ZGI or ZDI)
watch(
    () => form.location,
    () => {
        const year = form.year || new Date().getFullYear();
        const week = form.week_number || getCurrentIsoWeek();
        updateDocNoFromYearWeek(Number(year), Number(week));
    },
);

// Area Management
const createNewArea = (
    name = 'Area Baru',
    nvrId = 'NVR F1.1',
    camQty = 16,
): AreaItem => ({
    name,
    camera_qty: camQty,
    ok_qty: camQty,
    not_ok_qty: 0,
    remarks: '',
    last_record_date: '',
    record_days: 0,
    nvr_id: nvrId,
    nvr_remarks: '',
    actions: [],
    existingScreenshots: [],
    newScreenshotFiles: [],
    newScreenshotPreviews: [],
});

const addArea = () => {
    const nextNum = areas.value.length + 1;
    const newArea = createNewArea(
        `Area ${nextNum}`,
        nvrList.value[0]?.device_name || 'NVR F1.1',
        16,
    );
    areas.value.push(newArea);
    activeAreaIndex.value = areas.value.length - 1;
};

const removeArea = (index: number) => {
    if (areas.value.length <= 1) {
        alert('Minimal harus ada 1 area pemeriksaan.');
        return;
    }
    if (confirm(`Hapus area "${areas.value[index].name}"?`)) {
        areas.value.splice(index, 1);
        if (activeAreaIndex.value >= areas.value.length) {
            activeAreaIndex.value = Math.max(0, areas.value.length - 1);
        }
    }
};

const onAreaLastRecordDateChange = (area: AreaItem) => {
    area.record_days = calculateRetentionDays(
        form.checked_date,
        area.last_record_date,
    );
};

const onAreaCamQtyChange = (area: AreaItem) => {
    if (area.ok_qty > area.camera_qty) {
        area.ok_qty = area.camera_qty;
    }
    area.not_ok_qty = Math.max(0, area.camera_qty - area.ok_qty);
};

const onAreaOkQtyChange = (area: AreaItem) => {
    area.not_ok_qty = Math.max(0, area.camera_qty - area.ok_qty);
};

const addAreaAction = (area: AreaItem) => {
    area.actions.push({ camera_id: '', action: '', due_date: '', remarks: '' });
};

const removeAreaAction = (area: AreaItem, actionIdx: number) => {
    area.actions.splice(actionIdx, 1);
};

// Screenshot file for area (single 16-channel live view capture)
const onAreaScreenshotChange = (e: Event, area: AreaItem) => {
    const files = (e.target as HTMLInputElement).files;
    if (!files || !files[0]) return;
    const file = files[0];
    area.newScreenshotFiles = [file];
    area.newScreenshotPreviews = [URL.createObjectURL(file)];
};

const removeAreaScreenshot = (area: AreaItem) => {
    area.existingScreenshots = [];
    area.newScreenshotFiles = [];
    area.newScreenshotPreviews = [];
};

// Aliases for compatibility
const onAreaScreenshotsChange = onAreaScreenshotChange;
const removeExistingScreenshot = (area: AreaItem) => removeAreaScreenshot(area);
const removeNewScreenshot = (area: AreaItem) => removeAreaScreenshot(area);

// Maintenance items management
const addMaintenanceRow = () => {
    maintenanceItems.value.push({
        camera_id: '',
        action: '',
        act_date: form.checked_date || new Date().toISOString().split('T')[0],
        remarks: 'OK',
        beforeFile: null,
        beforePreview: null,
        afterFile: null,
        afterPreview: null,
    });
};

const removeMaintenanceRow = (index: number) => {
    maintenanceItems.value.splice(index, 1);
};

const onMaintenanceBeforePhotoChange = (
    e: Event,
    item: MaintenanceRecordItem,
) => {
    const files = (e.target as HTMLInputElement).files;
    if (!files || !files[0]) return;
    const file = files[0];
    item.beforeFile = file;
    item.beforePreview = URL.createObjectURL(file);
};

const removeMaintenanceBeforePhoto = (item: MaintenanceRecordItem) => {
    item.beforeFile = null;
    item.beforePreview = null;
    item.before_photo = null;
};

const onMaintenanceAfterPhotoChange = (
    e: Event,
    item: MaintenanceRecordItem,
) => {
    const files = (e.target as HTMLInputElement).files;
    if (!files || !files[0]) return;
    const file = files[0];
    item.afterFile = file;
    item.afterPreview = URL.createObjectURL(file);
};

const removeMaintenanceAfterPhoto = (item: MaintenanceRecordItem) => {
    item.afterFile = null;
    item.afterPreview = null;
    item.after_photo = null;
};

// Fetch metadata
const fetchMeta = async () => {
    isLoadingMeta.value = true;
    try {
        const { data } = await axios.get('/infra-report/cctv-regular/meta');
        if (data) {
            nvrList.value = data.nvrs || [];
            if (data.locations) locationOptions.value = data.locations;

            if (!props.reportId) {
                form.year = data.current_year;
                form.week_number = data.current_week;
                form.checked_date = data.default_date;
                form.checked_by = data.current_user;
                form.doc_no = data.default_doc_no;
                form.signature_dept1_signer = data.current_user;
            }
        }
    } catch (e) {
        console.error('Failed to load CCTV regular meta', e);
    } finally {
        isLoadingMeta.value = false;
    }
};

// Reset form
const resetForm = () => {
    form.status = 'draft';
    activeAreaIndex.value = 0;
    // Defaults: 2 areas (Loading Area & Beacukai CCTV)
    areas.value = [
        {
            name: 'Loading Area',
            camera_qty: 10,
            ok_qty: 10,
            not_ok_qty: 0,
            remarks: '',
            last_record_date: '',
            record_days: 0,
            nvr_id: 'NVR F1.5',
            nvr_remarks: '',
            actions: [],
            existingScreenshots: [],
            newScreenshotFiles: [],
            newScreenshotPreviews: [],
        },
        {
            name: 'Beacukai CCTV',
            camera_qty: 16,
            ok_qty: 16,
            not_ok_qty: 0,
            remarks: '',
            last_record_date: '',
            record_days: 0,
            nvr_id: 'NVR F1.1',
            nvr_remarks: '',
            actions: [],
            existingScreenshots: [],
            newScreenshotFiles: [],
            newScreenshotPreviews: [],
        },
    ];

    maintenanceItems.value = [];
};

// Fetch single report for edit
const fetchReport = async (id: number) => {
    try {
        const { data } = await axios.get(`/infra-report/cctv-regular/${id}`);
        const r = data.report;
        if (r) {
            form.status = r.status || 'draft';
            form.location = r.location;
            form.checked_by = r.checked_by;
            form.checked_date = r.checked_date;
            form.week_number = r.week_number;
            form.year = r.year;
            form.doc_no = r.doc_no;

            // Resolved Areas
            const rawAreas = r.resolved_areas || [];
            if (rawAreas.length > 0) {
                areas.value = rawAreas.map((ra: any, idx: number) => {
                    const cQty = ra.camera_qty ?? ra.total_cam ?? 16;
                    const okQ = ra.ok_qty ?? ra.normal_cam ?? cQty;
                    const notOkQ =
                        ra.not_ok_qty ??
                        ra.problem_cam ??
                        Math.max(0, cQty - okQ);
                    const lDate = ra.last_record_date || '';
                    const rDays =
                        ra.record_days ??
                        ra.retention_days ??
                        calculateRetentionDays(r.checked_date, lDate);

                    return {
                        name: ra.name || `Area ${idx + 1}`,
                        camera_qty: cQty,
                        ok_qty: okQ,
                        not_ok_qty: notOkQ,
                        remarks: ra.remarks || '',
                        last_record_date: lDate,
                        record_days: rDays,
                        nvr_id: ra.nvr_id || ra.nvr || 'NVR F1.1',
                        nvr_remarks: ra.nvr_remarks || '',
                        actions: ra.action_needed || [],
                        existingScreenshots: ra.screenshots || [],
                        newScreenshotFiles: [],
                        newScreenshotPreviews: [],
                    };
                });
            } else {
                resetForm();
            }

            // Maintenance
            const m = r.maintenance_data || {};
            form.maintenance_last_record_date = m.last_record_date || '';
            form.maintenance_nvr_id = m.nvr_id || '';

            if (m.items && Array.isArray(m.items) && m.items.length > 0) {
                maintenanceItems.value = m.items.map((it: any) => ({
                    camera_id: it.camera_id || '',
                    action: it.action || '',
                    act_date: it.act_date || '',
                    remarks: it.remarks || 'OK',
                    before_photo: it.before_photo || null,
                    beforeFile: null,
                    beforePreview: it.before_photo || null,
                    after_photo: it.after_photo || null,
                    afterFile: null,
                    afterPreview: it.after_photo || null,
                }));
            } else if (m.before_photo || m.after_photo) {
                // Legacy top-level fallback only if photos actually exist
                maintenanceItems.value = [
                    {
                        camera_id: 'All',
                        action: 'Pembersihan fisik & pengecekan berkala',
                        act_date: r.checked_date || '',
                        remarks: 'OK',
                        before_photo: m.before_photo || null,
                        beforeFile: null,
                        beforePreview: m.before_photo || null,
                        after_photo: m.after_photo || null,
                        afterFile: null,
                        afterPreview: m.after_photo || null,
                    },
                ];
            } else {
                maintenanceItems.value = [];
            }

            // Signatures
            form.signature_dept1_name = r.signature_dept1_name || 'IT';
            form.signature_dept1_signer =
                r.signature_dept1_signer || r.checked_by;
            form.signature_dept1_image = r.signature_dept1_image || '';

            form.signature_dept2_name = r.signature_dept2_name || 'Exim';
            form.signature_dept2_signer = r.signature_dept2_signer || '';
            form.signature_dept2_image = r.signature_dept2_image || '';
        }
    } catch (e) {
        console.error('Failed to load report detail', e);
    }
};

watch(
    () => props.open,
    (newVal) => {
        if (newVal) {
            activeTab.value = 'info';
            errorMessage.value = null;
            fetchMeta();
            if (props.reportId) {
                fetchReport(props.reportId);
            } else {
                resetForm();
            }
        }
    },
);

// Signature pad captures
const saveDept1Signature = () => {
    try {
        if (
            signaturePad1.value &&
            typeof signaturePad1.value.isEmpty === 'function' &&
            !signaturePad1.value.isEmpty()
        ) {
            if (typeof signaturePad1.value.saveSignature === 'function') {
                const sig = signaturePad1.value.saveSignature();
                if (sig) form.signature_dept1_image = sig;
            } else if (typeof signaturePad1.value.toDataURL === 'function') {
                const sig = signaturePad1.value.toDataURL();
                if (sig) form.signature_dept1_image = sig;
            } else if (typeof signaturePad1.value.getSignature === 'function') {
                const sig = signaturePad1.value.getSignature();
                if (sig) form.signature_dept1_image = sig;
            }
        }
    } catch (e) {
        console.warn('Could not save dept 1 signature', e);
    }
};

const clearDept1Signature = () => {
    try {
        if (signaturePad1.value) {
            if (typeof signaturePad1.value.clear === 'function')
                signaturePad1.value.clear();
            else if (typeof signaturePad1.value.clearSignature === 'function')
                signaturePad1.value.clearSignature();
        }
    } catch (e) {
        console.warn('Could not clear dept 1 signature', e);
    }
    form.signature_dept1_image = '';
};

const saveDept2Signature = () => {
    try {
        if (
            signaturePad2.value &&
            typeof signaturePad2.value.isEmpty === 'function' &&
            !signaturePad2.value.isEmpty()
        ) {
            if (typeof signaturePad2.value.saveSignature === 'function') {
                const sig = signaturePad2.value.saveSignature();
                if (sig) form.signature_dept2_image = sig;
            } else if (typeof signaturePad2.value.toDataURL === 'function') {
                const sig = signaturePad2.value.toDataURL();
                if (sig) form.signature_dept2_image = sig;
            } else if (typeof signaturePad2.value.getSignature === 'function') {
                const sig = signaturePad2.value.getSignature();
                if (sig) form.signature_dept2_image = sig;
            }
        }
    } catch (e) {
        console.warn('Could not save dept 2 signature', e);
    }
};

const clearDept2Signature = () => {
    try {
        if (signaturePad2.value) {
            if (typeof signaturePad2.value.clear === 'function')
                signaturePad2.value.clear();
            else if (typeof signaturePad2.value.clearSignature === 'function')
                signaturePad2.value.clearSignature();
        }
    } catch (e) {
        console.warn('Could not clear dept 2 signature', e);
    }
    form.signature_dept2_image = '';
};

// Save form data helper
const saveFormData = async () => {
    // Fallback checked_by if empty
    if (!form.checked_by && form.signature_dept1_signer) {
        form.checked_by = form.signature_dept1_signer;
    }

    if (!form.location || !form.checked_by || !form.checked_date) {
        errorMessage.value =
            'Mohon lengkapi data umum: Lokasi, Tanggal Pengecekan, dan Nama Pemeriksa.';
        activeTab.value = 'info';
        modalBodyRef.value?.scrollTo({ top: 0, behavior: 'smooth' });
        throw new Error(errorMessage.value);
    }

    if (!areas.value || areas.value.length === 0) {
        errorMessage.value = 'Minimal harus memiliki 1 area pemeriksaan.';
        activeTab.value = 'areas';
        throw new Error(errorMessage.value);
    }

    saveDept1Signature();
    saveDept2Signature();

    const formData = new FormData();
    formData.append('location', form.location);
    formData.append('checked_by', form.checked_by);
    formData.append('checked_date', form.checked_date);
    formData.append('week_number', String(form.week_number || 1));
    formData.append('year', String(form.year || new Date().getFullYear()));
    if (form.doc_no) {
        formData.append('doc_no', form.doc_no);
    }

    // Areas payload
    const areasPayload = areas.value.map((area, idx) => {
        // Append new screenshots for this area
        if (
            area.newScreenshotFiles &&
            Array.isArray(area.newScreenshotFiles)
        ) {
            area.newScreenshotFiles.forEach((file) => {
                formData.append(`area_${idx}_screenshots[]`, file);
            });
        }

        return {
            name: area.name || `Area ${idx + 1}`,
            camera_qty: Number(area.camera_qty) || 0,
            ok_qty: Number(area.ok_qty) || 0,
            not_ok_qty: Number(area.not_ok_qty) || 0,
            remarks: area.remarks || '',
            last_record_date: area.last_record_date || '',
            record_days: Number(area.record_days) || 0,
            retention_days: Number(area.record_days) || 0,
            nvr_id: area.nvr_id || '',
            nvr_remarks: area.nvr_remarks || '',
            action_needed: area.actions || [],
            screenshots: area.existingScreenshots || [],
        };
    });
    formData.append('areas_data', JSON.stringify(areasPayload));

    // Maintenance payload
    const mItemsPayload = maintenanceItems.value.map((item, mIdx) => {
        if (item.beforeFile) {
            formData.append(
                `maintenance_${mIdx}_before_photo`,
                item.beforeFile,
            );
        }
        if (item.afterFile) {
            formData.append(
                `maintenance_${mIdx}_after_photo`,
                item.afterFile,
            );
        }
        return {
            camera_id: item.camera_id || 'All',
            action: item.action || '',
            act_date: item.act_date || form.checked_date,
            remarks: item.remarks || 'OK',
            before_photo: item.before_photo || null,
            after_photo: item.after_photo || null,
        };
    });

    const maintenanceData = {
        last_record_date: form.maintenance_last_record_date || '',
        nvr_id: form.maintenance_nvr_id || '',
        items: mItemsPayload,
    };
    formData.append('maintenance_data', JSON.stringify(maintenanceData));

    // Signatures
    formData.append(
        'signature_dept1_name',
        form.signature_dept1_name || 'IT',
    );
    formData.append(
        'signature_dept1_signer',
        form.signature_dept1_signer || form.checked_by,
    );
    if (form.signature_dept1_image)
        formData.append(
            'signature_dept1_image',
            form.signature_dept1_image,
        );

    formData.append(
        'signature_dept2_name',
        form.signature_dept2_name || 'Exim',
    );
    formData.append(
        'signature_dept2_signer',
        form.signature_dept2_signer || '',
    );
    if (form.signature_dept2_image)
        formData.append(
            'signature_dept2_image',
            form.signature_dept2_image,
        );

    if (props.reportId) {
        formData.append('_method', 'PUT');
        const res = await axios.post(
            `/infra-report/cctv-regular/${props.reportId}`,
            formData,
        );
        return res.data;
    } else {
        const res = await axios.post('/infra-report/cctv-regular', formData);
        return res.data;
    }
};

// Submit form
const handleSubmit = async () => {
    errorMessage.value = null;
    isSubmitting.value = true;

    try {
        await saveFormData();
        emit('saved');
        emit('update:open', false);
    } catch (err: any) {
        console.error('Submit error:', err);
        const serverMsg = err.response?.data?.message || err.message;
        const errDetails = err.response?.data?.errors
            ? Object.values(err.response.data.errors).flat().join(' ')
            : '';
        errorMessage.value = errDetails
            ? `${serverMsg}: ${errDetails}`
            : serverMsg ||
              'Gagal menyimpan laporan. Mohon periksa kembali inputan Anda.';
    } finally {
        isSubmitting.value = false;
    }
};

// Complete & Lock report
const handleCompleteReport = async () => {
    if (!props.reportId) return;

    const confirmed = window.confirm(
        'Apakah Anda yakin ingin menyelesaikan dan mengunci laporan ini?\n\n' +
        '⚠️ PERHATIAN:\n' +
        'Setelah berstatus Completed, data laporan akan TERKUNCI PERMANEN dan tidak dapat diedit atau dihapus kembali.\n\n' +
        'Laporan hanya dapat dilihat, dicetak, atau disimpan dalam bentuk PDF.'
    );
    if (!confirmed) return;

    isCompleting.value = true;
    errorMessage.value = null;

    try {
        // Save current changes first if not yet locked
        if (!isLocked.value) {
            await saveFormData();
        }

        // Call complete endpoint
        await axios.post(`/infra-report/cctv-regular/${props.reportId}/complete`);
        form.status = 'completed';
        emit('saved');
        alert('Laporan berhasil diselesaikan dan status saat ini TERKUNCI (Completed). Data tidak dapat diubah kembali.');
    } catch (err: any) {
        console.error('Complete error:', err);
        const serverMsg = err.response?.data?.message || err.message;
        errorMessage.value = serverMsg || 'Gagal menyelesaikan dan mengunci laporan.';
    } finally {
        isCompleting.value = false;
    }
};

const closeModal = () => {
    emit('update:open', false);
};
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/60 p-4 backdrop-blur-sm"
    >
        <div
            class="relative my-auto flex max-h-[92vh] w-full max-w-5xl animate-in flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-2xl duration-150 zoom-in-95 fade-in dark:border-zinc-800 dark:bg-zinc-900"
        >
            <!-- Modal Header -->
            <div
                class="flex items-center justify-between border-b border-zinc-200 bg-zinc-50 px-6 py-4 dark:border-zinc-800 dark:bg-zinc-900/50"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="rounded-xl border border-emerald-200 bg-emerald-100 p-2 text-emerald-700 dark:border-emerald-800/60 dark:bg-emerald-950/50 dark:text-emerald-400"
                    >
                        <Camera class="h-5 w-5" />
                    </div>
                    <div>
                        <h2
                            class="flex flex-wrap items-center gap-2 text-lg font-bold text-zinc-900 dark:text-zinc-100"
                        >
                            {{
                                isLocked
                                    ? 'Detail Laporan CCTV Reguler'
                                    : reportId
                                      ? 'Edit Form CCTV Reguler Check'
                                      : 'Form CCTV Reguler Check Baru'
                            }}
                            <span
                                v-if="form.doc_no"
                                class="rounded-full bg-zinc-200 px-2.5 py-0.5 font-mono text-xs font-semibold text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300"
                            >
                                {{ form.doc_no }}
                            </span>
                            <span
                                v-if="isLocked"
                                class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 border border-emerald-300 px-2.5 py-0.5 text-xs font-bold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 dark:border-emerald-800"
                            >
                                <Lock class="h-3 w-3 text-emerald-700 dark:text-emerald-400" />
                                Completed &amp; Terkunci
                            </span>
                            <span
                                v-else-if="reportId"
                                class="inline-flex items-center gap-1 rounded-full bg-amber-100 border border-amber-300 px-2.5 py-0.5 text-xs font-bold text-amber-800 dark:bg-amber-950 dark:text-amber-300 dark:border-amber-800"
                            >
                                Draft
                            </span>
                        </h2>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                            Digitalisasi Form Pengecekan CCTV Mingguan dengan
                            Area Dinamis & Dokumentasi Pemeliharaan
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    @click="closeModal"
                    class="rounded-lg p-2 text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-200 cursor-pointer"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>

            <!-- Tab Navigation -->
            <div
                class="flex overflow-x-auto border-b border-zinc-200 bg-white px-6 text-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <button
                    type="button"
                    @click="activeTab = 'info'"
                    :class="[
                        'flex items-center gap-2 border-b-2 px-4 py-3 font-semibold whitespace-nowrap transition cursor-pointer',
                        activeTab === 'info'
                            ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400'
                            : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300',
                    ]"
                >
                    <FileText class="h-4 w-4" />
                    1. Info Umum
                </button>

                <button
                    type="button"
                    @click="activeTab = 'areas'"
                    :class="[
                        'flex items-center gap-2 border-b-2 px-4 py-3 font-semibold whitespace-nowrap transition cursor-pointer',
                        activeTab === 'areas'
                            ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400'
                            : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300',
                    ]"
                >
                    <Layers class="h-4 w-4" />
                    2. Area Pemeriksaan ({{ areas.length }})
                </button>

                <button
                    type="button"
                    @click="activeTab = 'maintenance'"
                    :class="[
                        'flex items-center gap-2 border-b-2 px-4 py-3 font-semibold whitespace-nowrap transition cursor-pointer',
                        activeTab === 'maintenance'
                            ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400'
                            : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300',
                    ]"
                >
                    <Wrench class="h-4 w-4" />
                    3. Catatan & Foto Maintenance ({{
                        maintenanceItems.length
                    }})
                </button>

                <button
                    type="button"
                    @click="activeTab = 'signatures'"
                    :class="[
                        'flex items-center gap-2 border-b-2 px-4 py-3 font-semibold whitespace-nowrap transition cursor-pointer',
                        activeTab === 'signatures'
                            ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400'
                            : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300',
                    ]"
                >
                    <PenTool class="h-4 w-4" />
                    4. Tanda Tangan
                </button>
            </div>

            <!-- Locked Notice Banner -->
            <div
                v-if="isLocked"
                class="mx-6 mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50/90 p-3 text-xs text-emerald-950 shadow-xs dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200"
            >
                <div class="flex items-center gap-2">
                    <CheckCircle2 class="h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                    <span>
                        <strong>Laporan Terverifikasi & Selesai (Terkunci):</strong> Data telah didokumentasikan secara permanen. Hanya bisa dilihat, dicetak, atau disimpan PDF.
                    </span>
                </div>
                <button
                    type="button"
                    @click="openPrint"
                    class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-[#003628] px-3 py-1.5 text-xs font-bold text-white shadow-xs transition hover:bg-[#004d39] active:scale-95 cursor-pointer"
                >
                    <Printer class="h-3.5 w-3.5" />
                    Cetak / Simpan PDF
                </button>
            </div>

            <!-- Error Banner -->
            <div
                v-if="errorMessage"
                class="mx-6 mt-4 flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 p-3 text-xs text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"
            >
                <AlertTriangle class="h-4 w-4 shrink-0" />
                <span>{{ errorMessage }}</span>
            </div>

            <!-- Modal Body (Tab Contents) -->
            <div
                ref="modalBodyRef"
                class="flex-1 space-y-6 overflow-y-auto p-6"
            >
                <!-- ================= TAB 1: INFORMASI UMUM ================= -->
                <div v-show="activeTab === 'info'" class="space-y-6">
                    <div
                        class="grid grid-cols-1 gap-5 rounded-2xl border border-zinc-200 bg-zinc-50 p-5 md:grid-cols-3 dark:border-zinc-800 dark:bg-zinc-800/40"
                    >
                        <div>
                            <label
                                class="mb-1.5 block text-xs font-semibold text-zinc-700 dark:text-zinc-300"
                            >
                                Lokasi / Site
                                <span class="text-red-500">*</span>
                            </label>
                            <select
                                v-model="form.location"
                                :disabled="isLocked"
                                class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none disabled:bg-zinc-100 disabled:cursor-not-allowed dark:border-zinc-700 dark:bg-zinc-900 dark:disabled:bg-zinc-800"
                            >
                                <option
                                    v-for="loc in locationOptions"
                                    :key="loc"
                                    :value="loc"
                                >
                                    {{ loc }}
                                </option>
                            </select>
                            <p class="mt-1 text-[10px] font-semibold text-emerald-600 dark:text-emerald-400">
                                {{ form.location.toUpperCase().startsWith('ZDI') ? 'PT. ZINUS DREAM INDONESIA' : 'PT. ZINUS GLOBAL INDONESIA' }}
                            </p>
                        </div>

                        <div>
                            <label
                                class="mb-1.5 block text-xs font-semibold text-zinc-700 dark:text-zinc-300"
                            >
                                Tanggal Pengecekan
                                <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="date"
                                v-model="form.checked_date"
                                :disabled="isLocked"
                                class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none disabled:bg-zinc-100 disabled:cursor-not-allowed dark:border-zinc-700 dark:bg-zinc-900 dark:disabled:bg-zinc-800"
                            />
                            <p class="mt-1 text-[10px] text-zinc-500">
                                Mengubah tanggal ini otomatis memperbarui
                                Retention Days area
                            </p>
                        </div>

                        <div>
                            <label
                                class="mb-1.5 block text-xs font-semibold text-zinc-700 dark:text-zinc-300"
                            >
                                Diperiksa Oleh (Checked By)
                                <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                v-model="form.checked_by"
                                :disabled="isLocked"
                                placeholder="Nama Petugas IT"
                                class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none disabled:bg-zinc-100 disabled:cursor-not-allowed dark:border-zinc-700 dark:bg-zinc-900 dark:disabled:bg-zinc-800"
                            />
                        </div>

                        <div>
                            <label
                                class="mb-1.5 block text-xs font-semibold text-zinc-700 dark:text-zinc-300"
                            >
                                Minggu Ke- (Week)
                            </label>
                            <input
                                type="number"
                                min="1"
                                max="53"
                                v-model.number="form.week_number"
                                :disabled="isLocked"
                                class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none disabled:bg-zinc-100 disabled:cursor-not-allowed dark:border-zinc-700 dark:bg-zinc-900 dark:disabled:bg-zinc-800"
                            />
                        </div>

                        <div>
                            <label
                                class="mb-1.5 block text-xs font-semibold text-zinc-700 dark:text-zinc-300"
                            >
                                Tahun (Year)
                            </label>
                            <input
                                type="number"
                                min="2020"
                                max="2099"
                                v-model.number="form.year"
                                :disabled="isLocked"
                                class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none disabled:bg-zinc-100 disabled:cursor-not-allowed dark:border-zinc-700 dark:bg-zinc-900 dark:disabled:bg-zinc-800"
                            />
                        </div>

                        <div>
                            <label
                                class="mb-1.5 block text-xs font-semibold text-zinc-700 dark:text-zinc-300"
                            >
                                Document ID
                            </label>
                            <input
                                type="text"
                                v-model="form.doc_no"
                                :disabled="isLocked"
                                placeholder="Auto-generated jika kosong"
                                class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 font-mono text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none disabled:bg-zinc-100 disabled:cursor-not-allowed dark:border-zinc-700 dark:bg-zinc-900 dark:disabled:bg-zinc-800"
                            />
                        </div>
                    </div>
                </div>

                <!-- ================= TAB 2: AREA PEMERIKSAAN (DYNAMIC) ================= -->
                <div v-show="activeTab === 'areas'" class="space-y-6">
                    <!-- Area Sub-Navigation Tabs & Add Button -->
                    <div
                        class="flex flex-wrap items-center justify-between gap-2 border-b border-zinc-200 pb-3 dark:border-zinc-800"
                    >
                        <div class="flex items-center gap-1.5 overflow-x-auto">
                            <button
                                v-for="(area, aIdx) in areas"
                                :key="aIdx"
                                type="button"
                                @click="activeAreaIndex = aIdx"
                                :class="[
                                    'flex items-center gap-2 rounded-xl border px-3.5 py-1.5 text-xs font-bold transition',
                                    activeAreaIndex === aIdx
                                        ? 'border-emerald-600 bg-emerald-600 text-white shadow-sm'
                                        : 'border-zinc-200 bg-zinc-100 text-zinc-700 hover:bg-zinc-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700',
                                ]"
                            >
                                <span>{{
                                    area.name || `Area ${aIdx + 1}`
                                }}</span>
                                <span
                                    class="py-0.2 rounded-full bg-black/20 px-1.5 text-[10px] font-normal"
                                >
                                    {{ area.camera_qty }} Cam
                                </span>
                            </button>
                        </div>

                        <button
                            v-if="!isLocked"
                            type="button"
                            @click="addArea"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-zinc-900 px-3 py-1.5 text-xs font-bold text-white shadow-sm transition hover:bg-zinc-800 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white cursor-pointer"
                        >
                            <Plus class="h-3.5 w-3.5" />
                            Tambah Area Baru
                        </button>
                    </div>

                    <!-- Selected Area Form Card -->
                    <div
                        v-if="areas[activeAreaIndex]"
                        class="space-y-6 rounded-2xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-800 dark:bg-zinc-900/40"
                    >
                        <!-- Area Title & Remove Button -->
                        <div
                            class="flex items-center justify-between border-b border-zinc-200 pb-3 dark:border-zinc-800"
                        >
                            <div class="max-w-md flex-1">
                                <label
                                    class="mb-1 block text-xs font-bold text-zinc-700 dark:text-zinc-300"
                                >
                                    Nama Area Pemeriksaan
                                </label>
                                <input
                                    type="text"
                                    v-model="areas[activeAreaIndex].name"
                                    :disabled="isLocked"
                                    placeholder="Contoh: Loading Area, Beacukai CCTV, Area Produksi..."
                                    class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm font-semibold focus:ring-2 focus:ring-emerald-500 focus:outline-none disabled:bg-zinc-100 disabled:cursor-not-allowed dark:border-zinc-700 dark:bg-zinc-900 dark:disabled:bg-zinc-800"
                                />
                            </div>

                            <button
                                v-if="!isLocked && areas.length > 1"
                                type="button"
                                @click="removeArea(activeAreaIndex)"
                                class="inline-flex items-center gap-1 rounded-xl p-2 text-xs text-red-600 transition hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-950/30 cursor-pointer"
                                title="Hapus area ini"
                            >
                                <Trash2 class="h-4 w-4" />
                                Hapus Area
                            </button>
                        </div>

                        <!-- Stats & NVR Mapping Grid -->
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                            <div>
                                <label
                                    class="mb-1 block text-xs font-semibold text-zinc-700 dark:text-zinc-300"
                                >
                                    NVR ID / Device
                                </label>
                                <input
                                    type="text"
                                    v-model="areas[activeAreaIndex].nvr_id"
                                    :disabled="isLocked"
                                    list="nvr-datalist"
                                    placeholder="e.g. NVR F1.1, NVR F1.5"
                                    class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none disabled:bg-zinc-100 disabled:cursor-not-allowed dark:border-zinc-700 dark:bg-zinc-900 dark:disabled:bg-zinc-800"
                                />
                                <datalist id="nvr-datalist">
                                    <option
                                        v-for="nvr in nvrList"
                                        :key="nvr.id"
                                        :value="nvr.device_name"
                                    />
                                    <option value="NVR F1.1" />
                                    <option value="NVR F1.5" />
                                    <option value="NVR F2.1" />
                                    <option value="NVR F3.1" />
                                </datalist>
                            </div>

                            <div>
                                <label
                                    class="mb-1 block text-xs font-semibold text-zinc-700 dark:text-zinc-300"
                                >
                                    Jumlah Kamera (Total)
                                </label>
                                <input
                                    type="number"
                                    min="1"
                                    max="64"
                                    v-model.number="
                                        areas[activeAreaIndex].camera_qty
                                    "
                                    :disabled="isLocked"
                                    @input="
                                        onAreaCamQtyChange(
                                            areas[activeAreaIndex],
                                        )
                                    "
                                    class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none disabled:bg-zinc-100 disabled:cursor-not-allowed dark:border-zinc-700 dark:bg-zinc-900 dark:disabled:bg-zinc-800"
                                />
                            </div>

                            <div>
                                <label
                                    class="mb-1 block text-xs font-semibold text-zinc-700 dark:text-zinc-300"
                                >
                                    Kondisi OK (Normal)
                                </label>
                                <input
                                    type="number"
                                    min="0"
                                    :max="areas[activeAreaIndex].camera_qty"
                                    v-model.number="
                                        areas[activeAreaIndex].ok_qty
                                    "
                                    :disabled="isLocked"
                                    @input="
                                        onAreaOkQtyChange(
                                            areas[activeAreaIndex],
                                        )
                                    "
                                    class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm font-bold text-emerald-600 focus:ring-2 focus:ring-emerald-500 focus:outline-none disabled:bg-zinc-100 disabled:cursor-not-allowed dark:border-zinc-700 dark:bg-zinc-900 dark:text-emerald-400 dark:disabled:bg-zinc-800"
                                />
                            </div>

                            <div>
                                <label
                                    class="mb-1 block text-xs font-semibold text-zinc-700 dark:text-zinc-300"
                                >
                                    Kondisi Not OK (Problem)
                                </label>
                                <input
                                    type="number"
                                    min="0"
                                    :max="areas[activeAreaIndex].camera_qty"
                                    v-model.number="
                                        areas[activeAreaIndex].not_ok_qty
                                    "
                                    :disabled="isLocked"
                                    class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm font-bold text-red-600 focus:ring-2 focus:ring-emerald-500 focus:outline-none disabled:bg-zinc-100 disabled:cursor-not-allowed dark:border-zinc-700 dark:bg-zinc-900 dark:text-red-400 dark:disabled:bg-zinc-800"
                                />
                            </div>
                        </div>

                        <!-- Retention Days & Last Record Date (AUTO CALCULATED) -->
                        <div
                            class="grid grid-cols-1 gap-4 rounded-xl border border-amber-200/60 bg-amber-50/60 p-4 md:grid-cols-3 dark:border-amber-900/40 dark:bg-amber-950/20"
                        >
                            <div>
                                <label
                                    class="mb-1 block text-xs font-bold text-amber-900 dark:text-amber-300"
                                >
                                    Last Record Date (Tanggal Rekaman Tertua)
                                </label>
                                <input
                                    type="date"
                                    v-model="
                                        areas[activeAreaIndex].last_record_date
                                    "
                                    :disabled="isLocked"
                                    @change="
                                        onAreaLastRecordDateChange(
                                            areas[activeAreaIndex],
                                        )
                                    "
                                    class="w-full rounded-xl border border-amber-300 bg-white px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none disabled:bg-zinc-100 disabled:cursor-not-allowed dark:border-amber-800 dark:bg-zinc-900 dark:disabled:bg-zinc-800"
                                />
                            </div>

                            <div>
                                <label
                                    class="mb-1 block text-xs font-bold text-amber-900 dark:text-amber-300"
                                >
                                    Retention Days (Otomatis)
                                </label>
                                <div class="flex items-center gap-2">
                                    <input
                                        type="number"
                                        v-model.number="
                                            areas[activeAreaIndex].record_days
                                        "
                                        :disabled="isLocked"
                                        class="w-full rounded-xl border border-amber-300 bg-white px-3 py-2 text-sm font-bold text-amber-900 focus:ring-2 focus:ring-amber-500 focus:outline-none disabled:bg-zinc-100 disabled:cursor-not-allowed dark:border-amber-800 dark:bg-zinc-900 dark:text-amber-200 dark:disabled:bg-zinc-800"
                                    />
                                    <span
                                        class="text-xs font-bold whitespace-nowrap text-amber-800 dark:text-amber-300"
                                        >Hari</span
                                    >
                                </div>
                                <p
                                    class="mt-1 text-[10px] text-amber-700 dark:text-amber-400"
                                >
                                    Dihitung: Checked Date ({{
                                        form.checked_date || '-'
                                    }}) - Last Record Date
                                </p>
                            </div>

                            <div>
                                <label
                                    class="mb-1 block text-xs font-bold text-zinc-700 dark:text-zinc-300"
                                >
                                    Catatan Tambahan NVR
                                </label>
                                <input
                                    type="text"
                                    v-model="areas[activeAreaIndex].nvr_remarks"
                                    :disabled="isLocked"
                                    placeholder="Contoh: Normal, Penyimpanan 4TB"
                                    class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none disabled:bg-zinc-100 disabled:cursor-not-allowed dark:border-zinc-700 dark:bg-zinc-900 dark:disabled:bg-zinc-800"
                                />
                            </div>
                        </div>

                        <!-- Single Live View 16 Ch Capture for Current Area -->
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <label
                                    class="flex items-center gap-2 text-xs font-bold text-zinc-900 dark:text-zinc-100"
                                >
                                    <Image class="h-4 w-4 text-emerald-600" />
                                    Tangkapan Layar Live View (16 Channel) -
                                    {{ areas[activeAreaIndex].name }}
                                </label>
                                <div v-if="!isLocked" class="flex items-center gap-2">
                                    <button
                                        v-if="areas[activeAreaIndex].newScreenshotPreviews[0] || areas[activeAreaIndex].existingScreenshots[0]"
                                        type="button"
                                        @click="removeAreaScreenshot(areas[activeAreaIndex])"
                                        class="text-xs font-semibold text-red-600 hover:text-red-700 hover:underline p-1 cursor-pointer"
                                    >
                                        Hapus Foto
                                    </button>
                                    <label
                                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-400"
                                    >
                                        <UploadCloud class="h-3.5 w-3.5" />
                                        {{ (areas[activeAreaIndex].newScreenshotPreviews[0] || areas[activeAreaIndex].existingScreenshots[0]) ? 'Ganti Foto Live View' : 'Unggah Foto Live View' }}
                                        <input
                                            type="file"
                                            accept="image/*"
                                            class="hidden"
                                            @change="
                                                (e) =>
                                                    onAreaScreenshotChange(
                                                        e,
                                                        areas[activeAreaIndex],
                                                    )
                                                "
                                        />
                                    </label>
                                </div>
                            </div>

                            <!-- Single Live View Preview Box -->
                            <div class="w-full">
                                <div
                                    v-if="areas[activeAreaIndex].newScreenshotPreviews[0] || areas[activeAreaIndex].existingScreenshots[0]"
                                    class="relative aspect-video max-h-[380px] w-full overflow-hidden rounded-xl border-2 border-emerald-500/80 bg-black flex items-center justify-center shadow-inner"
                                >
                                    <img
                                        :src="areas[activeAreaIndex].newScreenshotPreviews[0] || areas[activeAreaIndex].existingScreenshots[0]"
                                        class="h-full w-full object-contain"
                                        alt="Live View 16 Channel"
                                    />
                                    <span
                                        v-if="areas[activeAreaIndex].newScreenshotPreviews[0]"
                                        class="absolute bottom-2 left-2 rounded bg-emerald-600 px-2 py-0.5 text-[10px] font-bold text-white shadow"
                                    >
                                        Foto Baru Terpilih
                                    </span>
                                </div>

                                <div
                                    v-else-if="isLocked"
                                    class="flex flex-col items-center justify-center aspect-video max-h-[200px] w-full rounded-xl border border-zinc-200 bg-zinc-50 p-6 text-center text-zinc-400 dark:border-zinc-800 dark:bg-zinc-800/30"
                                >
                                    <Image class="h-8 w-8 mb-2 opacity-40 text-zinc-400" />
                                    <span class="text-xs">Tidak ada tangkapan layar untuk area ini</span>
                                </div>

                                <label
                                    v-else
                                    class="flex flex-col items-center justify-center aspect-video max-h-[260px] w-full cursor-pointer rounded-xl border-2 border-dashed border-zinc-300 bg-zinc-50/50 p-6 text-center transition hover:border-emerald-400 hover:bg-emerald-50/20 dark:border-zinc-700 dark:bg-zinc-800/30"
                                >
                                    <UploadCloud class="h-8 w-8 text-zinc-400 mb-2" />
                                    <span class="text-xs font-bold text-zinc-700 dark:text-zinc-200">
                                        Klik untuk unggah screenshot Live View (16 Channel)
                                    </span>
                                    <span class="text-[11px] text-zinc-400 mt-1">
                                        Cukup 1 tangkapan layar monitor NVR yang sudah menampilkan seluruh channel
                                    </span>
                                    <input
                                        type="file"
                                        accept="image/*"
                                        class="hidden"
                                        @change="
                                            (e) =>
                                                onAreaScreenshotChange(
                                                    e,
                                                    areas[activeAreaIndex],
                                                )
                                        "
                                    />
                                </label>
                            </div>
                        </div>

                        <!-- Action Needed Table for Current Area -->
                        <div
                            class="space-y-2 border-t border-zinc-200 pt-2 dark:border-zinc-800"
                        >
                            <div class="flex items-center justify-between">
                                <label
                                    class="text-xs font-bold text-zinc-900 dark:text-zinc-100"
                                >
                                    Action Needed / Tindakan Perbaikan ({{
                                        areas[activeAreaIndex].name
                                    }})
                                </label>
                                <button
                                    v-if="!isLocked"
                                    type="button"
                                    @click="
                                        addAreaAction(areas[activeAreaIndex])
                                    "
                                    class="inline-flex items-center gap-1 text-xs font-bold text-emerald-600 hover:underline dark:text-emerald-400 cursor-pointer"
                                >
                                    <Plus class="h-3.5 w-3.5" />
                                    Tambah Action
                                </button>
                            </div>

                            <table
                                v-if="areas[activeAreaIndex].actions.length > 0"
                                class="w-full overflow-hidden rounded-xl border border-zinc-200 text-left text-xs dark:border-zinc-800"
                            >
                                <thead
                                    class="bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300"
                                >
                                    <tr>
                                        <th class="w-28 p-2">Camera ID</th>
                                        <th class="p-2">Action / Tindakan</th>
                                        <th class="w-36 p-2">
                                             Due Date (Plan)
                                        </th>
                                        <th class="w-36 p-2">Remarks</th>
                                        <th v-if="!isLocked" class="w-10 p-2 text-center">
                                            Aksi
                                        </th>
                                    </tr>
                                </thead>
                                <tbody
                                    class="divide-y divide-zinc-200 dark:divide-zinc-800"
                                >
                                    <tr
                                        v-for="(act, actIdx) in areas[
                                            activeAreaIndex
                                        ].actions"
                                        :key="actIdx"
                                    >
                                        <td class="p-2">
                                            <input
                                                type="text"
                                                v-model="act.camera_id"
                                                :disabled="isLocked"
                                                placeholder="e.g. Cam #03"
                                                class="w-full rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none disabled:bg-zinc-100 disabled:cursor-not-allowed dark:border-zinc-700 dark:bg-zinc-900 dark:disabled:bg-zinc-800"
                                            />
                                        </td>
                                        <td class="p-2">
                                            <input
                                                type="text"
                                                v-model="act.action"
                                                :disabled="isLocked"
                                                placeholder="e.g. Ganti Adaptor PoE / Periksa Kabel"
                                                class="w-full rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none disabled:bg-zinc-100 disabled:cursor-not-allowed dark:border-zinc-700 dark:bg-zinc-900 dark:disabled:bg-zinc-800"
                                            />
                                        </td>
                                        <td class="p-2">
                                            <input
                                                type="date"
                                                v-model="act.due_date"
                                                :disabled="isLocked"
                                                class="w-full rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none disabled:bg-zinc-100 disabled:cursor-not-allowed dark:border-zinc-700 dark:bg-zinc-900 dark:disabled:bg-zinc-800"
                                            />
                                        </td>
                                        <td class="p-2">
                                            <input
                                                type="text"
                                                v-model="act.remarks"
                                                :disabled="isLocked"
                                                placeholder="e.g. Pending Part"
                                                class="w-full rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none disabled:bg-zinc-100 disabled:cursor-not-allowed dark:border-zinc-700 dark:bg-zinc-900 dark:disabled:bg-zinc-800"
                                            />
                                        </td>
                                        <td v-if="!isLocked" class="p-2 text-center">
                                            <button
                                                type="button"
                                                @click="
                                                    removeAreaAction(
                                                        areas[activeAreaIndex],
                                                        actIdx,
                                                    )
                                                "
                                                class="p-1 text-red-500 hover:text-red-700 cursor-pointer"
                                                title="Hapus baris"
                                            >
                                                <Trash2 class="h-3.5 w-3.5" />
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <div
                                v-else
                                class="py-2 text-xs text-zinc-400 italic"
                            >
                                Tidak ada tindakan perbaikan khusus untuk area
                                ini (semua kamera normal).
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ================= TAB 3: MAINTENANCE & FOTO SEBELUM / SESUDAH ================= -->
                <div v-show="activeTab === 'maintenance'" class="space-y-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3
                                class="text-sm font-bold text-zinc-900 dark:text-zinc-100"
                            >
                                Catatan Pemeliharaan & Dokumentasi Foto
                            </h3>
                            <p class="text-xs text-zinc-500">
                                Setiap catatan pemeliharaan dilengkapi dengan
                                foto Sebelum (Before) dan Sesudah (After).
                            </p>
                        </div>
                        <button
                            v-if="!isLocked"
                            type="button"
                            @click="addMaintenanceRow"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white shadow-sm transition hover:bg-emerald-700 cursor-pointer"
                        >
                            <Plus class="h-3.5 w-3.5" />
                            Tambah Catatan & Foto Maintenance
                        </button>
                    </div>

                    <!-- List of Maintenance Items with Photo Uploaders -->
                    <div v-if="maintenanceItems.length > 0" class="space-y-5">
                        <div
                            v-for="(mItem, mIdx) in maintenanceItems"
                            :key="mIdx"
                            class="space-y-4 rounded-2xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-900/40"
                        >
                            <!-- Header & Remove -->
                            <div
                                class="flex items-center justify-between border-b border-zinc-200 pb-2 dark:border-zinc-800"
                            >
                                <span
                                    class="flex items-center gap-1.5 text-xs font-bold text-emerald-700 dark:text-emerald-400"
                                >
                                    <Wrench class="h-3.5 w-3.5" />
                                    Item Pemeliharaan #{{ mIdx + 1 }}
                                </span>
                                <button
                                    v-if="!isLocked"
                                    type="button"
                                    @click="removeMaintenanceRow(mIdx)"
                                    class="inline-flex items-center gap-1 p-1 text-xs text-red-600 hover:text-red-700 cursor-pointer"
                                    title="Hapus item maintenance ini"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                    Hapus
                                </button>
                            </div>

                            <!-- Inputs: Camera ID, Action / Note, Date, Remarks -->
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                                <div>
                                    <label
                                        class="mb-1 block text-[11px] font-semibold text-zinc-700 dark:text-zinc-300"
                                    >
                                        Target Kamera
                                    </label>
                                    <input
                                        type="text"
                                        v-model="mItem.camera_id"
                                        :disabled="isLocked"
                                        placeholder="e.g. All, Cam #02, PTZ Dome"
                                        class="w-full rounded-xl border border-zinc-300 bg-white px-2.5 py-1.5 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none disabled:bg-zinc-100 disabled:cursor-not-allowed dark:border-zinc-700 dark:bg-zinc-900 dark:disabled:bg-zinc-800"
                                    />
                                </div>

                                <div class="md:col-span-2">
                                    <label
                                        class="mb-1 block text-[11px] font-semibold text-zinc-700 dark:text-zinc-300"
                                    >
                                        Catatan Pemeliharaan / Tindakan
                                    </label>
                                    <input
                                        type="text"
                                        v-model="mItem.action"
                                        :disabled="isLocked"
                                        placeholder="e.g. Pembersihan lensa & konektor kabel"
                                        class="w-full rounded-xl border border-zinc-300 bg-white px-2.5 py-1.5 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none disabled:bg-zinc-100 disabled:cursor-not-allowed dark:border-zinc-700 dark:bg-zinc-900 dark:disabled:bg-zinc-800"
                                    />
                                </div>

                                <div>
                                    <label
                                        class="mb-1 block text-[11px] font-semibold text-zinc-700 dark:text-zinc-300"
                                    >
                                        Tanggal Tindakan
                                    </label>
                                    <input
                                        type="date"
                                        v-model="mItem.act_date"
                                        :disabled="isLocked"
                                        class="w-full rounded-xl border border-zinc-300 bg-white px-2.5 py-1.5 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none disabled:bg-zinc-100 disabled:cursor-not-allowed dark:border-zinc-700 dark:bg-zinc-900 dark:disabled:bg-zinc-800"
                                    />
                                </div>
                            </div>

                            <!-- Paired Before & After Photo Uploaders -->
                            <div
                                class="grid grid-cols-1 gap-4 pt-2 sm:grid-cols-2"
                            >
                                <!-- Before Photo Box -->
                                <div
                                    class="space-y-2 rounded-xl border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-900"
                                >
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <span
                                            class="flex items-center gap-1.5 text-xs font-bold text-zinc-800 dark:text-zinc-200"
                                        >
                                            📷 Foto Sebelum (Before)
                                        </span>
                                        <button
                                            v-if="
                                                !isLocked &&
                                                (mItem.beforePreview ||
                                                    mItem.before_photo)
                                            "
                                            type="button"
                                            @click="
                                                removeMaintenanceBeforePhoto(
                                                    mItem,
                                                )
                                            "
                                            class="text-[11px] font-semibold text-red-500 hover:text-red-700 cursor-pointer"
                                        >
                                            Hapus Foto
                                        </button>
                                    </div>

                                    <div
                                        v-if="
                                            mItem.beforePreview ||
                                            mItem.before_photo
                                        "
                                        class="relative aspect-video w-full overflow-hidden rounded-lg bg-black"
                                    >
                                        <img
                                            :src="
                                                mItem.beforePreview ||
                                                mItem.before_photo!
                                            "
                                            class="h-full w-full object-contain"
                                            alt="Before Photo"
                                        />
                                    </div>
                                    <div
                                        v-else-if="isLocked"
                                        class="flex aspect-video w-full flex-col items-center justify-center rounded-lg border border-zinc-200 bg-zinc-50 text-zinc-400 dark:border-zinc-800 dark:bg-zinc-800/30"
                                    >
                                        <span class="text-xs italic">(Tidak ada foto sebelum)</span>
                                    </div>
                                    <label
                                        v-else
                                        class="flex aspect-video w-full cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-zinc-300 transition hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800/50"
                                    >
                                        <UploadCloud
                                            class="mb-1 h-6 w-6 text-zinc-400"
                                        />
                                        <span class="text-xs text-zinc-500"
                                            >Pilih Foto Sebelum</span
                                        >
                                        <input
                                            type="file"
                                            accept="image/*"
                                            class="hidden"
                                            @change="
                                                (e) =>
                                                    onMaintenanceBeforePhotoChange(
                                                        e,
                                                        mItem,
                                                    )
                                            "
                                        />
                                    </label>
                                </div>

                                <!-- After Photo Box -->
                                <div
                                    class="space-y-2 rounded-xl border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-900"
                                >
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <span
                                            class="flex items-center gap-1.5 text-xs font-bold text-zinc-800 dark:text-zinc-200"
                                        >
                                            ✨ Foto Sesudah (After)
                                        </span>
                                        <button
                                            v-if="
                                                !isLocked &&
                                                (mItem.afterPreview ||
                                                    mItem.after_photo)
                                            "
                                            type="button"
                                            @click="
                                                removeMaintenanceAfterPhoto(
                                                    mItem,
                                                )
                                            "
                                            class="text-[11px] font-semibold text-red-500 hover:text-red-700 cursor-pointer"
                                        >
                                            Hapus Foto
                                        </button>
                                    </div>

                                    <div
                                        v-if="
                                            mItem.afterPreview ||
                                            mItem.after_photo
                                        "
                                        class="relative aspect-video w-full overflow-hidden rounded-lg bg-black"
                                    >
                                        <img
                                            :src="
                                                mItem.afterPreview ||
                                                mItem.after_photo!
                                            "
                                            class="h-full w-full object-contain"
                                            alt="After Photo"
                                        />
                                    </div>
                                    <div
                                        v-else-if="isLocked"
                                        class="flex aspect-video w-full flex-col items-center justify-center rounded-lg border border-zinc-200 bg-zinc-50 text-zinc-400 dark:border-zinc-800 dark:bg-zinc-800/30"
                                    >
                                        <span class="text-xs italic">(Tidak ada foto sesudah)</span>
                                    </div>
                                    <label
                                        v-else
                                        class="flex aspect-video w-full cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-zinc-300 transition hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800/50"
                                    >
                                        <UploadCloud
                                            class="mb-1 h-6 w-6 text-zinc-400"
                                        />
                                        <span class="text-xs text-zinc-500"
                                            >Pilih Foto Sesudah</span
                                        >
                                        <input
                                            type="file"
                                            accept="image/*"
                                            class="hidden"
                                            @change="
                                                (e) =>
                                                    onMaintenanceAfterPhotoChange(
                                                        e,
                                                        mItem,
                                                    )
                                            "
                                        />
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State for Maintenance -->
                    <div
                        v-else
                        class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-zinc-200 bg-zinc-50/50 p-8 text-center dark:border-zinc-800 dark:bg-zinc-900/20"
                    >
                        <Wrench class="mb-2 h-8 w-8 text-zinc-400" />
                        <p class="text-xs font-semibold text-zinc-600 dark:text-zinc-300">
                            Belum ada catatan pemeliharaan atau pekerjaan perbaikan.
                        </p>
                        <p class="mt-0.5 text-[11px] text-zinc-400">
                            Default kosong. Jika seluruh kamera berfungsi normal, bagian ini tidak perlu diisi.
                        </p>
                        <button
                            v-if="!isLocked"
                            type="button"
                            @click="addMaintenanceRow"
                            class="mt-3 inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white shadow-sm transition hover:bg-emerald-700 cursor-pointer"
                        >
                            <Plus class="h-3.5 w-3.5" />
                            Tambah Pekerjaan Pemeliharaan
                        </button>
                    </div>
                </div>

                <!-- ================= TAB 4: TANDA TANGAN ================= -->
                <div v-show="activeTab === 'signatures'" class="space-y-6">
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <!-- Dept #1 (IT) Signature -->
                        <div
                            class="space-y-4 rounded-2xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-800 dark:bg-zinc-900/40"
                        >
                            <div
                                class="flex items-center justify-between border-b border-zinc-200 pb-2 dark:border-zinc-800"
                            >
                                <h4
                                    class="text-sm font-bold text-zinc-900 dark:text-zinc-100"
                                >
                                    Tanda Tangan Dept #1 (IT)
                                </h4>
                                <button
                                    v-if="!isLocked"
                                    type="button"
                                    @click="clearDept1Signature"
                                    class="text-xs font-semibold text-zinc-500 hover:text-red-600 cursor-pointer"
                                >
                                    Reset TTD
                                </button>
                            </div>

                            <div>
                                <label
                                    class="mb-1 block text-xs font-semibold text-zinc-700 dark:text-zinc-300"
                                >
                                    Nama Dept
                                </label>
                                <input
                                    type="text"
                                    v-model="form.signature_dept1_name"
                                    :disabled="isLocked"
                                    class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none disabled:bg-zinc-100 disabled:cursor-not-allowed dark:border-zinc-700 dark:bg-zinc-900 dark:disabled:bg-zinc-800"
                                />
                            </div>

                            <div>
                                <label
                                    class="mb-1 block text-xs font-semibold text-zinc-700 dark:text-zinc-300"
                                >
                                    Nama Penandatangan
                                </label>
                                <input
                                    type="text"
                                    v-model="form.signature_dept1_signer"
                                    :disabled="isLocked"
                                    class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none disabled:bg-zinc-100 disabled:cursor-not-allowed dark:border-zinc-700 dark:bg-zinc-900 dark:disabled:bg-zinc-800"
                                />
                            </div>

                            <div>
                                <label
                                    class="mb-1 block text-xs font-semibold text-zinc-700 dark:text-zinc-300"
                                >
                                    Goreskan Tanda Tangan Digital
                                </label>
                                <div
                                    class="overflow-hidden rounded-xl border border-zinc-300 bg-white dark:border-zinc-700 min-h-[160px]"
                                >
                                    <SignaturePad
                                        ref="signaturePad1"
                                        :initial-image="
                                            form.signature_dept1_image
                                        "
                                        :height="160"
                                        :disabled="isLocked"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Dept #2 (Exim/Other) Signature -->
                        <div
                            class="space-y-4 rounded-2xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-800 dark:bg-zinc-900/40"
                        >
                            <div
                                class="flex items-center justify-between border-b border-zinc-200 pb-2 dark:border-zinc-800"
                            >
                                <h4
                                    class="text-sm font-bold text-zinc-900 dark:text-zinc-100"
                                >
                                    Tanda Tangan Dept #2 (Exim / Saksi)
                                </h4>
                                <button
                                    v-if="!isLocked"
                                    type="button"
                                    @click="clearDept2Signature"
                                    class="text-xs font-semibold text-zinc-500 hover:text-red-600 cursor-pointer"
                                >
                                    Reset TTD
                                </button>
                            </div>

                            <div>
                                <label
                                    class="mb-1 block text-xs font-semibold text-zinc-700 dark:text-zinc-300"
                                >
                                    Nama Dept
                                </label>
                                <input
                                    type="text"
                                    v-model="form.signature_dept2_name"
                                    :disabled="isLocked"
                                    class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none disabled:bg-zinc-100 disabled:cursor-not-allowed dark:border-zinc-700 dark:bg-zinc-900 dark:disabled:bg-zinc-800"
                                />
                            </div>

                            <div>
                                <label
                                    class="mb-1 block text-xs font-semibold text-zinc-700 dark:text-zinc-300"
                                >
                                    Nama Penandatangan
                                </label>
                                <input
                                    type="text"
                                    v-model="form.signature_dept2_signer"
                                    :disabled="isLocked"
                                    placeholder="Contoh: Nama PIC Exim"
                                    class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none disabled:bg-zinc-100 disabled:cursor-not-allowed dark:border-zinc-700 dark:bg-zinc-900 dark:disabled:bg-zinc-800"
                                />
                            </div>

                            <div>
                                <label
                                    class="mb-1 block text-xs font-semibold text-zinc-700 dark:text-zinc-300"
                                >
                                    Goreskan Tanda Tangan Digital
                                </label>
                                <div
                                    class="overflow-hidden rounded-xl border border-zinc-300 bg-white dark:border-zinc-700 min-h-[160px]"
                                >
                                    <SignaturePad
                                        ref="signaturePad2"
                                        :initial-image="
                                            form.signature_dept2_image
                                        "
                                        :height="160"
                                        :disabled="isLocked"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div
                class="flex flex-wrap items-center justify-between gap-4 border-t border-zinc-200 bg-zinc-50 px-6 py-4 dark:border-zinc-800 dark:bg-zinc-900/50"
            >
                <div class="flex flex-wrap items-center gap-4">
                    <div class="text-xs text-zinc-500">
                        <span v-if="areas.length > 0">
                            {{ areas.length }} Area Pengecekan |
                            {{ maintenanceItems.length }} Item Maintenance
                        </span>
                    </div>
                    <div
                        v-if="errorMessage"
                        class="flex max-w-md items-center gap-1.5 rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-600 dark:border-rose-900 dark:bg-rose-950/50 dark:text-rose-400"
                    >
                        <AlertTriangle
                            class="h-3.5 w-3.5 shrink-0 text-rose-500"
                        />
                        <span class="truncate">{{ errorMessage }}</span>
                    </div>
                </div>
                <div class="ml-auto flex items-center gap-3">
                    <template v-if="isLocked">
                        <button
                            type="button"
                            @click="closeModal"
                            class="cursor-pointer rounded-xl border border-zinc-300 px-4 py-2 text-xs font-bold text-zinc-700 transition hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800"
                        >
                            Tutup
                        </button>
                        <button
                            type="button"
                            @click="openPrint"
                            class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-[#003628] px-5 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-[#004d39] active:scale-95"
                        >
                            <Printer class="h-4 w-4" />
                            Cetak / Simpan PDF
                        </button>
                    </template>
                    <template v-else>
                        <button
                            type="button"
                            @click="closeModal"
                            class="cursor-pointer rounded-xl border border-zinc-300 px-4 py-2 text-xs font-bold text-zinc-700 transition hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800"
                        >
                            Batal
                        </button>
                        <button
                            type="button"
                            @click.prevent="handleSubmit"
                            :disabled="isSubmitting || isCompleting"
                            class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-emerald-700 active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <Loader2
                                v-if="isSubmitting"
                                class="h-4 w-4 animate-spin"
                            />
                            <Save v-else class="h-4 w-4" />
                            {{
                                isSubmitting
                                    ? 'Menyimpan...'
                                    : reportId
                                      ? 'Simpan Perubahan'
                                      : 'Simpan Laporan'
                            }}
                        </button>
                        <button
                            v-if="reportId"
                            type="button"
                            @click.prevent="handleCompleteReport"
                            :disabled="isSubmitting || isCompleting"
                            class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-teal-800 px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-teal-900 active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
                            title="Kunci data laporan ini agar tidak dapat diedit kembali"
                        >
                            <Loader2
                                v-if="isCompleting"
                                class="h-4 w-4 animate-spin"
                            />
                            <CheckCircle2 v-else class="h-4 w-4" />
                            {{ isCompleting ? 'Mengunci...' : 'Selesaikan & Kunci' }}
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
