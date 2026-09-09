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
                    <Label
                        for="email"
                        class="ml-1 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                        >Identitas Akun</Label
                    >
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        autocomplete="email"
                        v-model="inputEmail"
                        class="h-12 cursor-not-allowed rounded-xl border-slate-100 bg-slate-50 px-4 text-xs font-bold text-slate-500 italic"
                        readonly
                    />
                    <InputError :message="errors.email" />
                </div>

                <!-- Password -->
                <div class="space-y-3">
                    <Label
                        for="password"
                        class="ml-1 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                        >Kata Sandi Baru</Label
                    >
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        autocomplete="new-password"
                        autofocus
                        class="h-12 rounded-xl border-slate-200 bg-slate-50/30 px-4 text-sm text-slate-900 transition-all outline-none placeholder:text-slate-300 focus:border-primary/50 focus:bg-white"
                    />
                    <InputError :message="errors.password" />
                </div>

                <!-- Password Confirmation -->
                <div class="space-y-3">
                    <Label
                        for="password_confirmation"
                        class="ml-1 text-[10px] font-black tracking-widest text-slate-400 uppercase"
                    >
                        Verify New Passcode
                    </Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        autocomplete="new-password"
                        class="h-12 rounded-xl border-slate-200 bg-slate-50/30 px-4 text-sm text-slate-900 transition-all outline-none placeholder:text-slate-300 focus:border-primary/50 focus:bg-white"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <Button
                    type="submit"
                    class="hover:bg-primary-dark mt-4 h-12 w-full rounded-2xl bg-primary text-sm font-bold text-white shadow-xl shadow-primary/20 transition-all duration-300 hover:shadow-2xl hover:shadow-primary/30 active:scale-[0.98] disabled:opacity-50"
                    :disabled="processing"
                    data-test="reset-password-button"
                >
                    <Spinner v-if="processing" class="mr-2 size-4" />
                    {{
                        processing ? 'Updating Identity...' : 'Reinstate Access'
                    }}
                </Button>
            </div>
        </Form>
    </AuthLayout>
</template>
