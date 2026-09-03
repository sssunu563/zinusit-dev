<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
// import { email } from '@/routes/password';
const email: any = { form: () => ({}) };

defineProps<{
    status?: string;
}>();
</script>

<template>
    <AuthLayout
        title="Reset password"
        description="Masukkan email akun Anda untuk menerima tautan reset password."
    >
        <Head title="Reset password" />

        <div
            v-if="status"
            class="mb-6 rounded-2xl border border-emerald-100 bg-emerald-50 px-4 py-4 text-center text-sm font-bold text-emerald-600"
        >
            {{ status }}
        </div>

        <div class="space-y-8">
            <Form v-bind="email.form()" v-slot="{ errors, processing }" class="space-y-6">
                <div class="space-y-3">
                    <Label for="email" class="text-[10px] font-black tracking-widest text-slate-400 uppercase ml-1">
                        Recovery Enterprise Email
                    </Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        autocomplete="off"
                        autofocus
                        placeholder="name@company.com"
                        class="h-12 rounded-xl border-slate-200 bg-slate-50/30 px-4 text-sm text-slate-900 placeholder:text-slate-300 focus:border-primary/50 focus:bg-white transition-all outline-none"
                    />
                    <InputError :message="errors.email" />
                </div>

                <Button
                    class="h-12 w-full rounded-2xl bg-primary text-sm font-bold text-white shadow-xl shadow-primary/20 transition-all duration-300 hover:bg-primary-dark hover:shadow-2xl hover:shadow-primary/30 active:scale-[0.98] disabled:opacity-50"
                    :disabled="processing"
                    data-test="email-password-reset-link-button"
                >
                    <Spinner v-if="processing" class="mr-2 size-4" />
                    {{ processing ? 'Transmitting Link...' : 'Send Recovery Link' }}
                </Button>
            </Form>

            <div class="text-center text-[13px] text-slate-400">
                Facing issues? Contact 
                <TextLink :href="login()" class="font-black text-primary hover:text-primary-dark transition-colors">Infrastructure Support</TextLink>
                <br />
                <div class="mt-4">
                    <TextLink :href="login()" class="text-slate-400 font-bold hover:text-slate-600 transition-all">Back to Login</TextLink>
                </div>
            </div>
        </div>
    </AuthLayout>
</template>
