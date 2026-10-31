<template>
    <div class="login-page">
        <section class="login-hero" aria-hidden="true">
            <div class="hero-inner">
                <img src="/icons/icon-512.png" alt="" class="hero-logo" />
                <h2>{{ appName }}</h2>
                <p>مدیریت فروش، موجودی و مشتریان در یک سیستم ساده و یکپارچه.</p>
            </div>
        </section>

        <main class="login-main">
            <div class="login-card">
                <div class="login-brand">
                    <img src="/icons/icon-192.png" alt="" class="brand-logo" width="44" height="44" />
                    <span>{{ appName }}</span>
                </div>

                <h1>ورود به حساب کاربری</h1>
                <p class="login-subtitle">لطفاً ایمیل و رمز عبور خود را وارد کنید.</p>

                <form @submit.prevent="handleLogin" class="login-form" novalidate>
                    <div class="form-group">
                        <label for="email">ایمیل</label>
                        <input
                            type="email"
                            id="email"
                            v-model="form.email"
                            required
                            autocomplete="username"
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
                            autocomplete="current-password"
                            placeholder="••••••••"
                        >
                    </div>

                    <button type="submit" class="login-btn" :disabled="loading">
                        <span v-if="loading">در حال ورود...</span>
                        <span v-else>ورود به سیستم</span>
                    </button>

                    <div v-if="notice" class="notice-message" role="status">
                        {{ notice }}
                    </div>

                    <div v-if="error" class="error-message" role="alert">
                        {{ error }}
                    </div>
                </form>

                <!-- حساب‌های دمو فقط در محیط توسعه نمایش داده می‌شوند -->
                <div v-if="isDev" class="demo-accounts">
                    <h3>حساب‌های دمو (فقط توسعه)</h3>
                    <button type="button" class="demo-account" @click="fillDemo('admin@example.com')">
                        <strong>ادمین</strong><span>admin@example.com</span>
                    </button>
                    <button type="button" class="demo-account" @click="fillDemo('reception@example.com')">
                        <strong>ریسپشن</strong><span>reception@example.com</span>
                    </button>
                    <p class="demo-hint">رمز عبور هر دو حساب: password</p>
                </div>
            </div>
        </main>
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

        // پر کردن سریع فرم با حساب دمو (فقط در توسعه)؛ رمز را کاربر خودش وارد می‌کند.
        const fillDemo = (email) => {
            form.value.email = email;
            form.value.password = 'password';
        };

        return {
            appName,
            form,
            loading,
            error,
            notice,
            isDev,
            handleLogin,
            fillDemo
        };
    }
}
</script>

<style scoped>
.login-page {
    min-height: 100vh;
    min-height: 100dvh;
    display: grid;
    grid-template-columns: minmax(0, 1.05fr) minmax(360px, 480px);
    background: var(--bg);
}

.login-hero {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 48px;
    color: #fff;
    background:
        radial-gradient(circle at 20% 20%, rgba(255, 255, 255, 0.18), transparent 45%),
        linear-gradient(145deg, #1e4e80 0%, #2b6cb0 55%, #3b82c4 100%);
    overflow: hidden;
}

.hero-inner {
    max-width: 440px;
}

.hero-logo {
    width: 88px;
    height: 88px;
    border-radius: 22px;
    box-shadow: 0 16px 40px rgba(0, 0, 0, 0.25);
}

.hero-inner h2 {
    color: #fff;
    font-size: 2.4rem;
    font-weight: 800;
    margin: 24px 0 8px;
}

.hero-inner p {
    font-size: 1.05rem;
    line-height: 1.9;
    opacity: 0.92;
    margin: 0;
}

.login-main {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 24px;
}

.login-card {
    width: 100%;
    max-width: 400px;
}

.login-brand {
    display: none;
    align-items: center;
    gap: 10px;
    font-weight: 800;
    font-size: 1.25rem;
    color: var(--text);
    margin-bottom: 28px;
}

.brand-logo {
    border-radius: 12px;
}

.login-card h1 {
    font-size: 1.7rem;
    font-weight: 800;
    margin: 0;
}

.login-subtitle {
    color: var(--text-muted);
    margin: 6px 0 28px;
}

.login-form .form-group {
    margin-bottom: 18px;
}

.login-form input {
    padding: 12px 14px;
    font-size: 1rem;
}

.login-btn {
    width: 100%;
    margin-top: 6px;
    padding: 13px;
    border: none;
    border-radius: var(--radius-sm);
    background: var(--brand);
    color: #fff;
    font: inherit;
    font-weight: 700;
    font-size: 1rem;
    cursor: pointer;
    box-shadow: 0 6px 16px rgba(43, 108, 176, 0.3);
    transition: background-color 0.15s ease;
}

.login-btn:hover:not(:disabled) {
    background: var(--brand-hover);
}

.login-btn:focus-visible {
    outline: none;
    box-shadow: var(--focus-ring);
}

.login-btn:disabled {
    opacity: 0.7;
    cursor: wait;
}

.notice-message,
.error-message {
    margin-top: 14px;
    padding: 10px 12px;
    border-radius: var(--radius-sm);
    font-size: 0.9rem;
}

.notice-message {
    background: var(--warning-soft);
    color: var(--warning);
}

.error-message {
    background: var(--danger-soft);
    color: #b91c1c;
}

.demo-accounts {
    margin-top: 28px;
    padding-top: 20px;
    border-top: 1px dashed var(--border-strong);
}

.demo-accounts h3 {
    font-size: 0.85rem;
    color: var(--text-muted);
    margin: 0 0 10px;
    font-weight: 600;
}

.demo-account {
    width: 100%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    padding: 10px 12px;
    margin-bottom: 8px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    font: inherit;
    font-size: 0.85rem;
    color: var(--text-2);
    cursor: pointer;
    text-align: start;
}

.demo-account:hover {
    border-color: var(--brand);
}

.demo-account span {
    direction: ltr;
    color: var(--text-muted);
}

.demo-hint {
    font-size: 0.8rem;
    color: var(--text-muted);
    margin: 4px 0 0;
}

@media (max-width: 900px) {
    .login-page {
        grid-template-columns: 1fr;
    }

    .login-hero {
        display: none;
    }

    .login-brand {
        display: flex;
    }

    .login-main {
        align-items: flex-start;
        padding: 40px 20px;
    }
}
</style>
