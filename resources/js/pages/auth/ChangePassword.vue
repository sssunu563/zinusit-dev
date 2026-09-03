<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { LockKeyhole } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { update } from '@/routes/password/change';
</script>

<template>
    <AuthBase
        title="Buat Password Baru"
        description="Ini adalah login pertama Anda. Harap buat password baru sebelum melanjutkan."
    >
        <Head title="Buat Password Baru" />

        <Form
            v-bind="update.form()"
            :reset-on-error="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-8"
        >
            <div
                class="rounded-[24px] border border-amber-100 bg-amber-50 px-6 py-5 text-sm text-slate-600"
            >
                <div class="flex items-center gap-2 mb-2">
                    <div class="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse" />
                    <span class="text-[10px] font-black tracking-[0.2em] text-amber-600 uppercase">Security Mandate</span>
                </div>
                <p class="text-[12px] font-medium leading-relaxed">
                    This is your initial authentication. Please establish a unique enterprise passcode to secure your identity.
                </p>
            </div>

            <div class="space-y-3">
                <Label
                    for="password"
                    class="text-[10px] font-black tracking-widest text-slate-400 uppercase ml-1"
                >
                    New Access Passcode
                </Label>
                <Input
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="new-password"
                    placeholder="Minimum 8 characters"
                    class="h-12 rounded-xl border-slate-200 bg-slate-50/30 px-4 text-sm text-slate-900 placeholder:text-slate-300 focus:border-primary/50 focus:bg-white transition-all outline-none"
                    required
                    autofocus
                />
                <InputError :message="errors.password" />
            </div>

            <div class="space-y-3">
                <Label
                    for="password_confirmation"
                    class="text-[10px] font-black tracking-widest text-slate-400 uppercase ml-1"
                >
                    Verify Passcode
                </Label>
                <Input
                    id="password_confirmation"
                    name="password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    placeholder="Repeat new passcode"
                    class="h-12 rounded-xl border-slate-200 bg-slate-50/30 px-4 text-sm text-slate-900 placeholder:text-slate-300 focus:border-primary/50 focus:bg-white transition-all outline-none"
                    required
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-4 h-12 w-full rounded-2xl bg-primary text-sm font-bold text-white shadow-xl shadow-primary/20 transition-all duration-300 hover:bg-primary-dark hover:shadow-2xl hover:shadow-primary/30 active:scale-[0.98] disabled:opacity-50"
                :disabled="processing"
            >
                <Spinner v-if="processing" class="mr-2 size-4" />
                <LockKeyhole v-else class="mr-2 size-4" />
                {{ processing ? 'Updating Identity...' : 'Commit & Proceed' }}
            </Button>
        </Form>
    </AuthBase>
</template>
