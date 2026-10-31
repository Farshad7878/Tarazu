<template>
    <header class="navbar">
        <div class="navbar-content">
            <div class="navbar-brand">
                <h1>{{ appName }}</h1>
                <span class="user-role" v-if="user">
                    {{ user.role === 'admin' ? 'مدیر سیستم' : 'ریسپشن' }}
                </span>
            </div>
            
            <div class="navbar-actions">
                <NotificationBell v-if="user?.role === 'admin'" />
                <span class="welcome-message">خوش آمدید, {{ user?.name }}</span>
                <span v-if="user?.role === 'reception' && user?.branch" class="welcome-message">🏬 {{ user.branch.name }}</span>
                <button @click="logout" class="logout-btn">
                    🚪 خروج
                </button>
            </div>
        </div>
    </header>
</template>

<script>
import { useAuthStore } from '../../stores/auth';
import { useRouter } from 'vue-router';
import { appName } from '../../appConfig';
import NotificationBell from './NotificationBell.vue';

export default {
    name: 'Navbar',
    components: { NotificationBell },
    props: {
        user: {
            type: Object,
            required: true
        }
    },
    setup() {
        const authStore = useAuthStore();
        const router = useRouter();

        const logout = async () => {
            await authStore.logout();
            router.push('/login');
        };

        return {
            appName,
            logout
        };
    }
}
</script>

<style scoped>
.navbar {
    background: white;
    border-bottom: 1px solid #e2e8f0;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    height: 64px;
}

.navbar-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 100%;
    padding: 0 24px;
    max-width: 1200px;
    margin: 0 auto;
}

.navbar-brand {
    display: flex;
    align-items: center;
    gap: 12px;
}

.navbar-brand h1 {
    color: #2d3748;
    font-size: 1.5rem;
    font-weight: bold;
}

.user-role {
    background: #edf2f7;
    color: #4a5568;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 500;
}

.navbar-actions {
    display: flex;
    align-items: center;
    gap: 16px;
}

.welcome-message {
    color: #4a5568;
    font-weight: 500;
}

.logout-btn {
    background: #fed7d7;
    color: #c53030;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    transition: background 0.3s ease;
}

.logout-btn:hover {
    background: #feb2b2;
}
/* Mobile: let the navbar wrap instead of pushing content off the start edge */
@media (max-width: 768px) {
    .navbar {
        height: auto;
    }
    .navbar-content {
        flex-wrap: wrap;
        height: auto;
        padding: 10px 12px;
        gap: 8px;
    }
    .navbar-brand {
        flex-wrap: wrap;
        min-width: 0;
        gap: 8px;
    }
    .navbar-brand h1 {
        font-size: 1.1rem;
    }
    .navbar-actions {
        flex-wrap: wrap;
        gap: 8px;
    }
    .welcome-message {
        font-size: 0.875rem;
    }
}
</style>