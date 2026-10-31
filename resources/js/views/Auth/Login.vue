<template>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1>{{ appName }}</h1>
                <p>لطفا وارد حساب کاربری خود شوید</p>
            </div>

            <form @submit.prevent="handleLogin" class="login-form">
                <div class="form-group">
                    <label for="email">ایمیل</label>
                    <input
                        type="email"
                        id="email"
                        v-model="form.email"
                        required
                        placeholder="example@company.com"
                    >
                </div>

                <div class="form-group">
                    <label for="password">رمز عبور</label>
                    <input
                        type="password"
                        id="password"
                        v-model="form.password"
                        required
                        placeholder="••••••••"
                    >
                </div>

                <button type="submit" class="login-btn" :disabled="loading">
                    <span v-if="loading">در حال ورود...</span>
                    <span v-else>ورود به سیستم</span>
                </button>

                <div v-if="notice" class="notice-message">
                    {{ notice }}
                </div>

                <div v-if="error" class="error-message">
                    {{ error }}
                </div>
            </form>

            <!-- حساب‌های دمو فقط در محیط توسعه نمایش داده می‌شوند -->
            <div v-if="isDev" class="demo-accounts">
                <h3>حساب‌های دمو (فقط توسعه):</h3>
                <div class="demo-account">
                    <strong>ادمین:</strong> admin@example.com / password
                </div>
                <div class="demo-account">
                    <strong>ریسپشن:</strong> reception@example.com / password
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { appName } from '../../appConfig';

export default {
    name: 'Login',
    setup() {
        const form = ref({
            email: '',
            password: ''
        });
        
        const loading = ref(false);
        const error = ref('');
        
        const authStore = useAuthStore();
        const router = useRouter();
        const route = useRoute();

        // اگر نشست توسط interceptor منقضی شده باشد، پیام راهنما نشان داده می‌شود
        const notice = ref(route.query.expired ? 'نشست شما منقضی شده است. لطفاً دوباره وارد شوید.' : '');
        const isDev = import.meta.env.DEV;

        const handleLogin = async () => {
            loading.value = true;
            error.value = '';
            notice.value = '';

            const result = await authStore.login(form.value);

            if (result.success) {
                // هدایت بر اساس نقش کاربر
                if (result.user.role === 'admin') {
                    router.push('/admin/dashboard');
                } else {
                    router.push('/reception/dashboard');
                }
            } else {
                error.value = result.error;
            }

            loading.value = false;
        };

        return {
            appName,
            form,
            loading,
            error,
            notice,
            isDev,
            handleLogin
        };
    }
}
</script>

<style scoped>
.notice-message {
    margin-top: 12px;
    padding: 10px 12px;
    border-radius: 8px;
    background: #fef3c7;
    color: #92400e;
    font-size: 14px;
}

.login-container {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 20px;
}

.login-card {
    background: white;
    border-radius: 16px;
    padding: 40px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    width: 100%;
    max-width: 400px;
}

.login-header {
    text-align: center;
    margin-bottom: 32px;
}

.login-header h1 {
    color: #2d3748;
    font-size: 1.5rem;
    margin-bottom: 8px;
}

.login-header p {
    color: #718096;
}

.login-form {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-group label {
    color: #4a5568;
    font-weight: 500;
    font-size: 0.875rem;
}

.form-group input {
    padding: 12px 16px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.3s ease;
}

.form-group input:focus {
    outline: none;
    border-color: #3182ce;
}

.login-btn {
    background: #3182ce;
    color: white;
    border: none;
    padding: 12px 16px;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.3s ease;
}

.login-btn:hover:not(:disabled) {
    background: #2c5aa0;
}

.login-btn:disabled {
    background: #a0aec0;
    cursor: not-allowed;
}

.error-message {
    background: #fed7d7;
    color: #c53030;
    padding: 12px 16px;
    border-radius: 8px;
    font-size: 0.875rem;
    text-align: center;
}

.demo-accounts {
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid #e2e8f0;
}

.demo-accounts h3 {
    color: #4a5568;
    font-size: 0.875rem;
    margin-bottom: 12px;
}

.demo-account {
    background: #f7fafc;
    padding: 8px 12px;
    border-radius: 6px;
    margin-bottom: 8px;
    font-size: 0.875rem;
    color: #4a5568;
}
</style>