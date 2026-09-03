<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
// import { store } from '@/routes/register';
const store: any = { form: () => ({}) };
</script>

<template>
    <AuthBase
        title="Buat akun baru"
        description="Lengkapi data user untuk menambahkan akses baru ke workspace internal."
    >
        <Head title="Daftar" />

        <Form
            :action="route('register')"
            method="post"
            class="flex flex-col gap-8"
            v-slot="{ errors, processing }"
        >
            <div
                class="rounded-[24px] border border-slate-100 bg-slate-50/50 p-6 shadow-inner"
            >
                <div class="flex items-center gap-2 mb-2">
                    <div class="h-1.5 w-1.5 rounded-full bg-primary animate-pulse" />
                    <p class="text-[10px] font-black tracking-[0.2em] text-primary uppercase">Workspace Access</p>
                </div>
                <p class="text-[12px] font-medium leading-relaxed text-slate-500">
                    Complete your profile to gain operational access to the Zinus IT internal modules.
                </p>
            </div>

            <div class="grid gap-6">
                <!-- Nama -->
                <div class="space-y-2">
                    <Label for="name" class="text-[10px] font-black tracking-widest text-slate-400 uppercase ml-1">
                        Full Identity Name
                    </Label>
                    <Input
                        id="name"
                        type="text"
                        name="name"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="name"
                        placeholder="John Doe"
                        class="h-12 rounded-xl border-slate-200 bg-slate-50/30 px-4 text-sm text-slate-900 placeholder:text-slate-300 focus:border-primary/50 focus:bg-white transition-all outline-none"
                    />
                    <InputError :message="errors.name" />
                </div>

                <!-- Email -->
                <div class="space-y-2">
                    <Label for="email" class="text-[10px] font-black tracking-widest text-slate-400 uppercase ml-1">
                        Enterprise Email
                    </Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        required
                        :tabindex="2"
                        autocomplete="email"
                        placeholder="name@company.com"
                        class="h-12 rounded-xl border-slate-200 bg-slate-50/30 px-4 text-sm text-slate-900 placeholder:text-slate-300 focus:border-primary/50 focus:bg-white transition-all outline-none"
                    />
                    <InputError :message="errors.email" />
                </div>

                <!-- Password Row -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <Label for="password" class="text-[10px] font-black tracking-widest text-slate-400 uppercase ml-1">
                            New Passcode
                        </Label>
                        <Input
                            id="password"
                            type="password"
                            name="password"
                            required
                            :tabindex="3"
                            autocomplete="new-password"
                            class="h-12 rounded-xl border-slate-200 bg-slate-50/30 px-4 text-sm text-slate-900 placeholder:text-slate-300 focus:border-primary/50 focus:bg-white transition-all outline-none"
                        />
                        <InputError :message="errors.password" />
                    </div>

                    <div class="space-y-2">
                        <Label for="password_confirmation" class="text-[10px] font-black tracking-widest text-slate-400 uppercase ml-1">
                            Verify Passcode
                        </Label>
                        <Input
                            id="password_confirmation"
                            type="password"
                            name="password_confirmation"
                            required
                            :tabindex="4"
                            autocomplete="new-password"
                            class="h-12 rounded-xl border-slate-200 bg-slate-50/30 px-4 text-sm text-slate-900 placeholder:text-slate-300 focus:border-primary/50 focus:bg-white transition-all outline-none"
                        />
                    </div>
                </div>

                <Button
                    type="submit"
                    class="mt-4 h-12 w-full rounded-2xl bg-primary text-sm font-bold text-white shadow-xl shadow-primary/20 transition-all duration-300 hover:bg-primary-dark hover:shadow-2xl hover:shadow-primary/30 active:scale-[0.98] disabled:opacity-50"
                    :tabindex="5"
                    :disabled="processing"
                    data-test="register-user-button"
                >
                    <Spinner v-if="processing" class="mr-2 size-4" />
                    {{ processing ? 'Creating Identity...' : 'Initialize Access' }}
                </Button>
            </div>

            <p class="text-center text-[13px] text-slate-400">
                Already registered?
                <TextLink
                    :href="login()"
                    class="font-black text-primary hover:text-primary-dark transition-colors"
                    :tabindex="6"
                >
                    Masuk Sekarang
                </TextLink>
            </p>

            <div class="flex justify-center gap-4 opacity-40 grayscale hover:grayscale-0 transition-all duration-500">
                <span class="text-[9px] font-black tracking-[0.2em] text-slate-400 uppercase border border-slate-100 px-3 py-1 rounded-full">Secure Stack</span>
                <span class="text-[9px] font-black tracking-[0.2em] text-slate-400 uppercase border border-slate-100 px-3 py-1 rounded-full">Internal Only</span>
            </div>
        </Form>
    </AuthBase>
</template>
