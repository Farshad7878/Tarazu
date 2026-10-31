<template>
    <div class="reception-layout">
        <Navbar :user="authStore.user" />
        
        <div class="layout-container">
            <aside class="sidebar">
                <nav class="sidebar-nav">
                    <router-link 
                        to="/reception/dashboard" 
                        class="nav-item"
                        :class="{ active: $route.name === 'reception.dashboard' }"
                    >
                        🏠 صفحه اصلی
                    </router-link>
                    <router-link to="/reception/new-order" class="nav-item">
                        ➕ ثبت سفارش جدید
                    </router-link>
                    <router-link to="/reception/shift" class="nav-item">
                        🧾 صندوق کاری
                    </router-link>
                    <router-link to="/reception/customers" class="nav-item">
                        👥 مشتریان
                    </router-link>
                    <router-link to="/reception/orders" class="nav-item">
                        📦 سفارشات من
                    </router-link>
                    <router-link to="/reception/gift-cards" class="nav-item">
                        🎁 کارت هدیه
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
    name: 'ReceptionLayout',
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
.reception-layout {
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