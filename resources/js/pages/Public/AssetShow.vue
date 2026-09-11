<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, ExternalLink, ShieldCheck } from 'lucide-vue-next';
import AssetDetailCard, { type AssetDetail } from './Partials/AssetDetailCard.vue';

const props = defineProps<{
    asset: AssetDetail;
}>();

const handleViewUserAssets = (email: string) => {
    router.visit(`/check-assets?email=${encodeURIComponent(email)}`);
};
</script>

<template>
    <div class="min-h-screen bg-[#F8FAFC] text-slate-900 flex flex-col antialiased selection:bg-[#003628]/20 selection:text-[#003628]">
        <Head :title="`Asset: ${asset.asset_tag} - ${asset.name}`" />

        <!-- Header Navbar -->
        <header class="bg-white/90 backdrop-blur-md border-b border-slate-200/80 sticky top-0 z-40 shadow-xs">
            <div class="max-w-xl mx-auto px-4 h-14 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <img src="/form-logo.png" alt="ZinusIT" class="h-6 w-auto object-contain" />
                    <div class="h-4 w-[1px] bg-slate-200" />
                    <div class="flex items-center gap-1.5 text-slate-500 text-xs font-bold tracking-tight">
                        <ShieldCheck class="w-4 h-4 text-[#003628]" />
                        <span class="text-[11px] font-black uppercase tracking-wider text-[#003628]">IT Asset Registry</span>
                    </div>
                </div>

                <a
                    :href="`/check-assets?tag=${encodeURIComponent(asset.asset_tag || asset.serial)}`"
                    class="flex items-center gap-1 text-xs font-bold text-[#003628] hover:underline"
                >
                    <span>Portal Cek Aset</span>
                    <ExternalLink class="size-3.5" />
                </a>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1 max-w-xl mx-auto w-full px-4 py-6 pb-16">
            <div class="mb-4">
                <a
                    href="/check-assets"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-800 transition-colors"
                >
                    <ArrowLeft class="size-3.5" />
                    <span>Kembali ke Portal Cek Aset</span>
                </a>
            </div>

            <AssetDetailCard
                :asset="asset"
                @view-user-assets="handleViewUserAssets"
            />
        </main>

        <!-- Footer -->
        <footer class="py-6 border-t border-slate-200/60 bg-white text-center text-slate-400 text-[10px] font-bold uppercase tracking-widest">
            <p>&copy; {{ new Date().getFullYear() }} PT Zinus Global Indonesia &bull; IT Asset Management</p>
        </footer>
    </div>
</template>
