import axios from 'axios';

export const discountApi = {
    // دریافت لیست تخفیف‌ها
    getDiscounts(params = {}) {
        return axios.get('/api/admin/discounts', { params });
    },

    // ایجاد تخفیف جدید
    createDiscount(data) {
        return axios.post('/api/admin/discounts', data);
    },

    // بروزرسانی تخفیف
    updateDiscount(id, data) {
        return axios.put(`/api/admin/discounts/${id}`, data);
    },

    // اختصاص تخفیف به مشتری
    assignToCustomer(discountId, customerId) {
        return axios.post(`/api/admin/discounts/${discountId}/assign`, {
            customer_id: customerId
        });
    }
};