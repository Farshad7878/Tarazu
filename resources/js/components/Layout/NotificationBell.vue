<template>
    <div class="bell" ref="root">
        <button class="bell-btn" type="button" @click="toggle" :aria-label="'اعلان‌ها'">
            🔔
            <span v-if="unreadCount > 0" class="badge">{{ unreadCount > 99 ? '99+' : unreadCount }}</span>
        </button>

        <div v-if="open" class="panel">
            <div class="panel-header">
                <strong>اعلان‌ها</strong>
                <button v-if="unreadCount > 0" class="link" type="button" @click="markAllRead">
                    خواندن همه
                </button>
            </div>

            <p v-if="loading" class="empty">در حال بارگذاری...</p>
            <p v-else-if="notifications.length === 0" class="empty">اعلانی وجود ندارد.</p>

            <ul v-else class="list">
                <li
                    v-for="item in notifications"
                    :key="item.id"
                    :class="{ unread: !item.is_read }"
                    @click="markRead(item)"
                >
                    <div class="item-title">{{ item.title }}</div>
                    <div class="item-body">{{ item.body }}</div>
                    <div class="item-time">{{ item.created_at }}</div>
                </li>
            </ul>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

const POLL_INTERVAL_MS = 60000;

export default {
    name: 'NotificationBell',
    data() {
        return {
            open: false,
            loading: false,
            notifications: [],
            unreadCount: 0,
            timer: null,
        };
    },
    mounted() {
        this.fetch();
        this.timer = setInterval(this.fetch, POLL_INTERVAL_MS);
        document.addEventListener('click', this.handleOutsideClick);
    },
    beforeUnmount() {
        clearInterval(this.timer);
        document.removeEventListener('click', this.handleOutsideClick);
    },
    methods: {
        async fetch() {
            this.loading = this.notifications.length === 0;
            try {
                const { data } = await axios.get('/api/notifications');
                this.notifications = data.notifications;
                this.unreadCount = data.unread_count;
            } catch {
                // خطای شبکه یا ۴۰۱ (که interceptor هندل می‌کند) نباید پنل را خراب کند
            } finally {
                this.loading = false;
            }
        },
        toggle() {
            this.open = !this.open;
            if (this.open) this.fetch();
        },
        async markRead(item) {
            if (item.is_read) return;
            try {
                await axios.post(`/api/notifications/${item.id}/read`);
                item.is_read = true;
                this.unreadCount = Math.max(0, this.unreadCount - 1);
            } catch {
                // بعداً دوباره تلاش می‌شود
            }
        },
        async markAllRead() {
            try {
                await axios.post('/api/notifications/read-all');
                this.notifications.forEach((n) => (n.is_read = true));
                this.unreadCount = 0;
            } catch {
                // بی‌اثر؛ دوباره قابل تلاش است
            }
        },
        handleOutsideClick(event) {
            if (this.open && this.$refs.root && !this.$refs.root.contains(event.target)) {
                this.open = false;
            }
        },
    },
};
</script>

<style scoped>
.bell { position: relative; }
.bell-btn {
    position: relative;
    background: #edf2f7;
    border: none;
    border-radius: 8px;
    padding: 8px 12px;
    cursor: pointer;
    font-size: 16px;
}
.badge {
    position: absolute;
    top: -6px;
    left: -6px;
    background: #e53e3e;
    color: #fff;
    border-radius: 999px;
    font-size: 11px;
    padding: 1px 6px;
    min-width: 18px;
    text-align: center;
}
.panel {
    position: absolute;
    left: 0;
    top: 48px;
    width: 340px;
    max-height: 420px;
    overflow-y: auto;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
    z-index: 50;
}
.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 14px;
    border-bottom: 1px solid #e2e8f0;
}
.link { background: none; border: none; color: #3182ce; cursor: pointer; font-size: 13px; }
.empty { padding: 16px; color: #718096; text-align: center; margin: 0; }
.list { list-style: none; margin: 0; padding: 0; }
.list li { padding: 12px 14px; border-bottom: 1px solid #f1f5f9; cursor: pointer; }
.list li:hover { background: #f8fafc; }
.list li.unread { background: #ebf8ff; }
.item-title { font-weight: 600; color: #2d3748; margin-bottom: 4px; }
.item-body { color: #4a5568; font-size: 13px; line-height: 1.6; }
.item-time { color: #a0aec0; font-size: 11px; margin-top: 4px; }
</style>
