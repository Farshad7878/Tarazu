import axios from 'axios';

export const productApi = {
    // دریافت لیست محصولات
    getProducts(params = {}) {
        return axios.get('/api/admin/products', { params });
    },

    // ایجاد محصول جدید
    createProduct(data) {
        return axios.post('/api/admin/products', data);
    },

    // بروزرسانی محصول
    updateProduct(id, data) {
        return axios.put(`/api/admin/products/${id}`, data);
    },

    // حذف محصول
    deleteProduct(id) {
        return axios.delete(`/api/admin/products/${id}`);
    },

    // دریافت محصولات برای ریسپشن
    getReceptionProducts() {
        return axios.get('/api/reception/products');
    }
};