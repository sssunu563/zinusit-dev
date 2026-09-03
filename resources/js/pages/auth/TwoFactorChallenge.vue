<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { store } from '@/routes/two-factor/login';
import type { TwoFactorConfigContent } from '@/types';

const authConfigContent = computed<TwoFactorConfigContent>(() => {
    if (showRecoveryInput.value) {
        return {
            title: 'Kode recovery',
            description:
                'Masukkan salah satu kode pemulihan darurat untuk menyelesaikan proses login.',
            buttonText: 'masuk dengan kode autentikasi',
        };
    }

    return {
        title: 'Kode autentikasi',
        description:
            'Masukkan kode dari aplikasi authenticator yang terhubung ke akun Anda.',
        buttonText: 'masuk dengan kode recovery',
    };
});

const showRecoveryInput = ref<boolean>(false);

const toggleRecoveryMode = (clearErrors: () => void): void => {
    showRecoveryInput.value = !showRecoveryInput.value;
    clearErrors();
    code.value = '';
};

const code = ref<string>('');
</script>

<template>
    <AuthLayout
        :title="authConfigContent.title"
        :description="authConfigContent.description"
    >
        <Head title="Verifikasi 2 langkah" />

        <div class="space-y-8">
            <template v-if="!showRecoveryInput">
                <Form
                    v-bind="store.form()"
                    class="space-y-8"
                    reset-on-error
                    @error="code = ''"
                    #default="{ errors, processing, clearErrors }"
                >
                    <input type="hidden" name="code" :value="code" />
                    <div
                        class="flex flex-col items-center justify-center space-y-6 text-center"
                    >
                        <div class="flex w-full items-center justify-center">
                            <InputOTP
                                id="otp"
                                v-model="code"
                                :maxlength="6"
                                :disabled="processing"
                                autofocus
                            >
                                <InputOTPGroup class="gap-3">
                                    <InputOTPSlot
                                        v-for="index in 6"
                                        :key="index"
                                        :index="index - 1"
                                        class="h-12 w-10 md:h-16 md:w-14 rounded-2xl border-slate-200 bg-slate-50/50 text-xl font-black text-slate-900 focus-visible:ring-primary/40 focus-visible:border-primary/50"
                                    />
                                </InputOTPGroup>
                            </InputOTP>
                        </div>
                        <InputError :message="errors.code" />
                    </div>

                    <Button 
                        type="submit" 
                        class="h-12 w-full rounded-2xl bg-primary text-sm font-bold text-white shadow-xl shadow-primary/20 transition-all duration-300 hover:bg-primary-dark hover:shadow-2xl hover:shadow-primary/30 active:scale-[0.98] disabled:opacity-50" 
                        :disabled="processing || code.length < 6"
                    >
                        <Spinner v-if="processing" class="mr-2 size-4" />
                        {{ processing ? 'Autentikasi...' : 'Verify Access Code' }}
                    </Button>

                    <div class="text-center text-[13px] text-slate-400">
                        <span>Lost Access? </span>
                        <button
                            type="button"
                            class="font-black text-primary hover:text-primary-dark transition-colors"
                            @click="() => toggleRecoveryMode(clearErrors)"
                        >
                            {{ authConfigContent.buttonText }}
                        </button>
                    </div>
                </Form>
            </template>

            <template v-else>
                <Form
                    v-bind="store.form()"
                    class="space-y-8"
                    reset-on-error
                    #default="{ errors, processing, clearErrors }"
                >
                    <div class="space-y-3">
                        <Label class="text-[10px] font-black tracking-widest text-slate-400 uppercase ml-1">Recovery Access Code</Label>
                        <Input
                            name="recovery_code"
                            type="text"
                            placeholder="e.g. 12345-abcde"
                            :autofocus="showRecoveryInput"
                            required
                            class="h-12 rounded-xl border-slate-200 bg-slate-50/30 px-4 text-sm text-slate-900 placeholder:text-slate-300 focus:border-primary/50 focus:bg-white transition-all outline-none"
                        />
                        <InputError :message="errors.recovery_code" />
                    </div>

                    <Button 
                        type="submit" 
                        class="h-12 w-full rounded-2xl bg-primary text-sm font-bold text-white shadow-xl shadow-primary/20 transition-all duration-300 hover:bg-primary-dark hover:shadow-2xl hover:shadow-primary/30 active:scale-[0.98] disabled:opacity-50" 
                        :disabled="processing"
                    >
                        <Spinner v-if="processing" class="mr-2 size-4" />
                        {{ processing ? 'Authorizing...' : 'Reinstate Access' }}
                    </Button>

                    <div class="text-center text-[13px] text-slate-400">
                        <span>Back to App? </span>
                        <button
                            type="button"
                            class="font-black text-primary hover:text-primary-dark transition-colors"
                            @click="() => toggleRecoveryMode(clearErrors)"
                        >
                            {{ authConfigContent.buttonText }}
                        </button>
                    </div>
                </Form>
            </template>
        </div>
    </AuthLayout>
</template>
