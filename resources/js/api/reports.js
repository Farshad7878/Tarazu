import axios from 'axios';

export const reportApi = {
    // دریافت آمار کلی
    getStats(params = {}) {
        return axios.get('/api/admin/reports/stats', { params });
    },

    // دریافت سفارشات اخیر
    getRecentOrders(params = {}) {
        return axios.get('/api/admin/reports/recent-orders', { params });
    }
};