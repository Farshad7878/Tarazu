<template>
    <div class="customers-page">
        <div class="page-header">
            <h2><IconUsers class="emo-icon" aria-hidden="true" /> مدیریت مشتریان</h2>
            <button @click="showAddModal = true" class="btn-primary">
                <IconPlus class="emo-icon" aria-hidden="true" /> افزودن مشتری جدید
            </button>
        </div>

        <div class="filters">
            <div class="search-box">
                <input
                    type="text"
                    v-model="search"
                    placeholder="جستجو بر اساس نام، شماره تماس یا شماره اشتراک..."
                    @input="handleSearch"
                >
                <span class="search-icon"><IconSearch class="emo-icon" aria-hidden="true" /></span>
            </div>
        </div>

        <div class="table-container">
            <div class="table-scroll"><table class="customers-table">
                <thead>
                    <tr>
                        <th>شماره اشتراک</th>
                        <th>نام کامل</th>
                        <th>شماره تماس</th>
                        <th>تاریخ تولد</th>
                        <th>مجموع خریدها</th>
                        <th>تعداد سفارشات</th>
                        <th>امتیاز</th>
                        <th>پیامک</th>
                        <th>تاریخ ثبت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="customer in customers" :key="customer.id">
                        <td>
                            <span class="membership-badge">
                                {{ customer.membership_number }}
                            </span>
                        </td>
                        <td>{{ customer.full_name }}</td>
                        <td>{{ customer.phone }}</td>
                        <td>
                            <span v-if="customer.date_of_birth">
                                {{ formatDate(customer.date_of_birth) }}
                            </span>
                            <span v-else class="text-muted">--</span>
                        </td>
                        <td>
                            <span class="price">
                                {{ formatPrice(customer.total_purchases) }}
                            </span>
                        </td>
                        <td>
                            <span class="orders-count">
                                {{ customer.orders_count }}
                            </span>
                        </td>
                        <td>{{ customer.loyalty_points }}</td>
                        <td>
                            <span v-if="customer.sms_opt_out" class="sms-badge sms-off">لغو کرده</span>
                            <span v-else-if="customer.sms_consent" class="sms-badge sms-on">دارد</span>
                            <span v-else class="sms-badge sms-off">ندارد</span>
                        </td>
                        <td>{{ customer.created_at }}</td>
                        <td>
                            <div class="actions">
                                <button 
                                    @click="viewCustomer(customer.id)"
                                    class="btn-view"
                                    title="مشاهده جزئیات"
                                >
                                    <IconEye class="emo-icon" aria-hidden="true" />
                                </button>
                                <button 
                                    @click="editCustomer(customer)"
                                    class="btn-edit"
                                    title="ویرایش"
                                >
                                    <IconPencil class="emo-icon" aria-hidden="true" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table></div>

            <div v-if="loading" class="loading">
                در حال بارگذاری...
            </div>

            <div v-if="!loading && customers.length === 0" class="empty-state">
                <IconFileText class="emo-icon" aria-hidden="true" /> هیچ مشتری یافت نشد
            </div>

            <!-- Pagination -->
            <div v-if="pagination.last_page > 1" class="pagination">
                <button 
                    @click="changePage(pagination.current_page - 1)"
                    :disabled="pagination.current_page === 1"
                    class="pagination-btn"
                >
                    قبلی
                </button>
                
                <span class="pagination-info">
                    صفحه {{ pagination.current_page }} از {{ pagination.last_page }}
                </span>
                
                <button 
                    @click="changePage(pagination.current_page + 1)"
                    :disabled="pagination.current_page === pagination.last_page"
                    class="pagination-btn"
                >
                    بعدی
                </button>
            </div>
        </div>

        <!-- Modal افزودن/ویرایش مشتری -->
        <div v-if="showAddModal || showEditModal" class="modal-overlay">
            <div class="modal">
                <div class="modal-header">
                    <h3>{{ showEditModal ? 'ویرایش مشتری' : 'افزودن مشتری جدید' }}</h3>
                    <button @click="closeModal" class="close-btn">×</button>
                </div>
                
                <form @submit.prevent="submitCustomerForm" class="modal-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label>نام *</label>
                            <input 
                                type="text" 
                                v-model="customerForm.first_name"
                                required
                            >
                        </div>
                        <div class="form-group">
                            <label>نام خانوادگی *</label>
                            <input 
                                type="text" 
                                v-model="customerForm.last_name"
                                required
                            >
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>شماره تماس *</label>
                        <input 
                            type="tel" 
                            v-model="customerForm.phone"
                            required
                            placeholder="09xxxxxxxxx"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label>تاریخ تولد</label>
                        <input 
                            type="date" 
                            v-model="customerForm.date_of_birth"
                        >
                    </div>

                    <div class="form-group consent-group">
                        <label class="consent-label">
                            <input type="checkbox" v-model="customerForm.sms_consent">
                            مشتری موافقت کرده است که پیامک‌های اطلاع‌رسانی و پیشنهادهای ویژه دریافت کند
                        </label>
                    </div>

                    <div class="modal-actions">
                        <button 
                            type="button" 
                            @click="closeModal"
                            class="btn-cancel"
                        >
                            انصراف
                        </button>
                        <button 
                            type="submit" 
                            :disabled="formLoading"
                            class="btn-primary"
                        >
                            {{ formLoading ? 'در حال ذخیره...' : 'ذخیره' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal جزئیات مشتری -->
        <div v-if="showDetailModal" class="modal-overlay">
            <div class="modal modal-lg">
                <div class="modal-header">
                    <h3>جزئیات مشتری</h3>
                    <button @click="showDetailModal = false" class="close-btn">×</button>
                </div>
                
                <div v-if="selectedCustomer" class="customer-details">
                    <div class="detail-section">
                        <h4>اطلاعات شخصی</h4>
                        <div class="detail-grid">
                            <div class="detail-item">
                                <label>شماره اشتراک:</label>
                                <span>{{ selectedCustomer.membership_number }}</span>
                            </div>
                            <div class="detail-item">
                                <label>نام کامل:</label>
                                <span>{{ selectedCustomer.full_name }}</span>
                            </div>
                            <div class="detail-item">
                                <label>شماره تماس:</label>
                                <span>{{ selectedCustomer.phone }}</span>
                            </div>
                            <div class="detail-item">
                                <label>تاریخ تولد:</label>
                                <span>
                                    {{ selectedCustomer.date_of_birth || '--' }}
                                </span>
                            </div>
                            <div class="detail-item">
                                <label>مجموع خریدها:</label>
                                <span class="price">
                                    {{ formatPrice(selectedCustomer.total_purchases) }}
                                </span>
                            </div>
                            <div class="detail-item">
                                <label>خریدهای این ماه:</label>
                                <span class="price">
                                    {{ formatPrice(selectedCustomer.monthly_purchases) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="detail-section">
                        <h4>امتیاز وفاداری</h4>
                        <div class="loyalty-summary">
                            <span>موجودی: <strong>{{ selectedCustomer.loyalty.points_balance }}</strong> امتیاز</span>
                            <span>سطح: <strong>{{ selectedCustomer.loyalty.tier }}</strong> (ضریب {{ selectedCustomer.loyalty.earn_multiplier }})</span>
                            <span>مجموع کسب‌شده: {{ selectedCustomer.loyalty.lifetime_points }}</span>
                            <span>هر {{ formatPrice(selectedCustomer.loyalty.amount_per_point) }} تومان = ۱ امتیاز</span>
                        </div>

                        <form @submit.prevent="submitLoyaltyAdjustment" class="loyalty-adjust">
                            <input type="number" v-model.number="loyaltyForm.points" placeholder="مثلاً 50 یا -20" required>
                            <input type="text" v-model="loyaltyForm.reason" placeholder="دلیل تعدیل (الزامی)" maxlength="255" required>
                            <button type="submit" class="btn-primary" :disabled="loyaltyLoading">ثبت تعدیل</button>
                        </form>
                        <div v-if="loyaltyError" class="loyalty-error">{{ loyaltyError }}</div>

                        <div v-if="selectedCustomer.loyalty_transactions && selectedCustomer.loyalty_transactions.length" class="loyalty-ledger">
                            <div v-for="t in selectedCustomer.loyalty_transactions" :key="t.id" class="loyalty-row">
                                <span>{{ t.created_at }}</span>
                                <span>{{ t.type_label }}</span>
                                <span :class="t.points >= 0 ? 'change-plus' : 'change-minus'">{{ t.points > 0 ? '+' : '' }}{{ t.points }}</span>
                                <span>موجودی: {{ t.balance_after }}</span>
                                <span class="loyalty-reason">{{ t.reason || '' }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="detail-section">
                        <h4>تاریخچه سفارشات</h4>
                        <div v-if="selectedCustomer.orders.length === 0" class="no-orders">
                            هیچ سفارشی ثبت نشده است
                        </div>
                        <div v-else class="orders-list">
                            <div 
                                v-for="order in selectedCustomer.orders" 
                                :key="order.id"
                                class="order-item"
                            >
                                <div class="order-header">
                                    <span class="order-number">
                                        #{{ order.order_number }}
                                    </span>
                                    <span class="order-date">
                                        {{ order.created_at }}
                                    </span>
                                    <span class="order-total">
                                        {{ formatPrice(order.total_amount) }}
                                    </span>
                                </div>
                                <div class="order-items">
                                    <div 
                                        v-for="item in order.items" 
                                        :key="item.id"
                                        class="order-product"
                                    >
                                        {{ item.product_name }} ({{ item.quantity }} عدد) - 
                                        {{ formatPrice(item.total_price) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { Eye as IconEye, FileText as IconFileText, Pencil as IconPencil, Plus as IconPlus, Search as IconSearch, Users as IconUsers } from 'lucide-vue-next';
import { ref, onMounted } from 'vue';
import axios from 'axios';

export default {
    components: { IconEye, IconFileText, IconPencil, IconPlus, IconSearch, IconUsers },
    name: 'Customers',
    setup() {
        const customers = ref([]);
        const loading = ref(false);
        const search = ref('');
        const searchTimeout = ref(null);
        
        const showAddModal = ref(false);
        const showEditModal = ref(false);
        const showDetailModal = ref(false);
        const formLoading = ref(false);
        
        const selectedCustomer = ref(null);
        const customerForm = ref({
            first_name: '',
            last_name: '',
            phone: '',
            date_of_birth: '',
            sms_consent: false
        });

        const loyaltyForm = ref({ points: null, reason: '' });
        const loyaltyLoading = ref(false);
        const loyaltyError = ref('');

        const pagination = ref({
            current_page: 1,
            last_page: 1,
            per_page: 20,
            total: 0
        });

        const fetchCustomers = async (page = 1) => {
            loading.value = true;
            try {
                const params = { page };
                if (search.value) {
                    params.search = search.value;
                }

                const response = await axios.get('/api/admin/customers', { params });
                customers.value = response.data.customers;
                pagination.value = response.data.pagination;
            } catch (error) {
                console.error('Error fetching customers:', error);
            } finally {
                loading.value = false;
            }
        };

        const handleSearch = () => {
            clearTimeout(searchTimeout.value);
            searchTimeout.value = setTimeout(() => {
                fetchCustomers(1);
            }, 500);
        };

        const changePage = (page) => {
            if (page >= 1 && page <= pagination.value.last_page) {
                fetchCustomers(page);
            }
        };

        const submitCustomerForm = async () => {
            formLoading.value = true;
            try {
                const url = showEditModal.value 
                    ? `/api/admin/customers/${selectedCustomer.value.id}`
                    : '/api/admin/customers';
                
                const method = showEditModal.value ? 'put' : 'post';
                
                await axios[method](url, customerForm.value);
                
                closeModal();
                fetchCustomers();
                
                // نمایش پیام موفقیت
            } catch (error) {
                console.error('Error saving customer:', error);
                // نمایش خطا
            } finally {
                formLoading.value = false;
            }
        };

        const viewCustomer = async (customerId) => {
            try {
                const response = await axios.get(`/api/admin/customers/${customerId}`);
                selectedCustomer.value = response.data.customer;
                selectedCustomer.value.orders = response.data.orders;
                selectedCustomer.value.loyalty_transactions = response.data.loyalty_transactions;
                loyaltyForm.value = { points: null, reason: '' };
                loyaltyError.value = '';
                showDetailModal.value = true;
            } catch (error) {
                console.error('Error fetching customer details:', error);
            }
        };

        const editCustomer = (customer) => {
            selectedCustomer.value = customer;
            customerForm.value = {
                first_name: customer.first_name,
                last_name: customer.last_name,
                phone: customer.phone,
                date_of_birth: customer.date_of_birth || '',
                sms_consent: !!customer.sms_consent
            };
            showEditModal.value = true;
        };

        const submitLoyaltyAdjustment = async () => {
            loyaltyLoading.value = true;
            loyaltyError.value = '';
            try {
                await axios.post(`/api/admin/customers/${selectedCustomer.value.id}/loyalty-adjustments`, {
                    points: loyaltyForm.value.points,
                    reason: loyaltyForm.value.reason,
                });
                await viewCustomer(selectedCustomer.value.id);
            } catch (error) {
                const errors = error.response?.data?.errors;
                loyaltyError.value = (errors && Object.values(errors).flat()[0])
                    || error.response?.data?.message
                    || 'خطا در ثبت تعدیل امتیاز';
            } finally {
                loyaltyLoading.value = false;
            }
        };

        const closeModal = () => {
            showAddModal.value = false;
            showEditModal.value = false;
            showDetailModal.value = false;
            selectedCustomer.value = null;
            customerForm.value = {
                first_name: '',
                last_name: '',
                phone: '',
                date_of_birth: ''
            };
        };

        const formatPrice = (price) => {
            return new Intl.NumberFormat('fa-IR').format(price) + ' تومان';
        };

        const formatDate = (dateString) => {
            return new Date(dateString).toLocaleDateString('fa-IR');
        };

        onMounted(() => {
            fetchCustomers();
        });

        return {
            customers,
            loading,
            search,
            showAddModal,
            showEditModal,
            showDetailModal,
            formLoading,
            selectedCustomer,
            customerForm,
            pagination,
            handleSearch,
            changePage,
            submitCustomerForm,
            viewCustomer,
            loyaltyForm,
            loyaltyLoading,
            loyaltyError,
            submitLoyaltyAdjustment,
            editCustomer,
            closeModal,
            formatPrice,
            formatDate
        };
    }
}
</script>

<style scoped>
.sms-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
}

.sms-on {
    background: #c6f6d5;
    color: #22543d;
}

.sms-off {
    background: #edf2f7;
    color: #718096;
}

.consent-label {
    display: flex;
    gap: 8px;
    align-items: flex-start;
    font-size: 0.9rem;
    cursor: pointer;
}

.loyalty-summary {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 12px;
    color: #4a5568;
    font-size: 0.9rem;
}

.loyalty-adjust {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 8px;
}

.loyalty-adjust input[type="number"] {
    width: 130px;
    padding: 6px 8px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
}

.loyalty-adjust input[type="text"] {
    flex: 1;
    min-width: 180px;
    padding: 6px 8px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
}

.loyalty-error {
    color: #c53030;
    font-size: 0.85rem;
    margin-bottom: 8px;
}

.loyalty-ledger {
    max-height: 220px;
    overflow-y: auto;
    font-size: 0.82rem;
}

.loyalty-row {
    display: grid;
    grid-template-columns: 1.3fr 1.2fr 0.6fr 1fr 2fr;
    gap: 8px;
    padding: 6px 0;
    border-bottom: 1px solid #edf2f7;
}

.loyalty-reason {
    color: #718096;
}

.change-plus {
    color: #2f855a;
    font-weight: 600;
}

.change-minus {
    color: #c53030;
    font-weight: 600;
}

.customers-page {
    padding: 20px;
}

.filters {
    margin-bottom: 24px;
}

.search-box {
    position: relative;
    max-width: 400px;
}

.search-box input {
    width: 100%;
    padding: 12px 40px 12px 16px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 1rem;
}

.search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #a0aec0;
}

.table-container {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.customers-table {
    width: 100%;
    border-collapse: collapse;
}

.customers-table th,
.customers-table td {
    padding: 16px;
    text-align: right;
    border-bottom: 1px solid #e2e8f0;
}

.customers-table th {
    background: #f7fafc;
    font-weight: 600;
    color: #4a5568;
}

.membership-badge {
    background: #ebf8ff;
    color: #3182ce;
    padding: 4px 8px;
    border-radius: 6px;
    font-weight: 500;
    font-size: 0.875rem;
}

.price {
    color: #38a169;
    font-weight: 600;
}

.orders-count {
    background: #f0fff4;
    color: #38a169;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.875rem;
}

.actions {
    display: flex;
    gap: 8px;
}

.btn-view, .btn-edit {
    border: none;
    padding: 6px 10px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 1rem;
    transition: background 0.3s ease;
}

.btn-view {
    background: #edf2f7;
}

.btn-view:hover {
    background: #e2e8f0;
}

.btn-edit {
    background: #fef5e7;
}

.btn-edit:hover {
    background: #fed7aa;
}

.loading, .empty-state {
    text-align: center;
    padding: 40px;
    color: #a0aec0;
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 16px;
    padding: 20px;
    border-top: 1px solid #e2e8f0;
}

.pagination-btn {
    background: #f7fafc;
    border: 1px solid #e2e8f0;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.pagination-btn:hover:not(:disabled) {
    background: #edf2f7;
}

.pagination-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.pagination-info {
    color: #4a5568;
    font-weight: 500;
}

.modal-lg {
    max-width: 800px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

/* Customer Details Styles */
.customer-details {
    padding: 24px;
}

.detail-section {
    margin-bottom: 32px;
}

.detail-section h4 {
    color: #2d3748;
    margin-bottom: 16px;
    font-size: 1.125rem;
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(250px, 100%), 1fr));
    gap: 16px;
    background: #f7fafc;
    padding: 20px;
    border-radius: 8px;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border: 1px solid #dfdfdf;
    border-radius: 8px;
    padding: 0.5rem;
    background: #f8f8f8;
}

.detail-item label {
    color: #4a5568;
    font-weight: 500;
}

.detail-item span {
    color: #2d3748;
    font-weight: 600;
}

.orders-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.order-item {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 16px;
}

.order-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f7fafc;
}

.order-number {
    font-weight: 600;
    color: #2d3748;
}

.order-date {
    color: #718096;
    font-size: 0.875rem;
}

.order-total {
    color: #38a169;
    font-weight: 600;
}

.order-product {
    padding: 4px 0;
    color: #4a5568;
    font-size: 0.875rem;
}

.no-orders {
    text-align: center;
    color: #a0aec0;
    padding: 40px;
    background: #f7fafc;
    border-radius: 8px;
}

.text-muted {
    color: #a0aec0;
}
</style>