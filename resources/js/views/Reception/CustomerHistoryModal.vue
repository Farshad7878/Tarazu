<template>
    <div class="modal-overlay">
        <div class="modal modal-large">
            <div class="modal-header">
                <h3>📊 تاریخچه خریدهای {{ customer.full_name }}</h3>
                <button @click="$emit('close')" class="close-btn">×</button>
            </div>
            
            <div class="modal-content">
                <!-- خلاصه اطلاعات مشتری -->
                <div class="customer-summary">
                    <div class="summary-card">
                        <div class="summary-item">
                            <span class="label">شماره اشتراک:</span>
                            <span class="value">{{ customer.membership_number }}</span>
                        </div>
                        <div class="summary-item">
                            <span class="label">تلفن:</span>
                            <span class="value">{{ customer.phone }}</span>
                        </div>
                        <div class="summary-item">
                            <span class="label">مجموع خریدها:</span>
                            <span class="value price">{{ formatPrice(customer.total_purchases) }}</span>
                        </div>
                        <div class="summary-item">
                            <span class="label">تعداد سفارشات:</span>
                            <span class="value">{{ orders.length }}</span>
                        </div>
                    </div>
                </div>

                <!-- فیلترها -->
                <div class="filters-section">
                    <div class="filter-group">
                        <label>بازه زمانی:</label>
                        <select v-model="filters.period" @change="fetchOrders">
                            <option value="all">همه زمان</option>
                            <option value="month">ماه جاری</option>
                            <option value="last_month">ماه گذشته</option>
                            <option value="year">سال جاری</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>مرتب سازی:</label>
                        <select v-model="filters.sort" @change="fetchOrders">
                            <option value="newest">جدیدترین</option>
                            <option value="oldest">قدیمی‌ترین</option>
                            <option value="highest">بیشترین مبلغ</option>
                            <option value="lowest">کمترین مبلغ</option>
                        </select>
                    </div>
                </div>

                <!-- لیست سفارشات -->
                <div class="orders-section">
                    <div v-if="loading" class="loading-state">
                        <div class="loading-spinner"></div>
                        <span>در حال بارگذاری تاریخچه...</span>
                    </div>

                    <div v-else-if="orders.length === 0" class="empty-state">
                        <div class="empty-icon">📝</div>
                        <h4>هیچ سفارشی یافت نشد</h4>
                        <p>این مشتری تاکنون خریدی انجام نداده است.</p>
                    </div>

                    <div v-else class="orders-list">
                        <div 
                            v-for="order in orders"
                            :key="order.id"
                            class="order-card"
                        >
                            <div class="order-header">
                                <div class="order-info">
                                    <span class="order-number">#{{ order.order_number }}</span>
                                    <span class="order-date">{{ formatDate(order.created_at) }}</span>
                                </div>
                                <div class="order-amount">
                                    <span class="total-amount">{{ formatPrice(order.total_amount) }}</span>
                                    <span v-if="order.discount_amount > 0" class="discount-badge">
                                        -{{ formatPrice(order.discount_amount) }}
                                    </span>
                                </div>
                            </div>

                            <div class="order-details">
                                <div class="order-items">
                                    <div 
                                        v-for="item in order.items"
                                        :key="item.id"
                                        class="order-item"
                                    >
                                        <span class="item-name">{{ item.product_name }}</span>
                                        <span class="item-quantity">× {{ item.quantity }}</span>
                                        <span class="item-price">{{ formatPrice(item.total_price) }}</span>
                                    </div>
                                </div>

                                <div class="order-meta">
                                    <span class="reception-info">
                                        ثبت شده توسط: {{ order.reception_name }}
                                    </span>
                                    <span v-if="order.notes" class="order-notes" :title="order.notes">
                                        📝
                                    </span>
                                </div>
                            </div>
                        </div>
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
            </div>

            <div class="modal-footer">
                <button @click="$emit('close')" class="btn-primary">
                    بستن
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import axios from 'axios';

import { formatPrice, formatDate } from '../../utils/format';
export default {
    name: 'CustomerHistoryModal',
    props: {
        customer: {
            type: Object,
            required: true
        }
    },
    emits: ['close'],
    setup(props) {
        const orders = ref([]);
        const loading = ref(false);
        
        const filters = ref({
            period: 'all',
            sort: 'newest'
        });

        const pagination = ref({
            current_page: 1,
            last_page: 1,
            per_page: 10,
            total: 0
        });

        const fetchOrders = async (page = 1) => {
            loading.value = true;
            try {
                const params = {
                    page,
                    customer_id: props.customer.id,
                    ...filters.value
                };

                const response = await axios.get('/api/reception/customer-orders', { params });
                orders.value = response.data.orders;
                pagination.value = response.data.pagination;
            } catch (error) {
                console.error('Error fetching customer orders:', error);
            } finally {
                loading.value = false;
            }
        };

        const changePage = (page) => {
            if (page >= 1 && page <= pagination.value.last_page) {
                fetchOrders(page);
            }
        };

        onMounted(() => {
            fetchOrders();
        });

        return {
            orders,
            loading,
            filters,
            pagination,
            fetchOrders,
            changePage,
            formatPrice,
            formatDate
        };
    }
}
</script>

<style scoped>
.modal-large {
    max-width: 800px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
}

.modal-content {
    flex: 1;
    overflow-y: auto;
    padding: 0 24px;
}

.modal-footer {
    padding: 20px 24px;
    border-top: 1px solid #e2e8f0;
    display: flex;
    justify-content: flex-end;
}

.customer-summary {
    margin-bottom: 24px;
}

.summary-card {
    background: #f7fafc;
    border-radius: 8px;
    padding: 16px;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(200px, 100%), 1fr));
    gap: 12px;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.summary-item .label {
    color: #4a5568;
    font-size: 0.875rem;
}

.summary-item .value {
    color: #2d3748;
    font-weight: 600;
}

.price {
    color: #38a169;
}

.filters-section {
    display: flex;
    gap: 16px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.filter-group label {
    color: #4a5568;
    font-size: 0.875rem;
    font-weight: 500;
}

.filter-group select {
    padding: 8px 12px;
    border: 2px solid #e2e8f0;
    border-radius: 6px;
    background: white;
    min-width: 150px;
}

.orders-section {
    flex: 1;
}

.loading-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px;
    color: #718096;
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: #718096;
}

.empty-icon {
    font-size: 3rem;
    margin-bottom: 16px;
    opacity: 0.5;
}

.orders-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.order-card {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
    background: white;
}

.order-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px;
    background: #f7fafc;
    border-bottom: 1px solid #e2e8f0;
}

.order-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.order-number {
    font-weight: 600;
    color: #2d3748;
    font-size: 1.1rem;
}

.order-date {
    color: #718096;
    font-size: 0.875rem;
}

.order-amount {
    display: flex;
    align-items: center;
    gap: 8px;
}

.total-amount {
    font-weight: 700;
    color: #3182ce;
    font-size: 1.2rem;
}

.discount-badge {
    background: #fed7d7;
    color: #c53030;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
}

.order-details {
    padding: 16px;
}

.order-items {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 12px;
}

.order-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #f7fafc;
}

.order-item:last-child {
    border-bottom: none;
}

.item-name {
    flex: 1;
    color: #2d3748;
}

.item-quantity {
    color: #718096;
    margin: 0 12px;
    font-size: 0.875rem;
}

.item-price {
    color: #38a169;
    font-weight: 600;
    min-width: 100px;
    text-align: left;
}

.order-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 12px;
    border-top: 1px solid #e2e8f0;
}

.reception-info {
    color: #718096;
    font-size: 0.875rem;
}

.order-notes {
    cursor: help;
    font-size: 1.2rem;
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 16px;
    padding: 20px 0;
    margin-top: 20px;
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

@media (max-width: 768px) {
    .order-header {
        flex-direction: column;
        gap: 12px;
        align-items: flex-start;
    }
    
    .filters-section {
        flex-direction: column;
    }
    
    .filter-group select {
        min-width: auto;
    }
    
    .summary-card {
        grid-template-columns: 1fr;
    }
}
</style>