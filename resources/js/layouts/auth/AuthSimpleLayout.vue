<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes/index';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="relative flex min-h-svh flex-col items-center justify-center bg-[#f8fafc] px-4 py-12 selection:bg-primary/20 selection:text-primary overflow-hidden">
        
        <!-- Clean Solid Background -->
        <div class="absolute inset-0 pointer-events-none overflow-hidden bg-[#f8fafc]">
        </div>

        <!-- Auth Card -->
        <div class="relative w-full max-w-[440px] z-10 transition-all duration-700 animate-in fade-in slide-in-from-bottom-4">
            <div class="rounded-[40px] border border-border bg-white/90 backdrop-blur-2xl px-10 py-12 shadow-[0_40px_80px_-20px_rgba(0,0,0,0.08)]">
                <!-- Header inside Card -->
                <div class="mb-12 flex flex-col items-center gap-4 group">
                    <Link :href="home()" class="flex flex-col items-center gap-4">
                        <AppLogoIcon class="size-20 text-primary transition-all duration-500 group-hover:scale-110 group-hover:rotate-6" />
                        <span class="text-sm font-black tracking-[0.5em] text-foreground uppercase ml-[0.5em]">Zinus IT</span>
                    </Link>
                </div>

                <!-- Form Content -->
                <slot />
            </div>

            <!-- Global Footer -->
            <div class="mt-12 text-center">
                <p class="text-[10px] font-black text-muted-foreground uppercase tracking-widest opacity-30">
                    &copy; {{ new Date().getFullYear() }} Zinus IT Indonesia
                </p>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Hidden scrollbar but functional */
::-webkit-scrollbar {
  width: 0px;
  background: transparent;
}
</style>
