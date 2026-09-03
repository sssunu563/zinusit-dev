<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
// import { update } from '@/routes/password';
const update: any = { form: () => ({}) };

const props = defineProps<{
    token: string;
    email: string;
}>();

const inputEmail = ref(props.email);
</script>

<template>
    <AuthLayout
        title="Atur password baru"
        description="Masukkan password baru untuk melanjutkan akses ke sistem."
    >
        <Head title="Password baru" />

        <Form
            v-bind="update.form()"
            :transform="(data) => ({ ...data, token, email })"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="space-y-8"
        >
            <div class="grid gap-6">
                <!-- Email Display -->
                <div class="space-y-3">
                    <Label for="email" class="text-[10px] font-black tracking-widest text-slate-400 uppercase ml-1">Identity Context</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        autocomplete="email"
                        v-model="inputEmail"
                        class="h-12 rounded-xl border-slate-100 bg-slate-50 px-4 text-xs font-bold text-slate-500 cursor-not-allowed italic"
                        readonly
                    />
                    <InputError :message="errors.email" />
                </div>

                <!-- Password -->
                <div class="space-y-3">
                    <Label for="password" class="text-[10px] font-black tracking-widest text-slate-400 uppercase ml-1">New Access Passcode</Label>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        autocomplete="new-password"
                        autofocus
                        class="h-12 rounded-xl border-slate-200 bg-slate-50/30 px-4 text-sm text-slate-900 placeholder:text-slate-300 focus:border-primary/50 focus:bg-white transition-all outline-none"
                    />
                    <InputError :message="errors.password" />
                </div>

                <!-- Password Confirmation -->
                <div class="space-y-3">
                    <Label for="password_confirmation" class="text-[10px] font-black tracking-widest text-slate-400 uppercase ml-1">
                        Verify New Passcode
                    </Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        autocomplete="new-password"
                        class="h-12 rounded-xl border-slate-200 bg-slate-50/30 px-4 text-sm text-slate-900 placeholder:text-slate-300 focus:border-primary/50 focus:bg-white transition-all outline-none"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <Button
                    type="submit"
                    class="mt-4 h-12 w-full rounded-2xl bg-primary text-sm font-bold text-white shadow-xl shadow-primary/20 transition-all duration-300 hover:bg-primary-dark hover:shadow-2xl hover:shadow-primary/30 active:scale-[0.98] disabled:opacity-50"
                    :disabled="processing"
                    data-test="reset-password-button"
                >
                    <Spinner v-if="processing" class="mr-2 size-4" />
                    {{ processing ? 'Updating Identity...' : 'Reinstate Access' }}
                </Button>
            </div>
        </Form>
    </AuthLayout>
</template>
