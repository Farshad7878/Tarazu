import axios from 'axios';

export const customerApi = {
    // دریافت لیست مشتریان
    getCustomers(params = {}) {
        return axios.get('/api/admin/customers', { params });
    },

    // ایجاد مشتری جدید
    createCustomer(data) {
        return axios.post('/api/admin/customers', data);
    },

    // بروزرسانی مشتری
    updateCustomer(id, data) {
        return axios.put(`/api/admin/customers/${id}`, data);
    },

    // دریافت جزئیات مشتری
    getCustomer(id) {
        return axios.get(`/api/admin/customers/${id}`);
    },

    // جستجوی مشتری (برای ریسپشن)
    lookupCustomer(membershipNumber) {
        return axios.get(`/api/reception/customers/lookup/${membershipNumber}`);
    }
};