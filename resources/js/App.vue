<template>
    <div id="app">
        <RouterView />
        <NotificationContainer />
    </div>
</template>

<script>
import NotificationContainer from './components/UI/NotificationContainer.vue';
import { useAuthStore } from './stores/auth';

// نشست و اتصال‌های زنده در router و auth store مدیریت می‌شوند.
// اینجا فقط رویداد «نشست منقضی شده» (از interceptor در bootstrap.js) را گوش می‌دهیم.
export default {
    name: 'App',
    components: {
        NotificationContainer
    },
    mounted() {
        window.addEventListener('auth:expired', this.handleExpired);
    },
    beforeUnmount() {
        window.removeEventListener('auth:expired', this.handleExpired);
    },
    methods: {
        handleExpired() {
            const auth = useAuthStore();
            auth.clearSession();

            if (this.$route.name !== 'login') {
                this.$router.push({ name: 'login', query: { expired: '1' } });
            }
        }
    }
}
</script>
