<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { LockKeyhole, LayoutDashboard } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
// import { register } from '@/routes';
import { store } from '@/routes/login';
// import { request } from '@/routes/password';

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}>();
</script>

<template>
    <AuthBase title="Login">
        <Head title="Masuk" />

        <!-- Status message -->
        <div
            v-if="status"
            class="mb-5 rounded-lg border border-green-200/80 bg-green-50 px-4 py-2.5 text-sm text-green-800"
        >
            {{ status }}
        </div>

        <Form
            v-bind="store.form()"
            :reset-on-success="['password']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <!-- Username -->
            <div class="space-y-2">
                <Label
                    for="email"
                    class="ml-1 text-[10px] font-black tracking-[0.1em] text-slate-500 uppercase"
                >
                    Nama Pengguna
                </Label>
                <Input
                    id="email"
                    type="text"
                    name="email"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="username"
                    placeholder="Nama pengguna"
                    class="app-input-shell h-12 rounded-xl px-4 text-sm text-slate-900 transition-all duration-300 placeholder:text-slate-300"
                />
                <InputError :message="errors.email" />
            </div>

            <!-- Password -->
            <div class="space-y-2">
                <div
                    class="ml-1 flex cursor-default items-center justify-between text-slate-500 transition-colors hover:text-primary"
                >
                    <Label
                        for="password"
                        class="text-[10px] font-black tracking-[0.1em] uppercase"
                    >
                        Kata Sandi
                    </Label>
                </div>
                <div class="group relative">
                    <LockKeyhole
                        class="pointer-events-none absolute top-1/2 left-4 h-4 w-4 -translate-y-1/2 text-slate-400 transition-colors group-focus-within:text-primary"
                    />
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        class="app-input-shell h-12 rounded-xl pr-4 pl-11 text-sm text-slate-900 transition-all duration-300 placeholder:text-slate-300"
                    />
                </div>
                <InputError :message="errors.password" />
            </div>

            <!-- Remember me -->
            <Label
                for="remember"
                class="group ml-1 flex cursor-pointer items-center gap-3 text-[13px] text-slate-500 transition-colors select-none hover:text-primary"
            >
                <Checkbox
                    id="remember"
                    name="remember"
                    :tabindex="3"
                    class="rounded-md border-slate-300 bg-white transition-all group-hover:border-primary data-[state=checked]:border-primary data-[state=checked]:bg-primary"
                />
                <span class="font-medium">Ingat saya</span>
            </Label>

            <!-- Submit -->
            <Button
                type="submit"
                class="hover:bg-primary-light mt-4 h-12 w-full rounded-2xl bg-primary text-sm font-bold text-white shadow-xl shadow-primary/20 transition-all duration-300 hover:shadow-2xl hover:shadow-primary/30 active:scale-[0.98] disabled:opacity-50"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" class="mr-2 size-4" />
                {{ processing ? 'Sedang masuk...' : 'Masuk' }}
            </Button>

            <!-- Employee Asset Portal Link -->
            <div class="mt-4 border-t border-slate-100 pt-6 text-center">
                <p
                    class="mb-4 text-[10px] font-black tracking-[0.2em] text-slate-300 uppercase"
                >
                    Layanan Mandiri Karyawan
                </p>
                <a
                    href="/check-assets"
                    target="_blank"
                    class="group inline-flex items-center gap-2 rounded-2xl border border-slate-100 bg-slate-50 px-6 py-3 text-[11px] font-black tracking-widest text-slate-500 uppercase shadow-sm transition-all hover:border-primary/20 hover:bg-primary/[0.02] hover:text-primary"
                >
                    <LayoutDashboard class="size-3.5" />
                    Cek Aset Saya
                </a>
            </div>
        </Form>
    </AuthBase>
</template>
