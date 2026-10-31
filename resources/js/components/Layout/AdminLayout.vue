<template>
    <div class="app-shell">
        <AppSidebar role="admin" :groups="groups" :open="menuOpen" @close="menuOpen = false" />

        <div class="app-body">
            <Navbar :user="authStore.user" @toggle-menu="menuOpen = !menuOpen" />

            <main class="main-content">
                <router-view />
            </main>
        </div>

        <div v-if="menuOpen" class="scrim" aria-hidden="true" @click="menuOpen = false"></div>
    </div>
</template>

<script>
import { ref } from 'vue';
import { useAuthStore } from '../../stores/auth';
import { useRoute } from 'vue-router';
import Navbar from './Navbar.vue';
import AppSidebar from './AppSidebar.vue';
import {
    LayoutDashboard,
    Users,
    Megaphone,
    Gift,
    Percent,
    Layers,
    Package,
    Truck,
    ArrowLeftRight,
    Tags,
    Store,
    Receipt,
    ChartColumn,
    UserCog,
    ScrollText,
    DatabaseBackup
} from 'lucide-vue-next';

const groups = [
    {
        title: 'عمومی',
        items: [{ to: '/admin/dashboard', label: 'داشبورد', icon: LayoutDashboard }]
    },
    {
        title: 'فروش و مشتریان',
        items: [
            { to: '/admin/customers', label: 'مشتریان', icon: Users },
            { to: '/admin/discounts', label: 'تخفیف‌ها', icon: Percent },
            { to: '/admin/gift-cards', label: 'گیفت کارت', icon: Gift },
            { to: '/admin/sms-campaigns', label: 'پیامک گروهی', icon: Megaphone }
        ]
    },
    {
        title: 'کالا و انبار',
        items: [
            { to: '/admin/products', label: 'محصولات', icon: Package },
            { to: '/admin/categories', label: 'دسته‌بندی‌ها', icon: Layers },
            { to: '/admin/purchase-orders', label: 'خرید و تأمین‌کنندگان', icon: Truck },
            { to: '/admin/stock-transfers', label: 'انتقال موجودی', icon: ArrowLeftRight },
            { to: '/admin/barcode-labels', label: 'چاپ برچسب', icon: Tags },
            { to: '/admin/branches', label: 'شعبه‌ها', icon: Store }
        ]
    },
    {
        title: 'مالی و گزارش',
        items: [
            { to: '/admin/cash-shifts', label: 'صندوق و مغایرت', icon: Receipt },
            { to: '/admin/reports', label: 'گزارشات', icon: ChartColumn }
        ]
    },
    {
        title: 'سیستم',
        items: [
            { to: '/admin/users', label: 'کاربران', icon: UserCog },
            { to: '/admin/audit-logs', label: 'گزارش فعالیت‌ها', icon: ScrollText },
            { to: '/admin/backups', label: 'پشتیبان و بازیابی', icon: DatabaseBackup }
        ]
    }
];

export default {
    name: 'AdminLayout',
    components: {
        Navbar,
        AppSidebar
    },
    setup() {
        const authStore = useAuthStore();
        const route = useRoute();
        const menuOpen = ref(false);

        return {
            authStore,
            groups,
            menuOpen,
            route
        };
    }
};
</script>

<style scoped>
.app-shell {
    display: flex;
    min-height: 100vh;
    min-height: 100dvh;
    background: var(--bg);
}

.app-body {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
}

.main-content {
    flex: 1;
    width: 100%;
    max-width: 1280px;
    margin: 0 auto;
    padding: 24px 28px 48px;
    box-sizing: border-box;
}

.scrim {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.4);
    z-index: 55;
}

@media (min-width: 1024px) {
    .scrim {
        display: none;
    }
}

@media (max-width: 1023px) {
    .main-content {
        padding: 20px 18px 40px;
    }
}

@media (max-width: 768px) {
    .main-content {
        padding: 16px 12px 40px;
    }
}
</style>
