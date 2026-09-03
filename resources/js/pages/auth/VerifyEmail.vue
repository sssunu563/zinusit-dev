<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { logout } from '@/routes';
// import { send } from '@/routes/verification';
const send: any = { form: () => ({}) };

defineProps<{
    status?: string;
}>();
</script>

<template>
    <AuthLayout
        title="Check your Inbox"
        description="Verify your email address to activate your enterprise access."
    >
        <Head title="Verify email" />

        <div
            v-if="status === 'verification-link-sent'"
            class="mb-6 rounded-2xl border border-emerald-100 bg-emerald-50 px-6 py-4 text-center text-sm font-bold text-emerald-600"
        >
            A fresh verification link has been transmitted to your registered email address.
        </div>

        <Form
            :action="route('verification.send')"
            method="post"
            class="space-y-8 text-center"
            v-slot="{ processing }"
        >
            <Button 
                :disabled="processing" 
                class="h-12 w-full rounded-2xl bg-primary text-sm font-bold text-white shadow-xl shadow-primary/20 transition-all duration-300 hover:bg-primary-dark hover:shadow-2xl hover:shadow-primary/30 active:scale-[0.98] disabled:opacity-50"
            >
                <Spinner v-if="processing" class="mr-2 size-4" />
                Resend Verification Identity
            </Button>

            <button
                @click="router.post(logout())"
                type="button"
                class="mx-auto block text-[13px] font-black tracking-widest text-slate-400 hover:text-primary transition-colors uppercase"
            >
                Keluar / Logout
            </button>
        </Form>
    </AuthLayout>
</template>
