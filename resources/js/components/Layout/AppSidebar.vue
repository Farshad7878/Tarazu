<template>
    <aside class="app-sidebar" :class="{ 'is-open': open }" aria-label="ناوبری اصلی">
        <div class="sidebar-brand">
            <img :src="logoSrc" alt="" class="brand-mark" width="36" height="36" />
            <div class="brand-text">
                <span class="brand-name">{{ appName }}</span>
                <span class="brand-role">{{ roleLabel }}</span>
            </div>
            <button type="button" class="sidebar-close" aria-label="بستن منو" @click="$emit('close')">
                <X :size="20" :stroke-width="2" />
            </button>
        </div>

        <nav class="sidebar-nav">
            <div v-for="group in groups" :key="group.title" class="nav-group">
                <p class="nav-group-title">{{ group.title }}</p>
                <ul>
                    <li v-for="item in group.items" :key="item.to">
                        <router-link
                            :to="item.to"
                            class="nav-item"
                            :class="{ active: isActive(item.to) }"
                            :aria-current="isActive(item.to) ? 'page' : undefined"
                            @click="$emit('close')"
                        >
                            <component :is="item.icon" :size="19" :stroke-width="1.9" class="nav-icon" />
                            <span>{{ item.label }}</span>
                        </router-link>
                    </li>
                </ul>
            </div>
        </nav>
    </aside>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { X } from 'lucide-vue-next';
import { appName } from '../../appConfig';

const props = defineProps({
    groups: { type: Array, required: true },
    role: { type: String, default: 'admin' },
    open: { type: Boolean, default: false },
});

defineEmits(['close']);

const route = useRoute();

const roleLabel = computed(() => (props.role === 'admin' ? 'پنل مدیریت' : 'پنل پذیرش'));
const logoSrc = '/icons/icon-192.png';

const isActive = (to) => route.path === to || route.path.startsWith(to + '/');
</script>

<style scoped>
.app-sidebar {
    width: 260px;
    flex-shrink: 0;
    position: sticky;
    top: 0;
    height: 100vh;
    display: flex;
    flex-direction: column;
    background: var(--surface);
    border-inline-start: 1px solid var(--border);
    z-index: 60;
}

.sidebar-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 20px 20px 16px;
}

.brand-mark {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    flex-shrink: 0;
}

.brand-text {
    display: flex;
    flex-direction: column;
    min-width: 0;
    flex: 1;
}

.brand-name {
    font-weight: 700;
    font-size: 1.1rem;
    color: var(--text);
    line-height: 1.2;
}

.brand-role {
    font-size: 0.78rem;
    color: var(--text-muted);
}

.sidebar-close {
    display: none;
    border: none;
    background: none;
    color: var(--text-muted);
    width: 36px;
    height: 36px;
    border-radius: var(--radius-sm);
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.sidebar-nav {
    flex: 1;
    overflow-y: auto;
    padding: 4px 12px 24px;
}

.nav-group + .nav-group {
    margin-top: 14px;
}

.nav-group-title {
    margin: 0 10px 6px;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    color: var(--text-muted);
}

.nav-group ul {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.nav-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 9px 12px;
    border-radius: var(--radius-sm);
    color: var(--text-2);
    text-decoration: none;
    font-size: 0.92rem;
    font-weight: 500;
    transition:
        background-color 0.15s ease,
        color 0.15s ease;
}

.nav-icon {
    flex-shrink: 0;
    color: #8592a6;
    transition: color 0.15s ease;
}

.nav-item:hover {
    background: #f1f5f9;
    color: var(--text);
}

.nav-item:hover .nav-icon {
    color: var(--text-2);
}

.nav-item.active {
    background: var(--brand-soft);
    color: var(--brand-ink);
    font-weight: 600;
}

.nav-item.active .nav-icon {
    color: var(--brand);
}

.nav-item:focus-visible {
    outline: none;
    box-shadow: var(--focus-ring);
}

/* ---- Off-canvas drawer below 1024px ----
   Hidden with display:none while closed, so it never adds to the page's scroll width. */
@media (max-width: 1023px) {
    .app-sidebar {
        display: none;
        position: fixed;
        inset-block: 0;
        inset-inline-start: 0;
        height: 100dvh;
        width: min(300px, 86vw);
        box-shadow: var(--shadow-lg);
        border-inline-start: none;
    }

    .app-sidebar.is-open {
        display: flex;
        animation: drawer-in 0.22s ease-out;
    }

    .sidebar-close {
        display: inline-flex;
    }
}

@keyframes drawer-in {
    from {
        transform: translateX(100%);
    }
    to {
        transform: translateX(0);
    }
}
</style>
