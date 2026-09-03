<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { AlertCircle, CheckCircle2, Info, X } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

type FlashType = 'success' | 'error' | 'info';
type FlashPayload = { success?: unknown; error?: unknown; info?: unknown };

const page = usePage();
const isVisible = ref(false);
const alertType = ref<FlashType>('success');
const message = ref('');
let hideTimer: ReturnType<typeof setTimeout> | null = null;

const flashMessage = computed<FlashPayload>(
    () => (page.props?.flash as FlashPayload | undefined) || {},
);

const iconMap: Record<FlashType, typeof CheckCircle2> = {
    success: CheckCircle2,
    error: AlertCircle,
    info: Info,
};

const styleMap: Record<FlashType, { wrap: string; icon: string; bar: string }> = {
    success: {
        wrap: 'border-primary/20 bg-card/90 backdrop-blur-xl shadow-primary/10',
        icon: 'text-primary',
        bar: 'bg-primary',
    },
    error: {
        wrap: 'border-rose-500/20 bg-card/90 backdrop-blur-xl shadow-rose-500/10',
        icon: 'text-rose-500',
        bar: 'bg-rose-500',
    },
    info: {
        wrap: 'border-sky-500/20 bg-card/90 backdrop-blur-xl shadow-sky-500/10',
        icon: 'text-sky-500',
        bar: 'bg-sky-500',
    },
};

const show = (type: FlashType, text: string) => {
    if (hideTimer) clearTimeout(hideTimer);
    alertType.value = type;
    message.value = text;
    isVisible.value = true;
    hideTimer = setTimeout(() => { isVisible.value = false; }, 4500);
};

const dismiss = () => {
    if (hideTimer) clearTimeout(hideTimer);
    isVisible.value = false;
};

const lastFlashId = ref<string | null>(null);

const process = () => {
    const flash = page.props?.flash as FlashPayload & { id?: string } | undefined;
    if (!flash) return;

    // ANTI-GHOSTING FILTER:
    // If the flash ID is exactly the same as the last one we showed,
    // it means this is a cached page being restored via the Back/Discard button. Ignore it!
    if (flash.id && flash.id === lastFlashId.value) return;
    
    if (flash.id) {
        lastFlashId.value = flash.id;
    }

    if (flash.success) {
        show('success', String(flash.success));
        flash.success = null;
    } else if (flash.error) {
        show('error', String(flash.error));
        flash.error = null;
    } else if (flash.info) {
        show('info', String(flash.info));
        flash.info = null;
    }
};

onMounted(() => {
    process();
    // Support for global event triggering
    window.addEventListener('flash-message', (e: any) => {
        if (e.detail?.type && e.detail?.message) {
            show(e.detail.type, e.detail.message);
        }
    });
});

watch(flashMessage, process);
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0 translate-y-3 scale-95"
            enter-to-class="opacity-100 translate-y-0 scale-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100 translate-y-0 scale-100"
            leave-to-class="opacity-0 translate-y-3 scale-95"
        >
            <div
                v-if="isVisible"
                class="fixed right-4 bottom-4 z-[999] flex w-full max-w-[340px] flex-col overflow-hidden rounded-2xl border shadow-xl"
                :class="styleMap[alertType].wrap"
                role="alert"
            >
                <!-- Progress bar (auto-dismiss timer) -->
                <div class="h-0.5 w-full bg-transparent">
                    <div
                        class="h-full animate-[shrink_4.5s_linear_forwards] rounded-full"
                        :class="styleMap[alertType].bar"
                    />
                </div>

                <!-- Content -->
                <div class="flex items-start gap-3 p-4">
                    <div class="mt-0.5 shrink-0">
                        <component
                            :is="iconMap[alertType]"
                            class="h-5 w-5"
                            :class="styleMap[alertType].icon"
                        />
                    </div>
                    <p class="flex-1 text-sm font-medium leading-snug text-foreground">
                        {{ message }}
                    </p>
                    <button
                        type="button"
                        class="ml-auto shrink-0 rounded-lg p-0.5 text-muted-foreground transition-colors hover:bg-muted"
                        @click="dismiss"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style>
@keyframes shrink {
    from { width: 100%; }
    to   { width: 0%; }
}
</style>
