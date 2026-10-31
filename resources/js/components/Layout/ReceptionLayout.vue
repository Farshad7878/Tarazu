<template>
    <div class="app-shell">
        <AppSidebar role="reception" :groups="groups" :open="menuOpen" @close="menuOpen = false" />

        <div class="app-body">
            <Navbar :user="authStore.user" @toggle-menu="menuOpen = !menuOpen" />

            <main class="main-content">
                <router-view />
            </main>
        </div>

        <nav class="tabbar" aria-label="میانبرهای پذیرش">
            <router-link
                v-for="tab in tabs"
                :key="tab.to"
                :to="tab.to"
                class="tab"
                :class="{ active: isActive(tab.to) }"
            >
                <component :is="tab.icon" :size="22" :stroke-width="1.9" />
                <span>{{ tab.label }}</span>
            </router-link>
            <button type="button" class="tab" :class="{ active: menuOpen }" @click="menuOpen = !menuOpen">
                <MenuIcon :size="22" :stroke-width="1.9" />
                <span>منو</span>
            </button>
        </nav>

        <div v-if="menuOpen" class="scrim" aria-hidden="true" @click="menuOpen = false"></div>
    </div>
</template>

<script>
import { ref } from 'vue';
import { useAuthStore } from '../../stores/auth';
import { useRoute } from 'vue-router';
import Navbar from './Navbar.vue';
import AppSidebar from './AppSidebar.vue';
import { House, Plus, ShoppingBag, Wallet, Users, Gift, Menu as MenuIcon } from 'lucide-vue-next';

const groups = [
    {
        title: 'کار روزانه',
        items: [
            { to: '/reception/dashboard', label: 'صفحه اصلی', icon: House },
            { to: '/reception/new-order', label: 'ثبت سفارش جدید', icon: Plus },
            { to: '/reception/orders', label: 'سفارشات من', icon: ShoppingBag },
            { to: '/reception/shift', label: 'صندوق کاری', icon: Wallet }
        ]
    },
    {
        title: 'مشتریان',
        items: [
            { to: '/reception/customers', label: 'مشتریان', icon: Users },
            { to: '/reception/gift-cards', label: 'کارت هدیه', icon: Gift }
        ]
    }
];

const tabs = [
    { to: '/reception/new-order', label: 'ثبت سفارش', icon: Plus },
    { to: '/reception/orders', label: 'سفارش‌ها', icon: ShoppingBag },
    { to: '/reception/shift', label: 'صندوق', icon: Wallet },
    { to: '/reception/customers', label: 'مشتریان', icon: Users }
];

export default {
    name: 'ReceptionLayout',
    components: {
        Navbar,
        AppSidebar,
        MenuIcon
    },
    setup() {
        const authStore = useAuthStore();
        const route = useRoute();
        const menuOpen = ref(false);

        const isActive = (to) => route.path === to || route.path.startsWith(to + '/');

        return {
            authStore,
            groups,
            tabs,
            menuOpen,
            isActive
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

/* Bottom tab bar: phones only. */
.tabbar {
    display: none;
}

@media (max-width: 1023px) {
    .main-content {
        padding: 20px 18px 40px;
    }
}

@media (max-width: 768px) {
    .main-content {
        padding: 16px 12px 96px;
    }

    .tabbar {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        position: fixed;
        inset-inline: 0;
        bottom: 0;
        z-index: 58;
        background: rgba(255, 255, 255, 0.96);
        backdrop-filter: blur(8px);
        border-top: 1px solid var(--border);
        padding: 6px 6px calc(6px + env(safe-area-inset-bottom));
    }

    .tab {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 2px;
        min-height: 52px;
        border: none;
        background: none;
        color: var(--text-muted);
        font: inherit;
        font-size: 0.7rem;
        font-weight: 600;
        text-decoration: none;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100%;
        border-radius: var(--radius-sm);
        cursor: pointer;
    }

    .tab.active {
        color: var(--brand);
        background: var(--brand-soft);
    }
}
</style>
