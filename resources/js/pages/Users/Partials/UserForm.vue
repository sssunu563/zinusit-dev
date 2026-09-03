<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import {
    LucideChevronDown as ChevronDown,
    LucideChevronUp as ChevronUp,
    LucideUser as User,
    LucideMail as Mail,
    LucideShieldCheck as Shield,
    LucideSmartphone as Smartphone,
    LucideInfo as Info,
    LucideSave as Save,
    LucideX as X,
    LucideBuilding2 as Building2,
    LucideMapPin as MapPin,
    LucideBriefcase as Briefcase,
    LucideCheckCircle2 as CheckCircle,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------
type SelectOption = { id: number; name: string };

type UserFormData = {
    first_name: string;
    last_name: string;
    username: string;
    email: string;
    employee_num: string;
    phone: string;
    mobile: string;
    jobtitle: string;
    website: string;
    notes: string;
    manager_id: string;
    location_id: string;
    department_id: string;
    company_id: string;
    password: string;
    password_confirmation: string;
    vip: boolean;
    remote: boolean;
    auto_assign_licenses: boolean;
};

type Props = {
    title: string;
    submitLabel: string;
    submitUrl: string;
    method: 'post' | 'put';
    userId?: number;
    options: {
        managers: SelectOption[];
        locations: SelectOption[];
        departments: SelectOption[];
        companies: SelectOption[];
    };
    initialValues: UserFormData;
    isModal?: boolean;
    hidePassword?: boolean;
};

const props = defineProps<Props>();
const emit = defineEmits(['success']);

const form = useForm({ ...props.initialValues });

const optionalOpen = ref(true);

// ---------------------------------------------------------------------------
// API error
// ---------------------------------------------------------------------------
const apiError = computed(() => {
    const errors = form.errors as Record<string, string | string[] | undefined>;
    const value = errors.api;
    if (Array.isArray(value)) return value.join(', ');
    return value || null;
});

// ---------------------------------------------------------------------------
// Submit
// ---------------------------------------------------------------------------
const submit = () => {
    const opts = {
        preserveScroll: true,
        onSuccess: () => emit('success'),
    };
    if (props.method === 'put') {
        form.put(props.submitUrl, opts);
    } else {
        form.post(props.submitUrl, opts);
    }
};
</script>

<template>
    <div
        class="relative overflow-hidden rounded-[32px] border border-[#003628]/10 bg-white shadow-xl shadow-[#003628]/10"
    >
        <!-- Decorative background -->
        <div
            class="pointer-events-none absolute top-0 right-0 -mt-24 -mr-24 h-96 w-96 rounded-full bg-[#FFF2CC]/50 blur-[120px]"
        />

        <div class="relative z-10 p-8 md:p-12">
            <form @submit.prevent="submit">
                <!-- 1. PERSONAL IDENTITY -->
                <div class="mb-12">
                    <div class="mb-8 flex items-center gap-3">
                        <div class="h-6 w-1 rounded-full bg-primary" />
                        <h3
                            class="text-[10px] font-black tracking-[0.2em] text-primary uppercase"
                        >
                            Identitas Pribadi
                        </h3>
                    </div>

                    <div class="grid grid-cols-1 gap-8 md:grid-cols-2">
                        <div class="space-y-2">
                            <label
                                class="ml-1 text-[10px] font-black tracking-widest text-[#003628]/60 uppercase"
                                >Nama Depan
                                <span class="text-red-500">*</span></label
                            >
                            <div class="group relative">
                                <input
                                    v-model="form.first_name"
                                    type="text"
                                    class="h-11 w-full rounded-xl border border-[#003628]/10 bg-[#FFF2CC]/30 px-4 text-[13px] font-bold text-[#003628] transition-all outline-none placeholder:text-[#003628]/40 focus:border-[#003628]/30 focus:ring-4 focus:ring-[#003628]/10"
                                    placeholder="Contoh: Budi"
                                />
                                <div
                                    class="absolute top-0 right-0 bottom-0 w-1 rounded-r-xl bg-[#003628]/20 transition-colors group-focus-within:bg-[#003628]"
                                />
                            </div>
                            <p
                                v-if="form.errors.first_name"
                                class="letter-spacing-[0.05em] mt-1 ml-1 text-[10px] font-black text-red-500 uppercase"
                            >
                                {{ form.errors.first_name }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <label
                                class="ml-1 text-[10px] font-black tracking-widest text-[#003628]/60 uppercase"
                                >Nama Belakang</label
                            >
                            <input
                                v-model="form.last_name"
                                type="text"
                                class="h-11 w-full rounded-xl border border-[#003628]/10 bg-[#FFF2CC]/30 px-4 text-[13px] font-bold text-[#003628] transition-all outline-none placeholder:text-[#003628]/40 focus:border-[#003628]/30 focus:ring-4 focus:ring-[#003628]/10"
                                placeholder="Contoh: Santoso"
                            />
                        </div>

                        <div class="space-y-2">
                            <label
                                class="ml-1 text-[10px] font-black tracking-widest text-[#003628]/60 uppercase"
                                >Alamat Email
                                <span class="text-red-500">*</span></label
                            >
                            <div class="group relative">
                                <input
                                    v-model="form.email"
                                    type="email"
                                    class="h-11 w-full rounded-xl border border-[#003628]/10 bg-[#FFF2CC]/30 px-4 text-[13px] font-bold text-[#003628] transition-all outline-none placeholder:text-[#003628]/40 focus:border-[#003628]/30 focus:ring-4 focus:ring-[#003628]/10"
                                    placeholder="budi.santoso@zinus.com"
                                />
                                <div
                                    class="absolute top-0 right-0 bottom-0 w-1 rounded-r-xl bg-[#003628]/20 transition-colors group-focus-within:bg-[#003628]"
                                />
                            </div>
                            <p
                                v-if="form.errors.email"
                                class="letter-spacing-[0.05em] mt-1 ml-1 text-[10px] font-black text-red-500 uppercase"
                            >
                                {{ form.errors.email }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <label
                                class="ml-1 text-[10px] font-black tracking-widest text-[#003628]/60 uppercase"
                                >LDAP Username</label
                            >
                            <input
                                v-model="form.username"
                                type="text"
                                class="h-11 w-full rounded-xl border border-[#003628]/10 bg-[#FFF2CC]/30 px-4 text-[13px] font-bold text-[#003628] transition-all outline-none placeholder:text-[#003628]/40 focus:border-[#003628]/30 focus:ring-4 focus:ring-[#003628]/10"
                                placeholder="ID login domain"
                            />
                        </div>

                        <div
                            v-if="!hidePassword && method === 'post'"
                            class="space-y-2"
                        >
                            <label
                                class="ml-1 text-[10px] font-black tracking-widest text-[#003628]/60 uppercase"
                                >Kata Sandi Awal
                                <span class="text-red-500">*</span></label
                            >
                            <div class="group relative">
                                <input
                                    v-model="form.password"
                                    type="password"
                                    class="h-11 w-full rounded-xl border border-[#003628]/10 bg-[#FFF2CC]/30 px-4 text-[13px] font-bold text-[#003628] transition-all outline-none placeholder:text-[#003628]/40 focus:border-[#003628]/30 focus:ring-4 focus:ring-[#003628]/10"
                                />
                                <div
                                    class="absolute top-0 right-0 bottom-0 w-1 rounded-r-xl bg-[#003628]/20 transition-colors group-focus-within:bg-[#003628]"
                                />
                            </div>
                            <p
                                v-if="form.errors.password"
                                class="letter-spacing-[0.05em] mt-1 ml-1 text-[10px] font-black text-red-500 uppercase"
                            >
                                {{ form.errors.password }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- 2. ADMINISTRATIVE & GOVERNANCE -->
                <div class="mb-12">
                    <div class="mb-8 flex items-center gap-3">
                        <div class="h-6 w-1 rounded-full bg-primary" />
                        <h3
                            class="text-[10px] font-black tracking-[0.2em] text-primary uppercase"
                        >
                            Struktur Organisasi
                        </h3>
                    </div>

                    <div
                        class="grid grid-cols-1 gap-8 md:grid-cols-2 lg:grid-cols-3"
                    >
                        <div class="space-y-2">
                            <label
                                class="ml-1 text-[10px] font-black tracking-widest text-[#003628]/60 uppercase"
                                >Nomor Karyawan</label
                            >
                            <input
                                v-model="form.employee_num"
                                type="text"
                                class="h-11 w-full rounded-xl border border-[#003628]/10 bg-[#FFF2CC]/30 px-4 text-[13px] font-bold text-[#003628] transition-all outline-none placeholder:text-[#003628]/40 focus:border-[#003628]/30 focus:ring-4 focus:ring-[#003628]/10"
                                placeholder="EMP-001"
                            />
                        </div>

                        <div class="space-y-2">
                            <label
                                class="ml-1 text-[10px] font-black tracking-widest text-[#003628]/60 uppercase"
                                >Jabatan</label
                            >
                            <input
                                v-model="form.jobtitle"
                                type="text"
                                class="h-11 w-full rounded-xl border border-[#003628]/10 bg-[#FFF2CC]/30 px-4 text-[13px] font-bold text-[#003628] transition-all outline-none placeholder:text-[#003628]/40 focus:border-[#003628]/30 focus:ring-4 focus:ring-[#003628]/10"
                                placeholder="Contoh: Senior Specialist"
                            />
                        </div>

                        <div class="space-y-2">
                            <label
                                class="ml-1 text-[10px] font-black tracking-widest text-[#003628]/60 uppercase"
                                >Entitas / Perusahaan</label
                            >
                            <div class="relative">
                                <select
                                    v-model="form.company_id"
                                    class="h-11 w-full appearance-none rounded-xl border border-[#003628]/10 bg-[#FFF2CC]/30 px-4 text-[13px] font-bold text-[#003628] transition-all outline-none focus:border-[#003628]/30 focus:ring-4 focus:ring-[#003628]/10"
                                >
                                    <option value="">Pilih Perusahaan</option>
                                    <option
                                        v-for="co in options.companies"
                                        :key="co.id"
                                        :value="String(co.id)"
                                    >
                                        {{ co.name }}
                                    </option>
                                </select>
                                <ChevronDown
                                    class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-[#003628]/50"
                                />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label
                                class="ml-1 text-[10px] font-black tracking-widest text-[#003628]/60 uppercase"
                                >Departemen</label
                            >
                            <div class="relative">
                                <select
                                    v-model="form.department_id"
                                    class="h-11 w-full appearance-none rounded-xl border border-[#003628]/10 bg-[#FFF2CC]/30 px-4 text-[13px] font-bold text-[#003628] transition-all outline-none focus:border-[#003628]/30 focus:ring-4 focus:ring-[#003628]/10"
                                >
                                    <option value="">Pilih Departemen</option>
                                    <option
                                        v-for="d in options.departments"
                                        :key="d.id"
                                        :value="String(d.id)"
                                    >
                                        {{ d.name }}
                                    </option>
                                </select>
                                <ChevronDown
                                    class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-[#003628]/50"
                                />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label
                                class="ml-1 text-[10px] font-black tracking-widest text-[#003628]/60 uppercase"
                                >Lokasi Kerja</label
                            >
                            <div class="relative">
                                <select
                                    v-model="form.location_id"
                                    class="h-11 w-full appearance-none rounded-xl border border-[#003628]/10 bg-[#FFF2CC]/30 px-4 text-[13px] font-bold text-[#003628] transition-all outline-none focus:border-[#003628]/30 focus:ring-4 focus:ring-[#003628]/10"
                                >
                                    <option value="">Pilih Lokasi</option>
                                    <option
                                        v-for="l in options.locations"
                                        :key="l.id"
                                        :value="String(l.id)"
                                    >
                                        {{ l.name }}
                                    </option>
                                </select>
                                <ChevronDown
                                    class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-[#003628]/50"
                                />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label
                                class="ml-1 text-[10px] font-black tracking-widest text-[#003628]/60 uppercase"
                                >Atasan / Manajer</label
                            >
                            <div class="relative">
                                <select
                                    v-model="form.manager_id"
                                    class="h-11 w-full appearance-none rounded-xl border border-[#003628]/10 bg-[#FFF2CC]/30 px-4 text-[13px] font-bold text-[#003628] transition-all outline-none focus:border-[#003628]/30 focus:ring-4 focus:ring-[#003628]/10"
                                >
                                    <option value="">Pilih Manajer</option>
                                    <option
                                        v-for="m in options.managers"
                                        :key="m.id"
                                        :value="String(m.id)"
                                    >
                                        {{ m.name }}
                                    </option>
                                </select>
                                <ChevronDown
                                    class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-[#003628]/50"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. ADDITIONAL SETTINGS -->
                <div class="mb-12">
                    <div class="mb-8 flex items-center gap-3">
                        <div class="h-6 w-1 rounded-full bg-[#003628]" />
                        <h3
                            class="text-[10px] font-black tracking-[0.2em] text-[#003628] uppercase"
                        >
                            Pengaturan Tambahan
                        </h3>
                    </div>

                    <div
                        class="mb-8 flex flex-wrap items-center gap-6 rounded-[28px] border border-dashed border-[#003628]/10 bg-[#FFF2CC]/30 p-6"
                    >
                        <label
                            class="group flex cursor-pointer items-center gap-3"
                        >
                            <div
                                class="relative h-6 w-11 rounded-full transition-all duration-300"
                                :class="
                                    form.vip
                                        ? 'bg-[#003628] shadow-lg shadow-[#003628]/25'
                                        : 'bg-[#003628]/30'
                                "
                            >
                                <div
                                    class="absolute top-1 left-1 size-4 rounded-full bg-white shadow-md transition-all duration-300"
                                    :class="form.vip ? 'translate-x-5' : ''"
                                />
                                <input
                                    type="checkbox"
                                    v-model="form.vip"
                                    class="sr-only"
                                />
                            </div>
                            <span
                                class="text-[11px] font-black tracking-widest text-[#003628]/70 uppercase transition-colors group-hover:text-[#003628]"
                                >Pengguna VIP</span
                            >
                        </label>
                        <label
                            class="group flex cursor-pointer items-center gap-3"
                        >
                            <div
                                class="relative h-6 w-11 rounded-full transition-all duration-300"
                                :class="
                                    form.remote
                                        ? 'bg-[#003628] shadow-lg shadow-[#003628]/25'
                                        : 'bg-[#003628]/30'
                                "
                            >
                                <div
                                    class="absolute top-1 left-1 size-4 rounded-full bg-white shadow-md transition-all duration-300"
                                    :class="form.remote ? 'translate-x-5' : ''"
                                />
                                <input
                                    type="checkbox"
                                    v-model="form.remote"
                                    class="sr-only"
                                />
                            </div>
                            <span
                                class="text-[11px] font-black tracking-widest text-[#003628]/70 uppercase transition-colors group-hover:text-[#003628]"
                                >Bekerja Remote</span
                            >
                        </label>
                    </div>

                    <div class="space-y-2">
                        <label
                            class="ml-1 text-[10px] font-black tracking-widest text-[#003628]/60 uppercase"
                            >Catatan Audit</label
                        >
                        <textarea
                            v-model="form.notes"
                            rows="3"
                            class="w-full rounded-2xl border border-[#003628]/10 bg-[#FFF2CC]/30 p-4 text-[13px] font-bold text-[#003628] transition-all outline-none placeholder:text-[#003628]/40 focus:border-[#003628]/30 focus:ring-4 focus:ring-[#003628]/10"
                            placeholder="Detail catatan internal HR/IT..."
                        />
                    </div>
                </div>

                <!-- Footer Actions -->
                <div
                    class="mt-12 flex items-center justify-between border-t border-[#003628]/10 pt-10"
                >
                    <Link
                        v-if="!isModal"
                        href="/users"
                        class="flex h-12 items-center gap-2 rounded-2xl border border-[#003628]/10 bg-[#FFF2CC] px-6 text-[11px] font-black tracking-[0.1em] text-[#003628]/70 uppercase shadow-sm transition-all hover:border-[#003628]/30 hover:text-[#003628] active:scale-95"
                    >
                        <X class="size-3.5" />
                        <span>Batalkan</span>
                    </Link>
                    <div v-else />

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="flex h-12 items-center gap-2 rounded-2xl bg-[#003628] px-10 text-[11px] font-black tracking-[0.2em] text-white uppercase shadow-xl shadow-[#003628]/25 transition-all hover:brightness-110 active:scale-95 disabled:opacity-50"
                    >
                        <Save v-if="!form.processing" class="size-4" />
                        <CheckCircle v-else class="size-4 animate-spin" />
                        <span>{{
                            form.processing ? 'Menyimpan...' : submitLabel
                        }}</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Error Notice -->
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0 -translate-y-4"
            enter-to-class="opacity-100 translate-y-0"
        >
            <div
                v-if="apiError"
                class="mx-8 mb-8 flex items-start gap-4 rounded-2xl border border-red-500/20 bg-red-500/5 p-4 shadow-sm shadow-red-500/5"
            >
                <Info class="mt-0.5 size-5 shrink-0 text-red-500" />
                <div class="space-y-1">
                    <p
                        class="text-[10px] leading-none font-black tracking-widest text-red-400 uppercase"
                    >
                        Transmission Error
                    </p>
                    <p
                        class="text-[11px] leading-relaxed font-bold text-red-600"
                    >
                        {{ apiError }}
                    </p>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
/* Hidden scrollbar but functional */
::-webkit-scrollbar {
    width: 0px;
    background: transparent;
}
</style>
