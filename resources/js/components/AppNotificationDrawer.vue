<script setup>
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { Bell, Check, Trash2, ExternalLink, X, Info, AlertTriangle, CheckCircle2, AlertCircle, Loader2 } from 'lucide-vue-next';
import { ref, onMounted, onUnmounted } from 'vue';
import notificationsRoutes from '@/routes/notifications';

const isOpen = ref(false);
const notifications = ref([]);
const unreadCount = ref(0);
const isLoading = ref(false);

const toggleDrawer = () => {
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        fetchNotifications();
    }
};

const fetchNotifications = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get(notificationsRoutes.index.url());
        notifications.value = response.data.notifications;
        unreadCount.value = response.data.unreadCount;
    } catch (error) {
        console.error('Failed to fetch notifications:', error);
    } finally {
        isLoading.value = false;
    }
};

const markAsRead = async (notification) => {
    if (notification.read_at) return;
    
    try {
        await axios.post(notificationsRoutes.read.url(notification.id));
        notification.read_at = new Date().toISOString();
        unreadCount.value = Math.max(0, unreadCount.value - 1);
    } catch (error) {
        console.error('Failed to mark as read:', error);
    }
};

const markAllAsRead = async () => {
    try {
        await axios.post(notificationsRoutes.markAllRead.url());
        notifications.value.forEach(n => n.read_at = new Date().toISOString());
        unreadCount.value = 0;
    } catch (error) {
        console.error('Failed to mark all as read:', error);
    }
};

const getIcon = (tone) => {
    switch (tone) {
        case 'success': return CheckCircle2;
        case 'warning': return AlertTriangle;
        case 'danger': return AlertCircle;
        default: return Info;
    }
};

const getToneColor = (tone) => {
    switch (tone) {
        case 'success': return 'text-emerald-500 bg-emerald-50';
        case 'warning': return 'text-amber-500 bg-amber-50';
        case 'danger': return 'text-rose-500 bg-rose-50';
        default: return 'text-sky-500 bg-sky-50';
    }
};

onMounted(() => {
    fetchNotifications();
    // Poll every 60 seconds
    const interval = setInterval(fetchNotifications, 60000);
    onUnmounted(() => clearInterval(interval));
});

defineExpose({ toggleDrawer });
</script>

<template>
    <div v-if="isOpen" class="fixed inset-0 z-[110] overflow-hidden">
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-[2px]" @click="isOpen = false"></div>
        
        <div class="absolute inset-y-0 right-0 max-w-full flex">
            <div class="w-screen max-w-sm bg-white shadow-2xl flex flex-col transform transition-transform animate-in slide-in-from-right duration-300">
                <!-- Header -->
                <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-slate-900">Notifikasi</h2>
                        <p class="text-xs text-slate-500 font-medium mt-1">Anda memiliki {{ unreadCount }} pesan baru</p>
                    </div>
                    <button @click="isOpen = false" class="p-2 rounded-xl hover:bg-slate-100 text-slate-400 transition-colors">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Actions -->
                <div v-if="notifications.length > 0" class="px-6 py-3 bg-slate-50/50 border-b border-slate-100 flex items-center justify-between">
                    <button 
                        @click="markAllAsRead"
                        class="text-[10px] font-bold text-[#003628] uppercase tracking-widest hover:underline"
                    >
                        Tandai Semua Dibaca
                    </button>
                </div>

                <!-- List -->
                <div class="flex-1 overflow-y-auto">
                    <div v-if="isLoading && notifications.length === 0" class="flex flex-col items-center justify-center h-full text-slate-400">
                        <Loader2 class="w-10 h-10 animate-spin mb-4" />
                        <p class="text-sm">Memuat data...</p>
                    </div>

                    <div v-else-if="notifications.length > 0" class="divide-y divide-slate-50">
                        <div 
                            v-for="notif in notifications" 
                            :key="notif.id"
                            class="p-5 hover:bg-slate-50 transition-colors cursor-pointer group relative"
                            :class="[!notif.read_at ? 'bg-[#003628]/[0.02]' : '']"
                            @click="markAsRead(notif)"
                        >
                            <div class="flex gap-4">
                                <div :class="[getToneColor(notif.tone), 'w-10 h-10 rounded-2xl flex items-center justify-center shrink-0']">
                                    <component :is="getIcon(notif.tone)" class="w-5 h-5" />
                                </div>

                                <div class="flex-1 min-w-0">
                                    <div class="flex items-start justify-between mb-1">
                                        <h4 class="text-sm font-bold text-slate-800 line-clamp-1 pr-4">{{ notif.title }}</h4>
                                        <div v-if="!notif.read_at" class="w-2 h-2 rounded-full bg-[#003628] shrink-0 mt-1.5"></div>
                                    </div>
                                    <p class="text-xs text-slate-500 font-medium leading-relaxed mb-3">{{ notif.message }}</p>
                                    
                                    <div class="flex items-center justify-between">
                                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">
                                            {{ new Date(notif.created_at).toLocaleDateString('id-ID', { hour: '2-digit', minute: '2-digit' }) }}
                                        </span>

                                        <Link 
                                            v-if="notif.link"
                                            :href="notif.link"
                                            class="text-[10px] font-bold text-[#d99528] uppercase tracking-widest flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity"
                                        >
                                            Buka <ExternalLink class="w-3 h-3" />
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-else class="flex flex-col items-center justify-center h-full p-12 text-center">
                        <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mb-6">
                            <Bell class="w-10 h-10 text-slate-200" />
                        </div>
                        <h3 class="text-lg font-bold text-slate-800">Semua Beres!</h3>
                        <p class="mt-2 text-sm text-slate-500 font-medium leading-relaxed">
                            Tidak ada notifikasi baru untuk Anda saat ini. Kami akan memberi tahu jika ada pembaruan penting.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Trigger Button (can be styled externally too) -->
    <button 
        @click="toggleDrawer"
        class="relative p-2 rounded-xl text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-all"
    >
        <Bell class="w-5 h-5" />
        <span 
            v-if="unreadCount > 0"
            class="absolute top-1 right-1 w-4 h-4 bg-rose-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center border-2 border-white"
        >
            {{ unreadCount > 9 ? '9+' : unreadCount }}
        </span>
    </button>
</template>
