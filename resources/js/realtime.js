import { useNotificationStore } from './stores/notifications';

/**
 * اتصال رویدادهای زنده بر اساس نقش کاربر.
 * بعد از ورود یا بازیابی نشست صدا زده می‌شود و قبل از خروج، اشتراک‌ها را می‌بندد.
 */
export function connectRealtime(user) {
    disconnectRealtime();

    if (!window.Echo || !user) return;

    try {
        subscribe(user);
    } catch (error) {
        // نبودن تنظیمات Reverb نباید ورود کاربر را خراب کند
        console.error('Realtime connection failed:', error);
    }
}

function subscribe(user) {
    const notifications = useNotificationStore();

    if (user.role === 'admin') {
        window.Echo.private('admin.dashboard')
            .listen('.new.order.created', (e) => {
                notifications.addNotification({
                    type: 'success',
                    title: 'سفارش جدید',
                    message: `سفارش ${e.order_number} از ${e.customer_name} به مبلغ ${Number(e.total_amount).toLocaleString('fa-IR')} تومان`,
                });
            })
            .listen('.NewCustomerRegistered', (e) => {
                notifications.addNotification({
                    type: 'info',
                    title: 'مشتری جدید',
                    message: `مشتری جدید: ${e.full_name} - شماره عضویت: ${e.membership_number}`,
                });
            })
            .listen('.birthday.today', (e) => {
                notifications.addNotification({
                    type: 'info',
                    title: 'تولد مشتری',
                    message: `امروز تولد ${e.customer_name} است (شماره عضویت: ${e.membership_number})`,
                });
            });
    }

    if (user.role === 'reception') {
        window.Echo.private(`user.${user.id}`)
            .listen('.DiscountApplied', (e) => {
                notifications.addNotification({
                    type: 'success',
                    title: 'تخفیف اعمال شد',
                    message: `${e.message} (سفارش ${e.order_number})`,
                });
            });
    }
}

export function disconnectRealtime() {
    if (window.Echo) {
        window.Echo.leaveAllChannels();
    }
}
