<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { store } from '@/routes/password/confirm';
</script>

<template>
    <AuthLayout
        title="Konfirmasi password"
        description="Area ini memerlukan verifikasi ulang password sebelum Anda bisa melanjutkan."
    >
        <Head title="Konfirmasi password" />

        <Form
            v-bind="store.form()"
            reset-on-success
            v-slot="{ errors, processing }"
        >
            <div class="space-y-8">
                <div class="space-y-3">
                    <Label for="password" class="text-[10px] font-black tracking-widest text-slate-400 uppercase ml-1">Identity Security Passcode</Label>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        autofocus
                        class="h-12 rounded-xl border-slate-200 bg-slate-50/30 px-4 text-sm text-slate-900 placeholder:text-slate-300 focus:border-primary/50 focus:bg-white transition-all outline-none"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="flex items-center">
                    <Button
                        type="submit"
                        class="h-12 w-full rounded-2xl bg-primary text-sm font-bold text-white shadow-xl shadow-primary/20 transition-all duration-300 hover:bg-primary-dark hover:shadow-2xl hover:shadow-primary/30 active:scale-[0.98] disabled:opacity-50"
                        :disabled="processing"
                        data-test="confirm-password-button"
                    >
                        <Spinner v-if="processing" class="mr-2 size-4" />
                        {{ processing ? 'Autentikasi...' : 'Confirm Identity' }}
                    </Button>
                </div>
            </div>
        </Form>
    </AuthLayout>
</template>
