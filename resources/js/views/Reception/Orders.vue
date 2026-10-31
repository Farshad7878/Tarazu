<template>
    <div class="orders-page">
        <div class="page-header">
            <div class="header-content">
                <h1><IconPackage class="emo-icon" aria-hidden="true" /> سفارشات من</h1>
                <p>تاریخچه و مدیریت سفارشات ثبت شده</p>
            </div>
            <div class="header-actions">
                <router-link to="/reception/new-order" class="btn-primary">
                    <IconPlus class="emo-icon" aria-hidden="true" /> ثبت سفارش جدید
                </router-link>
            </div>
        </div>

        <!-- فیلترها و جستجو -->
        <div class="filters-section">
            <div class="filter-row">
                <div class="search-box">
                    <input
                        type="text"
                        v-model="filters.search"
                        placeholder="جستجو بر اساس شماره سفارش یا نام مشتری..."
                        @input="handleSearch"
                        class="search-input"
                    >
                    <span class="search-icon"><IconSearch class="emo-icon" aria-hidden="true" /></span>
                </div>

                <div class="filter-group">
                    <label>بازه زمانی:</label>
                    <select v-model="filters.date_range" @change="fetchOrders(1)">
                        <option value="today">امروز</option>
                        <option value="yesterday">دیروز</option>
                        <option value="week">هفته جاری</option>
                        <option value="month">ماه جاری</option>
                        <option value="all">همه زمان</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>مرتب سازی:</label>
                    <select v-model="filters.sort" @change="fetchOrders(1)">
                        <option value="newest">جدیدترین</option>
                        <option value="oldest">قدیمی‌ترین</option>
                        <option value="highest">بیشترین مبلغ</option>
                        <option value="lowest">کمترین مبلغ</option>
                    </select>
                </div>

                <button @click="resetFilters" class="btn-outline">
                    <IconRefreshCw class="emo-icon" aria-hidden="true" /> بازنشانی فیلترها
                </button>
            </div>
        </div>

        <!-- آمار سریع -->
        <div class="quick-stats">
            <div class="stat-card">
                <div class="stat-icon"><IconPackage class="emo-icon" aria-hidden="true" /></div>
                <div class="stat-info">
                    <div class="stat-value">{{ stats.total_orders || 0 }}</div>
                    <div class="stat-label">کل سفارشات</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><IconCoins class="emo-icon" aria-hidden="true" /></div>
                <div class="stat-info">
                    <div class="stat-value">{{ formatPrice(stats.total_revenue || 0) }}</div>
                    <div class="stat-label">کل درآمد</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><IconTicket class="emo-icon" aria-hidden="true" /></div>
                <div class="stat-info">
                    <div class="stat-value">{{ formatPrice(stats.total_discounts || 0) }}</div>
                    <div class="stat-label">تخفیف اعمال شده</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><IconUsers class="emo-icon" aria-hidden="true" /></div>
                <div class="stat-info">
                    <div class="stat-value">{{ stats.unique_customers || 0 }}</div>
                    <div class="stat-label">مشتریان منحصر به فرد</div>
                </div>
            </div>
        </div>

        <!-- لیست سفارشات -->
        <div class="orders-container">
            <div class="table-container">
                <div class="table-scroll"><table class="orders-table">
                    <thead>
                        <tr>
                            <th>شماره سفارش</th>
                            <th>مشتری</th>
                            <th>تعداد آیتم‌ها</th>
                            <th>مبلغ کل</th>
                            <th>تخفیف</th>
                            <th>تاریخ ثبت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="order in orders" :key="order.id">
                            <td data-label="شماره سفارش">
                                <span class="order-number">#{{ order.order_number }}</span>
                            </td>
                            <td data-label="مشتری">
                                <div class="customer-info">
                                    <div class="customer-name">{{ order.customer_name }}</div>
                                    <div class="customer-membership">{{ order.customer_membership }}</div>
                                </div>
                            </td>
                            <td data-label="تعداد آیتم‌ها">
                                <span class="items-count">{{ order.items_count }} عدد</span>
                            </td>
                            <td data-label="مبلغ کل">
                                <span class="price total-amount">{{ formatPrice(order.total_amount) }}</span>
                            </td>
                            <td data-label="تخفیف">
                                <span v-if="order.discount_amount > 0" class="discount-badge">
                                    -{{ formatPrice(order.discount_amount) }}
                                </span>
                                <span v-else class="no-discount">--</span>
                            </td>
                            <td data-label="تاریخ ثبت">
                                <div class="date-time">
                                    <div class="date">{{ order.created_date }}</div>
                                    <div class="time">{{ order.created_time }}</div>
                                </div>
                            </td>
                            <td data-label="عملیات">
                                <div class="actions">
                                    <button 
                                        @click="viewOrderDetails(order)"
                                        class="btn-view"
                                        title="مشاهده جزئیات"
                                    >
                                        <IconEye class="emo-icon" aria-hidden="true" />
                                    </button>
                                    <button 
                                        v-if="canCancelOrder(order)"
                                        @click="cancelOrder(order)"
                                        class="btn-cancel"
                                        title="لغو سفارش"
                                    >
                                        <IconX class="emo-icon" aria-hidden="true" />
                                    </button>
                                    <button 
                                        @click="reprintOrder(order)"
                                        class="btn-print"
                                        title="چاپ مجدد"
                                    >
                                        <IconPrinter class="emo-icon" aria-hidden="true" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table></div>

                <!-- وضعیت loading -->
                <div v-if="loading" class="loading-state">
                    <div class="loading-spinner"></div>
                    <span>در حال بارگذاری سفارشات...</span>
                </div>

                <!-- وضعیت خالی -->
                <div v-if="!loading && orders.length === 0" class="empty-state">
                    <div class="empty-icon"><IconFileText class="emo-icon" aria-hidden="true" /></div>
                    <h3>هیچ سفارشی یافت نشد</h3>
                    <p>هنوز هیچ سفارشی ثبت نکرده‌اید یا سفارشی با فیلترهای انتخابی شما مطابقت ندارد.</p>
                    <router-link to="/reception/new-order" class="btn-primary">
                        <IconPlus class="emo-icon" aria-hidden="true" /> ثبت اولین سفارش
                    </router-link>
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
                
                <div class="pagination-pages">
                    <button 
                        v-for="page in visiblePages"
                        :key="page"
                        @click="changePage(page)"
                        :class="['page-btn', { active: page === pagination.current_page }]"
                    >
                        {{ page }}
                    </button>
                </div>
                
                <button 
                    @click="changePage(pagination.current_page + 1)"
                    :disabled="pagination.current_page === pagination.last_page"
                    class="pagination-btn"
                >
                    بعدی
                </button>

                <div class="pagination-info">
                    نمایش {{ pagination.from }}-{{ pagination.to }} از {{ pagination.total }} سفارش
                </div>
            </div>
        </div>

        <!-- Modal جزئیات سفارش -->
        <div v-if="showOrderModal && selectedOrder" class="modal-overlay">
            <div class="modal modal-large">
                <div class="modal-header">
                    <h3>
                        <IconClipboardList class="emo-icon" aria-hidden="true" /> جزئیات سفارش #{{ selectedOrder.order_number }}
                        <span v-if="selectedOrder.returned_status !== 'none'" class="return-badge" :class="selectedOrder.returned_status">
                            {{ selectedOrder.returned_status === 'full' ? 'بازگشت کامل' : 'بازگشت جزئی' }}
                        </span>
                    </h3>
                    <button @click="closeOrderModal" class="close-btn">×</button>
                </div>
                
                <div class="modal-content">
                    <!-- اطلاعات مشتری -->
                    <div class="detail-section">
                        <h4><IconUser class="emo-icon" aria-hidden="true" /> اطلاعات مشتری</h4>
                        <div class="detail-grid">
                            <div class="detail-item">
                                <span class="label">نام کامل:</span>
                                <span class="value">{{ selectedOrder.customer_name }}</span>
                            </div>
                            <div class="detail-item">
                                <span class="label">شماره اشتراک:</span>
                                <span class="value">{{ selectedOrder.customer_membership }}</span>
                            </div>
                            <div class="detail-item">
                                <span class="label">تلفن:</span>
                                <span class="value">{{ selectedOrder.customer_phone }}</span>
                            </div>
                            <div class="detail-item">
                                <span class="label">تاریخ ثبت:</span>
                                <span class="value">{{ selectedOrder.created_date }} - {{ selectedOrder.created_time }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- آیتم‌های سفارش -->
                    <div class="detail-section">
                        <h4><IconShoppingCart class="emo-icon" aria-hidden="true" /> آیتم‌های سفارش</h4>
                        <div class="order-items">
                            <div 
                                v-for="item in selectedOrder.items"
                                :key="item.id"
                                class="order-item"
                            >
                                <div class="item-info">
                                    <span class="item-name">{{ item.product_name }}</span>
                                    <span class="item-type">{{ item.product_category }}</span>
                                </div>
                                <div class="item-details">
                                    <span class="item-quantity">{{ item.quantity }} عدد</span>
                                    <span v-if="item.returned_quantity > 0" class="item-returned">{{ item.returned_quantity }} عدد برگشتی</span>
                                    <span class="item-price">{{ formatPrice(item.unit_price) }}</span>
                                    <span class="item-total">{{ formatPrice(item.total_price) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- خلاصه مالی -->
                    <div class="detail-section">
                        <h4><IconCoins class="emo-icon" aria-hidden="true" /> خلاصه مالی</h4>
                        <div class="summary-grid">
                            <div class="summary-item">
                                <span>جمع کل:</span>
                                <span class="price">{{ formatPrice(selectedOrder.subtotal) }}</span>
                            </div>
                            <div v-if="selectedOrder.discount_amount > 0" class="summary-item discount">
                                <span>تخفیف:</span>
                                <span class="discount-amount">-{{ formatPrice(selectedOrder.discount_amount) }}</span>
                            </div>
                            <div v-if="selectedOrder.refunded_amount > 0" class="summary-item refund">
                                <span>بازگشتی (ریفاند):</span>
                                <span class="refund-amount">-{{ formatPrice(selectedOrder.refunded_amount) }}</span>
                            </div>
                            <div v-if="selectedOrder.tax_amount > 0" class="summary-item vat">
                                <span>شامل مالیات ارزش افزوده ({{ formatPrice(selectedOrder.tax_rate) }}٪):</span>
                                <span>{{ formatPrice(selectedOrder.tax_amount) }}</span>
                            </div>
                            <div class="summary-item">
                                <span>روش پرداخت:</span>
                                <span>{{ selectedOrder.payment_method === 'card' ? 'کارت' : 'نقد' }}</span>
                            </div>
                            <div v-if="selectedOrder.loyalty_points_earned > 0" class="summary-item">
                                <span>امتیاز باقی‌مانده‌ی این سفارش:</span>
                                <span>{{ selectedOrder.loyalty_points_earned }}</span>
                            </div>
                            <div class="summary-item total">
                                <span>مبلغ نهایی{{ selectedOrder.refunded_amount > 0 ? ' (خالص)' : '' }}:</span>
                                <span class="total-amount">{{ formatPrice(selectedOrder.total_amount) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- یادداشت‌ها -->
                    <div v-if="selectedOrder.notes" class="detail-section">
                        <h4><IconFileText class="emo-icon" aria-hidden="true" /> یادداشت سفارش</h4>
                        <div class="notes-content">
                            {{ selectedOrder.notes }}
                        </div>
                    </div>

                    <!-- تخفیف‌های اعمال شده -->
                    <div v-if="selectedOrder.applied_discounts && selectedOrder.applied_discounts.length > 0" class="detail-section">
                        <h4><IconTicket class="emo-icon" aria-hidden="true" /> تخفیف‌های اعمال شده</h4>
                        <div class="discounts-list">
                            <div 
                                v-for="discount in selectedOrder.applied_discounts"
                                :key="discount.id"
                                class="discount-item"
                            >
                                <div class="discount-name">{{ discount.name }}</div>
                                <div class="discount-message">{{ discount.message }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- تعویض کالا -->
                    <div v-if="(selectedOrder.exchanges && selectedOrder.exchanges.length > 0) || selectedOrder.exchange_of" class="detail-section">
                        <h4><IconRefreshCw class="emo-icon" aria-hidden="true" /> تعویض کالا</h4>
                        <div v-if="selectedOrder.exchange_of" class="return-entry-meta">
                            این سفارش حاصل تعویض کالا از سفارش #{{ selectedOrder.exchange_of }} است.
                        </div>
                        <div v-for="ex in selectedOrder.exchanges" :key="ex.new_order_number" class="return-entry-meta">
                            {{ ex.created_at }} — سفارش جدید #{{ ex.new_order_number }} —
                            <template v-if="ex.difference > 0">مشتری {{ formatPrice(ex.difference) }} پرداخت کرد</template>
                            <template v-else-if="ex.difference < 0">{{ formatPrice(-ex.difference) }} به مشتری بازپرداخت شد</template>
                            <template v-else>بدون پرداخت اضافی</template>
                        </div>
                    </div>

                    <!-- تاریخچه‌ی بازگشت کالا -->
                    <div v-if="selectedOrder.returns && selectedOrder.returns.length > 0" class="detail-section">
                        <h4><IconUndo2 class="emo-icon" aria-hidden="true" /> تاریخچه‌ی بازگشت کالا</h4>
                        <div class="return-history">
                            <div v-for="ret in selectedOrder.returns" :key="ret.id" class="return-entry">
                                <div class="return-entry-header">
                                    <span>{{ ret.created_at }} — {{ ret.user_name || '—' }}</span>
                                    <span class="return-entry-refund">ریفاند: {{ formatPrice(ret.refund_amount) }}<template v-if="ret.tax_refund > 0"> (مالیات {{ formatPrice(ret.tax_refund) }})</template></span>
                                </div>
                                <div class="return-entry-lines">
                                    <span v-for="line in ret.items" :key="line.product_name + line.quantity">
                                        {{ line.product_name }} × {{ line.quantity }}
                                    </span>
                                </div>
                                <div class="return-entry-meta">
                                    دلیل: {{ ret.reason }} —
                                    {{ ret.restock ? 'به انبار برگشت' : 'بدون ورود به انبار' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button v-if="selectedOrder.can_return" @click="openReturnModal" class="btn-warning">
                        <IconUndo2 class="emo-icon" aria-hidden="true" /> بازگشت کالا
                    </button>
                    <button v-if="selectedOrder.can_return" @click="showExchangeModal = true" class="btn-primary">
                        <IconRefreshCw class="emo-icon" aria-hidden="true" /> تعویض کالا
                    </button>
                    <button @click="reprintOrder(selectedOrder)" class="btn-primary">
                        <IconPrinter class="emo-icon" aria-hidden="true" /> چاپ فاکتور
                    </button>
                    <button @click="closeOrderModal" class="btn-secondary">
                        بستن
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal تعویض کالا -->
        <ExchangeModal
            v-if="showExchangeModal && selectedOrder"
            :order="selectedOrder"
            @close="showExchangeModal = false"
            @done="onExchangeDone"
        />

        <!-- Modal بازگشت کالا -->
        <div v-if="showReturnModal && selectedOrder" class="modal-overlay">
            <div class="modal modal-large">
                <div class="modal-header">
                    <h3><IconUndo2 class="emo-icon" aria-hidden="true" /> بازگشت کالا — سفارش #{{ selectedOrder.order_number }}</h3>
                    <button @click="closeReturnModal" class="close-btn" :disabled="returnSubmitting">×</button>
                </div>

                <div class="modal-content">
                    <p class="return-hint">
                        فقط کالاهای این سفارش تا {{ selectedOrder.return_window_days }} روز پس از خرید قابل بازگشت هستند.
                        مبلغ ریفاند بر اساس تخفیف سفارش محاسبه می‌شود.
                    </p>

                    <div class="detail-section">
                        <h4><IconShoppingCart class="emo-icon" aria-hidden="true" /> کالاهای قابل بازگشت</h4>
                        <div class="return-lines">
                            <div v-for="item in returnableItems" :key="item.id" class="return-line">
                                <div class="item-info">
                                    <span class="item-name">{{ item.product_name }}</span>
                                    <span class="item-type">
                                        قیمت واحد: {{ formatPrice(item.unit_price) }} — باقی‌مانده برای بازگشت: {{ item.returnable_quantity }} عدد
                                    </span>
                                </div>
                                <div class="return-line-input">
                                    <label :for="`return-qty-${item.id}`">تعداد:</label>
                                    <input
                                        :id="`return-qty-${item.id}`"
                                        type="number"
                                        min="0"
                                        :max="item.returnable_quantity"
                                        v-model.number="returnForm.quantities[item.id]"
                                        :disabled="returnSubmitting"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="detail-section">
                        <p v-if="hasInvalidReturnQuantity" class="return-hint return-hint-error">
                            تعداد هر کالا باید عدد صحیحی بین ۰ و باقی‌مانده‌ی قابل بازگشت باشد.
                        </p>
                        <div class="form-group">
                            <label for="return-reason">دلیل بازگشت <span class="required">*</span></label>
                            <input
                                id="return-reason"
                                type="text"
                                maxlength="255"
                                v-model="returnForm.reason"
                                placeholder="مثلاً: کالای معیوب، اشتباه در سفارش"
                                :disabled="returnSubmitting"
                            />
                        </div>

                        <label class="restock-toggle">
                            <input type="checkbox" v-model="returnForm.restock" :disabled="returnSubmitting" />
                            کالا به موجودی انبار برگردد
                        </label>
                    </div>

                    <div class="detail-section">
                        <div class="summary-grid">
                            <div class="summary-item total">
                                <span>ریفاند تقریبی:</span>
                                <span class="total-amount">{{ formatPrice(refundEstimate) }}</span>
                            </div>
                        </div>
                        <p class="return-hint">مبلغ نهایی را سرور هنگام ثبت محاسبه می‌کند.</p>
                    </div>
                </div>

                <div class="modal-footer">
                    <button @click="submitReturn" class="btn-warning" :disabled="!canSubmitReturn">
                        {{ returnSubmitting ? 'در حال ثبت...' : 'ثبت بازگشت' }}
                    </button>
                    <button @click="closeReturnModal" class="btn-secondary" :disabled="returnSubmitting">
                        انصراف
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ClipboardList as IconClipboardList, Coins as IconCoins, Eye as IconEye, FileText as IconFileText, Package as IconPackage, Plus as IconPlus, Printer as IconPrinter, RefreshCw as IconRefreshCw, Search as IconSearch, ShoppingCart as IconShoppingCart, Ticket as IconTicket, Undo2 as IconUndo2, User as IconUser, Users as IconUsers, X as IconX } from 'lucide-vue-next';
import { ref, reactive, onMounted, computed } from 'vue';
import axios from 'axios';
import { useNotificationStore } from '../../stores/notifications';

import { formatPrice } from '../../utils/format';
import { printReceipt } from '../../utils/receipt';
import ExchangeModal from './ExchangeModal.vue';

export default {
    name: 'ReceptionOrders',
    components: { IconClipboardList, IconCoins, IconEye, IconFileText, IconPackage, IconPlus, IconPrinter, IconRefreshCw, IconSearch, IconShoppingCart, IconTicket, IconUndo2, IconUser, IconUsers, IconX, 
        ExchangeModal
    },
    setup() {
        const notificationStore = useNotificationStore();

        // داده‌ها
        const orders = ref([]);
        const loading = ref(false);
        const showOrderModal = ref(false);
        const selectedOrder = ref(null);

        // بازگشت کالا
        const showReturnModal = ref(false);
        // تعویض کالا
        const showExchangeModal = ref(false);
        const returnSubmitting = ref(false);
        const returnForm = reactive({ quantities: {}, reason: '', restock: true });

        // فیلترها
        const filters = ref({
            search: '',
            date_range: 'today',
            sort: 'newest'
        });

        // آمار
        const stats = ref({
            total_orders: 0,
            total_revenue: 0,
            total_discounts: 0,
            unique_customers: 0
        });

        // Pagination
        const pagination = ref({
            current_page: 1,
            last_page: 1,
            per_page: 15,
            total: 0,
            from: 0,
            to: 0
        });

        // Computed
        const visiblePages = computed(() => {
            const current = pagination.value.current_page;
            const last = pagination.value.last_page;
            const delta = 2;
            const range = [];
            const rangeWithDots = [];

            for (let i = Math.max(2, current - delta); i <= Math.min(last - 1, current + delta); i++) {
                range.push(i);
            }

            if (current - delta > 2) {
                rangeWithDots.push(1, '...');
            } else {
                rangeWithDots.push(1);
            }

            rangeWithDots.push(...range);

            if (current + delta < last - 1) {
                rangeWithDots.push('...', last);
            } else if (last > 1) {
                rangeWithDots.push(last);
            }

            return rangeWithDots;
        });

        // متدها
        const fetchOrders = async (page = 1) => {
            loading.value = true;
            try {
                const params = {
                    page,
                    ...filters.value
                };

                const response = await axios.get('/api/reception/orders', { params });
                orders.value = response.data.orders;
                pagination.value = response.data.pagination;
                stats.value = response.data.stats || {};
            } catch (error) {
                console.error('Error fetching orders:', error);
                notificationStore.addNotification({
                    type: 'error',
                    title: 'خطا',
                    message: 'خطا در بارگذاری سفارشات'
                });
            } finally {
                loading.value = false;
            }
        };

        const fetchOrderDetails = async (orderId) => {
            try {
                const response = await axios.get(`/api/reception/orders/${orderId}`);
                return response.data.order;
            } catch (error) {
                console.error('Error fetching order details:', error);
                throw error;
            }
        };

        const viewOrderDetails = async (order) => {
            try {
                const orderDetails = await fetchOrderDetails(order.id);
                selectedOrder.value = orderDetails;
                showOrderModal.value = true;
            } catch {
                notificationStore.addNotification({
                    type: 'error',
                    title: 'خطا',
                    message: 'خطا در بارگذاری جزئیات سفارش'
                });
            }
        };

        const closeOrderModal = () => {
            showOrderModal.value = false;
            selectedOrder.value = null;
        };

        const returnableItems = computed(() =>
            (selectedOrder.value?.items || []).filter(item => item.returnable_quantity > 0)
        );

        // تخمین سمت کلاینت؛ مبلغ قطعی را سرور حساب می‌کند
        const refundEstimate = computed(() => {
            const order = selectedOrder.value;
            if (!order) return 0;
            const ratio = order.subtotal > 0 ? order.discount_amount / order.subtotal : 0;
            return returnableItems.value.reduce((sum, item) => {
                const qty = Math.min(Math.max(Number(returnForm.quantities[item.id]) || 0, 0), item.returnable_quantity);
                return sum + item.unit_price * qty * (1 - ratio);
            }, 0);
        });

        const selectedReturnLines = computed(() =>
            returnableItems.value
                .map(item => ({ order_item_id: item.id, quantity: Number(returnForm.quantities[item.id]) || 0 }))
                .filter(line => line.quantity > 0)
        );

        // هر تعداد باید عدد صحیح باشد و از باقی‌مانده‌ی قابل بازگشت بیشتر نشود؛ وگرنه ثبت غیرفعال می‌ماند
        const hasInvalidReturnQuantity = computed(() =>
            returnableItems.value.some(item => {
                const qty = Number(returnForm.quantities[item.id]) || 0;
                return !Number.isInteger(qty) || qty < 0 || qty > item.returnable_quantity;
            })
        );

        const canSubmitReturn = computed(() =>
            !returnSubmitting.value
            && !hasInvalidReturnQuantity.value
            && selectedReturnLines.value.length > 0
            && returnForm.reason.trim().length > 0
        );

        const openReturnModal = () => {
            returnForm.quantities = {};
            returnableItems.value.forEach(item => {
                returnForm.quantities[item.id] = 0;
            });
            returnForm.reason = '';
            returnForm.restock = true;
            showReturnModal.value = true;
        };

        const closeReturnModal = () => {
            if (returnSubmitting.value) return;
            showReturnModal.value = false;
        };

        const firstErrorMessage = (error, fallback) => {
            const errors = error.response?.data?.errors;
            if (errors) {
                const first = Object.values(errors).flat()[0];
                if (first) return first;
            }
            return error.response?.data?.message || fallback;
        };

        const onExchangeDone = async (data) => {
            const diff = Number(data.difference) || 0;
            const money = diff > 0
                ? `مشتری ${formatPrice(diff)} پرداخت کرد.`
                : diff < 0
                    ? `${formatPrice(-diff)} به مشتری بازپرداخت شد.`
                    : 'بدون پرداخت اضافی.';

            notificationStore.addNotification({
                type: 'success',
                title: 'موفقیت',
                message: `${data.message} ${money}`
            });

            showExchangeModal.value = false;
            selectedOrder.value = await fetchOrderDetails(selectedOrder.value.id);
            fetchOrders(pagination.value.current_page);
        };

        const submitReturn = async () => {
            if (!canSubmitReturn.value) return;

            returnSubmitting.value = true;
            try {
                const response = await axios.post(`/api/reception/orders/${selectedOrder.value.id}/returns`, {
                    items: selectedReturnLines.value,
                    reason: returnForm.reason.trim(),
                    restock: returnForm.restock,
                });

                notificationStore.addNotification({
                    type: 'success',
                    title: 'موفقیت',
                    message: `${response.data.message} ریفاند: ${formatPrice(response.data.return.refund_amount)}`
                });

                showReturnModal.value = false;
                selectedOrder.value = await fetchOrderDetails(selectedOrder.value.id);
                fetchOrders(pagination.value.current_page);
            } catch (error) {
                console.error('Error submitting return:', error);
                notificationStore.addNotification({
                    type: 'error',
                    title: 'خطا',
                    message: firstErrorMessage(error, 'خطا در ثبت بازگشت کالا')
                });
            } finally {
                returnSubmitting.value = false;
            }
        };

        const cancelOrder = async (order) => {
            if (!confirm(`آیا از لغو سفارش #${order.order_number} اطمینان دارید؟`)) {
                return;
            }

            try {
                await axios.delete(`/api/reception/orders/${order.id}`);
                
                notificationStore.addNotification({
                    type: 'success',
                    title: 'موفقیت',
                    message: `سفارش #${order.order_number} با موفقیت لغو شد`
                });

                fetchOrders(pagination.value.current_page);
            } catch (error) {
                console.error('Error canceling order:', error);
                const message = error.response?.data?.message || 'خطا در لغو سفارش';
                notificationStore.addNotification({
                    type: 'error',
                    title: 'خطا',
                    message: message
                });
            }
        };

        const reprintOrder = (order) => {
            // رسید از همان داده‌ی جزئیات سفارش ساخته می‌شود و پنجره‌ی چاپ باز می‌شود
            if (!order) return;
            printReceipt(order);
        };

        const canCancelOrder = (order) => {
            // فقط سفارش‌های امروز قابل لغو هستند
            const orderDate = new Date(order.created_at);
            const today = new Date();
            return orderDate.toDateString() === today.toDateString();
        };

        const changePage = (page) => {
            if (page >= 1 && page <= pagination.value.last_page && page !== '...') {
                fetchOrders(page);
            }
        };

        const handleSearch = () => {
            clearTimeout(window.searchTimeout);
            window.searchTimeout = setTimeout(() => {
                fetchOrders(1);
            }, 500);
        };

        const resetFilters = () => {
            filters.value = {
                search: '',
                date_range: 'today',
                sort: 'newest'
            };
            fetchOrders(1);
        };

        // lifecycle
        onMounted(() => {
            fetchOrders();
        });

        return {
            // داده‌ها
            orders,
            loading,
            showOrderModal,
            selectedOrder,
            filters,
            stats,
            pagination,

            // computed
            visiblePages,

            // متدها
            fetchOrders,
            viewOrderDetails,
            closeOrderModal,
            showReturnModal,
            showExchangeModal,
            onExchangeDone,
            returnSubmitting,
            returnForm,
            returnableItems,
            refundEstimate,
            canSubmitReturn,
            hasInvalidReturnQuantity,
            openReturnModal,
            closeReturnModal,
            submitReturn,
            cancelOrder,
            reprintOrder,
            canCancelOrder,
            changePage,
            handleSearch,
            resetFilters,
            formatPrice
        };
    }
}
</script>

<style scoped src="./Orders.css"></style>