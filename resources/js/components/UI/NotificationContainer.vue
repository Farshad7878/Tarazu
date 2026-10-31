<template>
    <div class="notification-container">
        <div 
            v-for="notification in notifications" 
            :key="notification.id"
            :class="['notification', `notification-${notification.type}`]"
            @click="removeNotification(notification.id)"
        >
            <div class="notification-title">{{ notification.title }}</div>
            <div class="notification-message">{{ notification.message }}</div>
            <button class="notification-close" @click.stop="removeNotification(notification.id)">
                ×
            </button>
        </div>
    </div>
</template>

<script>
import { useNotificationStore } from '../../stores/notifications';

export default {
    name: 'NotificationContainer',
    setup() {
        const notificationStore = useNotificationStore();

        return {
            notifications: notificationStore.notifications,
            removeNotification: notificationStore.removeNotification
        };
    }
}
</script>

<style scoped>
.notification-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 1000;
    max-width: 400px;
}

.notification {
    position: relative;
    background: white;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 10px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    border-left: 4px solid #ccc;
    cursor: pointer;
    transition: transform 0.3s ease;
}

.notification:hover {
    transform: translateX(-5px);
}

.notification-success {
    border-left-color: #10b981;
}

.notification-info {
    border-left-color: #3b82f6;
}

.notification-warning {
    border-left-color: #f59e0b;
}

.notification-error {
    border-left-color: #ef4444;
}

.notification-title {
    font-weight: bold;
    margin-bottom: 4px;
    color: #374151;
}

.notification-message {
    color: #6b7280;
    font-size: 14px;
}

.notification-close {
    position: absolute;
    top: 8px;
    right: 8px;
    background: none;
    border: none;
    font-size: 18px;
    cursor: pointer;
    color: #9ca3af;
}

.notification-close:hover {
    color: #374151;
}
</style>