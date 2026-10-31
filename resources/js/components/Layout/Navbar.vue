<template>
    <header class="topbar">
        <div class="topbar-start">
            <button type="button" class="menu-btn" aria-label="باز کردن منو" @click="$emit('toggle-menu')">
                <MenuIcon :size="22" :stroke-width="2" />
            </button>
            <span class="topbar-brand">{{ appName }}</span>
            <span class="role-chip">{{ roleLabel }}</span>
            <span v-if="user?.role === 'reception' && user?.branch" class="branch-chip">
                <Store :size="14" :stroke-width="2" />
                {{ user.branch.name }}
            </span>
        </div>

        <div class="topbar-end">
            <NotificationBell v-if="user?.role === 'admin'" />

            <div class="user-chip" :title="user?.name">
                <span class="avatar" aria-hidden="true">{{ initial }}</span>
                <span class="user-name">{{ user?.name }}</span>
            </div>

            <button type="button" class="icon-btn logout-btn" aria-label="خروج از حساب" title="خروج" @click="logout">
                <LogOut :size="18" :stroke-width="2" />
                <span class="logout-label">خروج</span>
            </button>
        </div>
    </header>
</template>

<script>
import { computed } from 'vue';
import { useAuthStore } from '../../stores/auth';
import { useRouter } from 'vue-router';
import { appName } from '../../appConfig';
import NotificationBell from './NotificationBell.vue';
import { Menu as MenuIcon, LogOut, Store } from 'lucide-vue-next';

export default {
    name: 'Navbar',
    components: { NotificationBell, MenuIcon, LogOut, Store },
    props: {
        user: {
            type: Object,
            required: true
        }
    },
    emits: ['toggle-menu'],
    setup(props) {
        const authStore = useAuthStore();
        const router = useRouter();

        const roleLabel = computed(() => (props.user?.role === 'admin' ? 'مدیر سیستم' : 'ریسپشن'));
        const initial = computed(() => (props.user?.name || '?').trim().charAt(0).toUpperCase());

        const logout = async () => {
            await authStore.logout();
            router.push('/login');
        };

        return {
            appName,
            roleLabel,
            initial,
            logout
        };
    }
};
</script>

<style scoped>
.topbar {
    position: sticky;
    top: 0;
    z-index: 50;
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 0 24px;
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: saturate(160%) blur(8px);
    border-bottom: 1px solid var(--border);
}

.topbar-start,
.topbar-end {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
}

.menu-btn {
    display: none;
    width: 40px;
    height: 40px;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--border);
    background: var(--surface);
    color: var(--text-2);
    border-radius: var(--radius-sm);
    cursor: pointer;
}

.topbar-brand {
    display: none;
    font-weight: 700;
    color: var(--text);
}

.role-chip {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-2);
    background: #eef2f7;
    padding: 4px 10px;
    border-radius: 999px;
    white-space: nowrap;
}

.branch-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--brand-ink);
    background: var(--brand-soft);
    padding: 4px 10px;
    border-radius: 999px;
    white-space: nowrap;
}

.user-chip {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 4px 10px 4px 4px;
    border-radius: 999px;
    background: var(--surface-muted);
    border: 1px solid var(--border);
    min-width: 0;
}

.avatar {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #2b6cb0, #4c8fd6);
    color: #fff;
    font-weight: 700;
    font-size: 0.85rem;
    flex-shrink: 0;
}

.user-name {
    font-weight: 600;
    font-size: 0.88rem;
    color: var(--text-2);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 180px;
}

.icon-btn,
.logout-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    height: 38px;
    padding: 0 12px;
    border-radius: var(--radius-sm);
    border: 1px solid #f5c2c2;
    background: var(--danger-soft);
    color: #b91c1c;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: background-color 0.15s ease;
}

.logout-btn:hover {
    background: #fecaca;
}

.logout-btn:focus-visible {
    outline: none;
    box-shadow: var(--focus-ring);
}

/* ---- Phones / tablets ---- */
@media (max-width: 1023px) {
    .menu-btn {
        display: inline-flex;
    }

    .topbar-brand {
        display: inline;
    }

    .role-chip {
        display: none;
    }
}

@media (max-width: 768px) {
    .topbar {
        padding: 0 12px;
        height: 60px;
    }

    .topbar-start .branch-chip {
        display: none;
    }

    .user-chip {
        padding: 0;
        background: transparent;
        border: none;
    }

    .user-name {
        display: none;
    }

    .logout-label {
        display: none;
    }

    .logout-btn {
        width: 38px;
        padding: 0;
        justify-content: center;
    }
}
</style>
