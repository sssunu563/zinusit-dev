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
    <AuthBase
        title="Login"
    >
        <Head title="Log in" />

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
                <Label for="email" class="text-[10px] font-black tracking-[0.1em] text-slate-500 uppercase ml-1">
                    Username
                </Label>
                <Input
                    id="email"
                    type="text"
                    name="email"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="username"
                    placeholder="Username"
                    class="app-input-shell h-12 rounded-xl px-4 text-sm text-slate-900 placeholder:text-slate-300 transition-all duration-300"
                />
                <InputError :message="errors.email" />
            </div>

            <!-- Password -->
            <div class="space-y-2">
                <div class="flex items-center justify-between ml-1 text-slate-500 hover:text-primary transition-colors cursor-default">
                    <Label for="password" class="text-[10px] font-black tracking-[0.1em] uppercase">
                        Password
                    </Label>
                </div>
                <div class="relative group">
                    <LockKeyhole class="pointer-events-none absolute top-1/2 left-4 h-4 w-4 -translate-y-1/2 text-slate-400 group-focus-within:text-primary transition-colors" />
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        class="app-input-shell h-12 rounded-xl pl-11 pr-4 text-sm text-slate-900 placeholder:text-slate-300 transition-all duration-300"
                    />
                </div>
                <InputError :message="errors.password" />
            </div>

            <!-- Remember me -->
            <Label for="remember" class="flex cursor-pointer items-center gap-3 text-[13px] text-slate-500 ml-1 select-none hover:text-primary transition-colors group">
                <Checkbox
                    id="remember"
                    name="remember"
                    :tabindex="3"
                    class="border-slate-300 bg-white data-[state=checked]:border-primary data-[state=checked]:bg-primary rounded-md transition-all group-hover:border-primary"
                />
                <span class="font-medium">Remember me</span>
            </Label>

            <!-- Submit -->
            <Button
                type="submit"
                class="mt-4 h-12 w-full rounded-2xl bg-primary text-sm font-bold text-white shadow-xl shadow-primary/20 transition-all duration-300 hover:bg-primary-light hover:shadow-2xl hover:shadow-primary/30 active:scale-[0.98] disabled:opacity-50"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" class="mr-2 size-4" />
                {{ processing ? 'Logging in...' : 'Log in' }}
            </Button>

            <!-- Employee Asset Portal Link -->
            <div class="mt-4 pt-6 border-t border-slate-100 text-center">
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-300 mb-4">Employee Self-Service</p>
                <a href="/check-assets" target="_blank" class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-slate-50 border border-slate-100 text-slate-500 hover:text-primary hover:border-primary/20 hover:bg-primary/[0.02] transition-all text-[11px] font-black uppercase tracking-widest group shadow-sm">
                    <LayoutDashboard class="size-3.5" />
                    Cek Aset Saya
                </a>
            </div>
        </Form>
    </AuthBase>
</template>
