<template>
    <div class="customers-page">
        <div class="page-header">
            <h2>👥 مشتریان</h2>
            <button @click="showCustomerModal = true" class="btn-primary">
                ➕ ثبت مشتری جدید
            </button>
        </div>

        <div class="search-box">
            <input
                type="text"
                v-model="search"
                @input="handleSearch"
                placeholder="جستجو با نام، شماره تماس یا شماره عضویت..."
            >
        </div>

        <div class="table-container">
            <table class="customers-table">
                <thead>
                    <tr>
                        <th>شماره عضویت</th>
                        <th>نام و نام خانوادگی</th>
                        <th>شماره تماس</th>
                        <th>مجموع خرید (تومان)</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="customer in customers" :key="customer.id">
                        <td><span class="membership">{{ customer.membership_number }}</span></td>
                        <td>{{ customer.full_name }}</td>
                        <td>{{ customer.phone }}</td>
                        <td>{{ formatPrice(customer.total_purchases) }}</td>
                        <td>
                            <button @click="selectedCustomer = customer" class="btn-link">سوابق خرید</button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="loading" class="empty-state">در حال بارگذاری...</div>
            <div v-else-if="customers.length === 0" class="empty-state">مشتری‌ای یافت نشد.</div>
        </div>

        <div v-if="pagination.last_page > 1" class="pagination">
            <button :disabled="pagination.current_page <= 1" @click="changePage(pagination.current_page - 1)">قبلی</button>
            <span>صفحه {{ pagination.current_page }} از {{ pagination.last_page }}</span>
            <button :disabled="pagination.current_page >= pagination.last_page" @click="changePage(pagination.current_page + 1)">بعدی</button>
        </div>

        <CustomerModal
            :show="showCustomerModal"
            @close="showCustomerModal = false"
            @saved="handleSaved"
        />

        <CustomerHistoryModal
            v-if="selectedCustomer"
            :customer="selectedCustomer"
            @close="selectedCustomer = null"
        />
    </div>
</template>

<script>
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { useNotificationStore } from '../../stores/notifications';
import CustomerModal from './CustomerModal.vue';
import CustomerHistoryModal from './CustomerHistoryModal.vue';

export default {
    name: 'ReceptionCustomers',
    components: { CustomerModal, CustomerHistoryModal },
    setup() {
        const notificationStore = useNotificationStore();

        const customers = ref([]);
        const loading = ref(false);
        const search = ref('');
        const pagination = ref({ current_page: 1, last_page: 1, total: 0 });
        const showCustomerModal = ref(false);
        const selectedCustomer = ref(null);

        let searchTimer = null;

        const fetchCustomers = async (page = 1) => {
            loading.value = true;
            try {
                const params = { page };
                if (search.value.trim()) {
                    params.search = search.value.trim();
                }

                const response = await axios.get('/api/reception/customers', { params });
                customers.value = response.data.customers;
                pagination.value = response.data.pagination;
            } catch {
                notificationStore.addNotification({ type: 'error', title: 'خطا', message: 'خطا در دریافت لیست مشتریان' });
            } finally {
                loading.value = false;
            }
        };

        const handleSearch = () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => fetchCustomers(1), 400);
        };

        const changePage = (page) => {
            if (page >= 1 && page <= pagination.value.last_page) {
                fetchCustomers(page);
            }
        };

        const handleSaved = (customer) => {
            notificationStore.addNotification({
                type: 'success',
                title: 'ثبت شد',
                message: `مشتری ${customer.full_name} با شماره عضویت ${customer.membership_number} ثبت شد`,
            });
            fetchCustomers(1);
        };

        const formatPrice = (price) => new Intl.NumberFormat('fa-IR').format(price || 0);

        onMounted(() => fetchCustomers(1));
        onUnmounted(() => clearTimeout(searchTimer));

        return {
            customers,
            loading,
            search,
            pagination,
            showCustomerModal,
            selectedCustomer,
            handleSearch,
            changePage,
            handleSaved,
            formatPrice,
        };
    }
};
</script>

<style scoped>
.customers-page {
    padding: 20px;
    max-width: 1100px;
    margin: 0 auto;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.page-header h2 {
    color: #2d3748;
    font-size: 1.5rem;
    margin: 0;
}

.search-box input {
    width: 100%;
    padding: 10px 14px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    margin-bottom: 16px;
}

.table-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    overflow-x: auto;
}

.customers-table {
    width: 100%;
    border-collapse: collapse;
}

.customers-table th,
.customers-table td {
    padding: 12px 16px;
    text-align: right;
    border-bottom: 1px solid #edf2f7;
}

.customers-table th {
    background: #f7fafc;
    color: #4a5568;
}

.membership {
    font-family: monospace;
    background: #edf2f7;
    padding: 2px 8px;
    border-radius: 4px;
}

.btn-primary {
    padding: 8px 14px;
    border-radius: 6px;
    border: none;
    background: #3182ce;
    color: white;
    cursor: pointer;
}

.btn-link {
    background: none;
    border: none;
    color: #3182ce;
    cursor: pointer;
}

.empty-state {
    padding: 24px;
    text-align: center;
    color: #718096;
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 12px;
    margin-top: 16px;
}

.pagination button {
    padding: 6px 12px;
    border: 1px solid #e2e8f0;
    background: white;
    border-radius: 6px;
    cursor: pointer;
}

.pagination button:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}
</style>
