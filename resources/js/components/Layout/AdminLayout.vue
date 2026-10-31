<template>
    <div class="admin-layout">
        <Navbar :user="authStore.user" />
        
        <div class="layout-container">
            <aside class="sidebar">
                <nav class="sidebar-nav">
                    <router-link 
        to="/admin/dashboard" 
        class="nav-item"
        :class="{ active: $route.name === 'admin.dashboard' }"
    >
        📊 داشبورد
                    </router-link>
                    <router-link 
                        to="/admin/customers" 
                        class="nav-item"
                        :class="{ active: $route.name === 'admin.customers' }"
                    >
                        👨‍👩‍👧‍👦 مدیریت مشتریان
                    </router-link>
                    <router-link 
                        to="/admin/categories" 
                        class="nav-item"
                        :class="{ active: $route.name === 'admin.categories' }"
                    >
                        🗂️ مدیریت دسته‌بندی‌ها
                    </router-link>
                    <router-link 
                        to="/admin/products" 
                        class="nav-item"
                        :class="{ active: $route.name === 'admin.products' }"
                    >
                        📦 مدیریت محصولات
                    </router-link>
                    <router-link 
                        to="/admin/discounts" 
                        class="nav-item"
                        :class="{ active: $route.name === 'admin.discounts' }"
                    >
                        🎫 مدیریت تخفیف‌ها
                    </router-link>
                    <router-link
                        to="/admin/sms-campaigns"
                        class="nav-item"
                        :class="{ active: $route.name === 'admin.sms-campaigns' }"
                    >
                        📣 پیامک گروهی
                    </router-link>
                    <router-link
                        to="/admin/cash-shifts"
                        class="nav-item"
                        :class="{ active: $route.name === 'admin.cash-shifts' }"
                    >
                        🧾 صندوق و مغایرت
                    </router-link>
                    <router-link
                        to="/admin/purchase-orders"
                        class="nav-item"
                        :class="{ active: $route.name === 'admin.purchase-orders' }"
                    >
                        🚚 خرید و تأمین‌کنندگان
                    </router-link>
                    <router-link
                        to="/admin/branches"
                        class="nav-item"
                        :class="{ active: $route.name === 'admin.branches' }"
                    >
                        🏬 شعبه‌ها
                    </router-link>
                    <router-link
                        to="/admin/stock-transfers"
                        class="nav-item"
                        :class="{ active: $route.name === 'admin.stock-transfers' }"
                    >
                        🔁 انتقال موجودی
                    </router-link>
                    <router-link
                        to="/admin/gift-cards"
                        class="nav-item"
                        :class="{ active: $route.name === 'admin.gift-cards' }"
                    >
                        🎁 گیفت کارت
                    </router-link>
                    <router-link
                        to="/admin/barcode-labels"
                        class="nav-item"
                        :class="{ active: $route.name === 'admin.barcode-labels' }"
                    >
                        🏷️ چاپ برچسب
                    </router-link>
                    <router-link to="/admin/reports" class="nav-item">
                        📈 گزارشات
                    </router-link>
                    <router-link
                        to="/admin/audit-logs"
                        class="nav-item"
                        :class="{ active: $route.name === 'admin.audit-logs' }"
                    >
                        🕵️ گزارش فعالیت‌ها
                    </router-link>
                    <router-link
                        to="/admin/backups"
                        class="nav-item"
                        :class="{ active: $route.name === 'admin.backups' }"
                    >
                        💾 پشتیبان و بازیابی
                    </router-link>
                    <router-link to="/admin/users" class="nav-item">
                        👤 مدیریت کاربران
                    </router-link>
                </nav>
            </aside>

            <main class="main-content">
                <router-view />
            </main>
        </div>
    </div>
</template>

<script>
import { useAuthStore } from '../../stores/auth';
import Navbar from './Navbar.vue';

export default {
    name: 'AdminLayout',
    components: {
        Navbar
    },
    setup() {
        const authStore = useAuthStore();
        
        return {
            authStore
        };
    }
}
</script>

<style scoped>
.admin-layout {
    min-height: 100vh;
    background-color: #f8fafc;
}

.layout-container {
    display: flex;
    min-height: calc(100vh - 64px);
}

.sidebar {
    width: 250px;
    background: white;
    border-left: 1px solid #e2e8f0;
    padding: 20px 0;
}

.sidebar-nav {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 0 16px;
}

.nav-item {
    padding: 12px 16px;
    text-decoration: none;
    color: #4a5568;
    border-radius: 8px;
    transition: all 0.3s ease;
    font-weight: 500;
}

.nav-item:hover {
    background-color: #edf2f7;
    color: #2d3748;
}

.nav-item.active {
    background-color: #ebf8ff;
    color: #3182ce;
    border-right: 3px solid #3182ce;
}

.main-content {
    flex: 1;
    min-width: 0;
    padding: 24px;
    overflow-y: auto;
}
/* Mobile: stack the sidebar above the content instead of squeezing the page */
@media (max-width: 768px) {
    .layout-container {
        flex-direction: column;
    }

    .sidebar {
        width: 100%;
        border-left: none;
        border-bottom: 1px solid #e2e8f0;
        padding: 8px 0;
    }

    /* wrap instead of horizontal scroll: in RTL, overflow to the start edge cannot be scrolled to */
    .sidebar-nav {
        flex-direction: row;
        flex-wrap: wrap;
        padding: 0 8px;
        gap: 6px;
    }

    .nav-item {
        white-space: nowrap;
        padding: 8px 10px;
        font-size: 14px;
    }

    .main-content {
        padding: 16px 12px;
    }
}
</style>