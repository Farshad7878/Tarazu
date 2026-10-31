import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';
import { setRealtimeToken } from '../bootstrap';
import { connectRealtime, disconnectRealtime } from '../realtime';
import { clearOfflineCache } from '../utils/offline';

// کپی کاربر جاری در localStorage: اگر اینترنت قطع باشد و /api/user در دسترس نباشد،
// نقش کاربر از همین کپی خوانده می‌شود تا دستگاه آفلاین همچنان وارد صفحه‌ی درست شود.
const USER_KEY = 'auth_user';

function readCachedUser() {
    try {
        return JSON.parse(localStorage.getItem(USER_KEY) || 'null');
    } catch {
        return null;
    }
}

export const useAuthStore = defineStore('auth', () => {
    const user = ref(localStorage.getItem('auth_token') ? readCachedUser() : null);
    const token = ref(localStorage.getItem('auth_token'));

    const isAuthenticated = computed(() => !!token.value);
    const isAdmin = computed(() => user.value?.role === 'admin');
    const isReception = computed(() => user.value?.role === 'reception');

    // تنظیم هدرهای axios
    if (token.value) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token.value}`;
        setRealtimeToken(token.value);
    }

    // بازیابی نشست را فقط یک بار و هم‌زمان برای همه‌ی درخواست‌ها اجرا می‌کنیم
    let checkPromise = null;

    function setSession(userData, authToken) {
        user.value = userData;
        token.value = authToken;

        localStorage.setItem('auth_token', authToken);
        localStorage.setItem(USER_KEY, JSON.stringify(userData));
        axios.defaults.headers.common['Authorization'] = `Bearer ${authToken}`;
        setRealtimeToken(authToken);
        connectRealtime(userData);
    }

    async function login(credentials) {
        try {
            const response = await axios.post('/api/login', credentials);

            const { user: userData, token: authToken } = response.data;
            setSession(userData, authToken);

            return { success: true, user: userData };
        } catch (error) {
            // خطای اعتبارسنجی لاراول پیام کلی دارد؛ متن قابل‌فهم در errors.email است
            const data = error.response?.data;
            return {
                success: false,
                error: data?.errors?.email?.[0] || data?.message || 'خطا در ورود',
            };
        }
    }

    function clearSession() {
        disconnectRealtime();
        user.value = null;
        token.value = null;
        localStorage.removeItem('auth_token');
        localStorage.removeItem(USER_KEY);
        delete axios.defaults.headers.common['Authorization'];
        setRealtimeToken(null);
        // کش منو و مشتری‌ها نباید برای کاربر بعدی همین دستگاه بماند (صف سفارش‌ها حفظ می‌شود)
        clearOfflineCache();
    }

    async function logout() {
        try {
            if (token.value) {
                await axios.post('/api/logout');
            }
        } catch (error) {
            console.error('Logout error:', error);
        } finally {
            clearSession();
        }
    }

    /**
     * بازیابی کاربر جاری از روی توکن (بعد از refresh).
     * فقط پاسخ 401 نشست را پاک می‌کند؛ خطای شبکه باعث خروج کاربر نمی‌شود.
     */
    function checkAuth() {
        if (user.value) return Promise.resolve(true);
        if (!token.value) return Promise.resolve(false);

        if (!checkPromise) {
            checkPromise = axios.get('/api/user')
                .then((response) => {
                    setSession(response.data, token.value);
                    return true;
                })
                .catch((error) => {
                    if (error.response?.status === 401) {
                        clearSession();
                        return false;
                    }
                    // خطای شبکه (آفلاین): اگر کاربر قبلاً ذخیره شده، همان را معتبر می‌دانیم
                    const cached = readCachedUser();
                    if (!error.response && cached) {
                        user.value = cached;
                        return true;
                    }
                    return false;
                })
                .finally(() => {
                    checkPromise = null;
                });
        }

        return checkPromise;
    }

    return {
        user,
        token,
        isAuthenticated,
        isAdmin,
        isReception,
        login,
        logout,
        clearSession,
        checkAuth
    };
});
