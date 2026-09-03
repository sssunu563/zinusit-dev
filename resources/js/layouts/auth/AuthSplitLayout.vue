<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

interface Props {
    title?: string;
    description?: string;
}

defineProps<Props>();
</script>

<template>
    <div class="relative min-h-screen flex flex-col items-center justify-center bg-background md:grid lg:max-w-none lg:grid-cols-2 lg:px-0">
        <div class="relative hidden h-full flex-col bg-muted p-10 text-white lg:flex dark:border-r overflow-hidden">
            <div class="absolute inset-0 bg-primary/95" />
            <div class="absolute inset-0 bg-[url('https://images.unsplash.com/photo-1550751827-4bd374c3f58b?q=80&w=2070&auto=format&fit=crop')] bg-cover bg-center mix-blend-overlay opacity-40 shadow-inner" />

            <div class="relative z-20 flex items-center text-lg font-bold">
                <Link href="/" class="flex items-center gap-2">
                    <AppLogoIcon class="size-8 text-secondary-foreground" />
                    <span class="tracking-tight text-white drop-shadow-md">ZinusIT</span>
                </Link>
            </div>

            <div class="relative z-20 mt-auto">
                <blockquote class="space-y-2">
                    <p class="text-lg font-medium leading-relaxed italic drop-shadow-sm">
                        "Your technology infrastructure, managed with precision and elegance."
                    </p>
                    <footer class="text-sm font-semibold opacity-80 uppercase tracking-widest">— ZinusIT Operations</footer>
                </blockquote>
            </div>
        </div>

        <div class="p-4 lg:p-8 w-full max-w-[440px] mx-auto">
            <div class="mx-auto flex w-full flex-col justify-center space-y-6">
                <div class="flex flex-col space-y-2 text-center lg:text-left">
                    <h1 class="text-2xl font-bold tracking-tight text-foreground" v-if="title">
                        {{ title }}
                    </h1>
                    <p class="text-sm text-muted-foreground" v-if="description">
                        {{ description }}
                    </p>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
