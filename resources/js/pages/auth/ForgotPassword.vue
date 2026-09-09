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
            <Form
                v-bind="email.form()"
                v-slot="{ errors, processing }"
                class="space-y-6"
            >
                <div class="space-y-3">
                    <Label
                        for="email"
                        class="ml-1 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                    >
                        Recovery Enterprise Email
                    </Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        autocomplete="off"
                        autofocus
                        placeholder="name@company.com"
                        class="h-12 rounded-xl border-slate-200 bg-slate-50/30 px-4 text-sm text-slate-900 transition-all outline-none placeholder:text-slate-300 focus:border-primary/50 focus:bg-white"
                    />
                    <InputError :message="errors.email" />
                </div>

                <Button
                    class="hover:bg-primary-dark h-12 w-full rounded-2xl bg-primary text-sm font-bold text-white shadow-xl shadow-primary/20 transition-all duration-300 hover:shadow-2xl hover:shadow-primary/30 active:scale-[0.98] disabled:opacity-50"
                    :disabled="processing"
                    data-test="email-password-reset-link-button"
                >
                    <Spinner v-if="processing" class="mr-2 size-4" />
                    {{
                        processing
                            ? 'Transmitting Link...'
                            : 'Send Recovery Link'
                    }}
                </Button>
            </Form>

            <div class="text-center text-[13px] text-slate-400">
                Facing issues? Contact
                <TextLink
                    :href="login()"
                    class="hover:text-primary-dark font-black text-primary transition-colors"
                    >Dukungan Infrastruktur</TextLink
                >
                <br />
                <div class="mt-4">
                    <TextLink
                        :href="login()"
                        class="font-bold text-slate-400 transition-all hover:text-slate-600"
                        >Kembali ke Masuk</TextLink
                    >
                </div>
            </div>
        </div>
    </AuthLayout>
</template>
