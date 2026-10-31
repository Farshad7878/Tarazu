import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js'; // Reverb از پروتکل Pusher استفاده می‌کند

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// اگر توکن منقضی یا باطل شده باشد، هر درخواست ۴۰۱ می‌دهد.
// رویداد سراسری می‌فرستیم تا App.vue نشست را پاک کرده و کاربر را به صفحه‌ی ورود ببرد.
window.axios.interceptors.response.use(
    (response) => response,
    (error) => {
        const status = error.response?.status;
        const url = error.config?.url || '';

        if (status === 401 && !url.includes('/api/login')) {
            window.dispatchEvent(new CustomEvent('auth:expired'));
        }

        return Promise.reject(error);
    }
);

// هدرهای احراز هویت کانال‌های خصوصی.
// این شیء به‌صورت ارجاعی در Echo نگه داشته می‌شود، پس با setRealtimeToken به‌روز می‌شود
// (بدون نیاز به reload بعد از ورود).
export const echoAuth = {
    headers: {
        Authorization: localStorage.getItem('auth_token')
            ? `Bearer ${localStorage.getItem('auth_token')}`
            : '',
    },
};

export function setRealtimeToken(token) {
    echoAuth.headers.Authorization = token ? `Bearer ${token}` : '';
}

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
    wsPort: import.meta.env.VITE_REVERB_PORT || 8080,
    wssPort: import.meta.env.VITE_REVERB_PORT || 8080,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
    // مسیر احراز هویت کانال‌ها در API (با Bearer token)
    authEndpoint: '/api/broadcasting/auth',
    auth: echoAuth,
});
