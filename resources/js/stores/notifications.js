import { defineStore } from 'pinia';
import { ref } from 'vue';

export const useNotificationStore = defineStore('notifications', () => {
    const notifications = ref([]);

    function addNotification(notification) {
        const id = Date.now();

        notifications.value.unshift({
            id,
            ...notification,
            timestamp: new Date()
        });

        // حذف اتوماتیک پس از 5 ثانیه
        setTimeout(() => {
            removeNotification(id);
        }, 5000);
    }

    function removeNotification(id) {
        const index = notifications.value.findIndex(n => n.id === id);
        if (index !== -1) {
            notifications.value.splice(index, 1);
        }
    }

    function clearAll() {
        notifications.value = [];
    }

    return {
        notifications,
        addNotification,
        removeNotification,
        clearAll
    };
});
