<template>
    <div class="modal-overlay">
        <div class="modal modal-medium">
            <div class="modal-header">
                <h3><IconCircleCheck class="emo-icon" aria-hidden="true" /> تأیید نهایی سفارش</h3>
                <button @click="$emit('cancel')" class="close-btn">×</button>
            </div>
            
            <div class="modal-content">
                <!-- اطلاعات مشتری -->
                <div class="customer-section">
                    <h4><IconUser class="emo-icon" aria-hidden="true" /> اطلاعات مشتری</h4>
                    <div class="customer-info">
                        <div class="info-row">
                            <span class="label">نام کامل:</span>
                            <span class="value">{{ orderData.customer.full_name }}</span>
                        </div>
                        <div class="info-row">
                            <span class="label">شماره اشتراک:</span>
                            <span class="value">{{ orderData.customer.membership_number }}</span>
                        </div>
                        <div class="info-row">
                            <span class="label">تلفن:</span>
                            <span class="value">{{ orderData.customer.phone }}</span>
                        </div>
                    </div>
                </div>

                <!-- جزئیات سفارش -->
                <div class="order-details-section">
                    <h4><IconShoppingCart class="emo-icon" aria-hidden="true" /> جزئیات سفارش</h4>
                    <div class="order-items">
                        <div 
                            v-for="item in orderData.items"
                            :key="item.product.id"
                            class="order-item"
                        >
                            <div class="item-info">
                                <span class="item-name">{{ item.product.name }}</span>
                                <span class="item-type">{{ item.product.category_name }}</span>
                            </div>
                            <div class="item-details">
                                <span class="item-quantity">{{ item.quantity }} عدد</span>
                                <span class="item-price">{{ formatPrice(item.product.price * item.quantity) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- خلاصه مالی -->
                <div v-if="orderData.offline" class="offline-estimate-note" style="background:#fff7ed;border:1px solid #fdba74;border-radius:8px;padding:8px 12px;margin-bottom:12px;font-size:0.9rem;">
                    <IconWifiOff class="emo-icon" aria-hidden="true" /> حالت آفلاین: تخفیف‌ها و امتیاز وفاداری فعلاً صفر نمایش داده می‌شوند. سرور هنگام همگام‌سازی آن‌ها را محاسبه می‌کند و مبلغ نهایی ممکن است تغییر کند.
                </div>
                <div class="summary-section">
                    <h4><IconCoins class="emo-icon" aria-hidden="true" /> خلاصه مالی</h4>
                    <div class="summary-grid">
                        <div class="summary-item">
                            <span>تعداد آیتم‌ها:</span>
                            <span>{{ orderData.items.length }}</span>
                        </div>
                        <div class="summary-item">
                            <span>تعداد کل:</span>
                            <span>{{ totalQuantity }}</span>
                        </div>
                        <div class="summary-item">
                            <span>جمع کل:</span>
                            <span class="price">{{ formatPrice(orderData.summary.subtotal) }}</span>
                        </div>
                        <div v-if="orderData.summary.discount > 0" class="summary-item discount">
                            <span>تخفیف اعمال شده:</span>
                            <span class="discount-amount">-{{ formatPrice(orderData.summary.discount) }}</span>
                        </div>
                        <div v-if="orderData.summary.gift_amount > 0" class="summary-item discount">
                            <span><IconGift class="emo-icon" aria-hidden="true" /> کارت هدیه</span>
                            <span class="discount-amount">-{{ formatPrice(orderData.summary.gift_amount) }}</span>
                        </div>
                        <div class="summary-item total">
                            <span>مبلغ قابل پرداخت:</span>
                            <span class="total-amount">{{ formatPrice(orderData.summary.total) }}</span>
                        </div>
                        <div v-if="orderData.summary.vat_amount > 0" class="summary-item vat">
                            <span>شامل مالیات ارزش افزوده ({{ formatPrice(orderData.summary.vat_rate) }}٪):</span>
                            <span>{{ formatPrice(orderData.summary.vat_amount) }}</span>
                        </div>
                    </div>
                </div>

                <!-- یادداشت‌ها -->
                <div v-if="orderData.notes" class="notes-section">
                    <h4><IconFileText class="emo-icon" aria-hidden="true" /> یادداشت سفارش</h4>
                    <div class="notes-content">
                        {{ orderData.notes }}
                    </div>
                </div>

                <!-- هشدارها و اطلاعات -->
                <div class="alerts-section">
                    <div class="alert info">
                        <span class="alert-icon">ℹ</span>
                        <div class="alert-content">
                            <strong>توجه:</strong> پس از تأیید، سفارش ثبت شده و امکان ویرایش آن وجود ندارد.
                        </div>
                    </div>
                    
                    <div v-if="hasLowStockItems" class="alert warning">
                        <span class="alert-icon"><IconTriangleAlert class="emo-icon" aria-hidden="true" /></span>
                        <div class="alert-content">
                            <strong>هشدار موجودی:</strong> برخی از محصولات موجودی کمی دارند.
                        </div>
                    </div>

                    <div v-if="hasDiscounts" class="alert success">
                        <span class="alert-icon"><IconTicket class="emo-icon" aria-hidden="true" /></span>
                        <div class="alert-content">
                            <strong>تخفیف اعمال شده:</strong> مشتری از تخفیف‌های ویژه بهره‌مند شده است.
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <div class="footer-actions">
                    <button 
                        @click="$emit('cancel')"
                        class="btn-cancel"
                    >
                        <IconX class="emo-icon" aria-hidden="true" /> انصراف
                    </button>
                    <button 
                        @click="$emit('confirm')"
                        class="btn-confirm"
                    >
                        <IconCircleCheck class="emo-icon" aria-hidden="true" /> تأیید و ثبت سفارش
                    </button>
                </div>
                
                <div class="footer-note">
                    <small>با کلیک بر روی تأیید، سفارش به صورت قطعی ثبت خواهد شد.</small>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { CircleCheck as IconCircleCheck, Coins as IconCoins, FileText as IconFileText, Gift as IconGift, ShoppingCart as IconShoppingCart, Ticket as IconTicket, TriangleAlert as IconTriangleAlert, User as IconUser, WifiOff as IconWifiOff, X as IconX } from 'lucide-vue-next';
import { computed } from 'vue';

import { formatPrice } from '../../utils/format';
export default {
    components: { IconCircleCheck, IconCoins, IconFileText, IconGift, IconShoppingCart, IconTicket, IconTriangleAlert, IconUser, IconWifiOff, IconX },
    name: 'OrderConfirmationModal',
    props: {
        orderData: {
            type: Object,
            required: true
        }
    },
    emits: ['confirm', 'cancel'],
    setup(props) {
        const totalQuantity = computed(() => {
            return props.orderData.items.reduce((sum, item) => sum + item.quantity, 0);
        });

        const hasLowStockItems = computed(() => {
            return props.orderData.items.some(item => 
                item.product.stock_quantity <= item.product.min_stock
            );
        });

        const hasDiscounts = computed(() => {
            return props.orderData.summary.discount > 0;
        });

        return {
            totalQuantity,
            hasLowStockItems,
            hasDiscounts,
            formatPrice
        };
    }
}
</script>

<style scoped>

.customer-section,
.order-details-section,
.summary-section,
.notes-section,
.alerts-section {
    margin-bottom: 24px;
}

.customer-section h4,
.order-details-section h4,
.summary-section h4,
.notes-section h4 {
    color: #2d3748;
    margin-bottom: 12px;
    font-size: 1rem;
    border-bottom: 2px solid #f7fafc;
    padding-bottom: 8px;
}

.customer-info {
    background: #f7fafc;
    border-radius: 8px;
    padding: 16px;
}

.info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #e2e8f0;
}

.info-row:last-child {
    border-bottom: none;
}

.info-row .label {
    color: #4a5568;
    font-weight: 500;
}

.info-row .value {
    color: #2d3748;
    font-weight: 600;
}

.order-items {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.order-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    background: #f7fafc;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.item-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1;
}

.item-name {
    font-weight: 600;
    color: #2d3748;
}

.item-type {
    color: #718096;
    font-size: 0.875rem;
    background: #ebf8ff;
    padding: 2px 8px;
    border-radius: 4px;
    align-self: flex-start;
}

.item-details {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 4px;
}

.item-quantity {
    color: #718096;
    font-size: 0.875rem;
}

.item-price {
    color: #38a169;
    font-weight: 600;
}

.summary-grid {
    display: flex;
    flex-direction: column;
    gap: 8px;
    background: #f7fafc;
    border-radius: 8px;
    padding: 16px;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #e2e8f0;
}

.summary-item:last-child {
    border-bottom: none;
}

.summary-item.vat {
    font-size: 0.85rem;
    color: #64748b;
}

.summary-item.total {
    border-top: 2px solid #e2e8f0;
    padding-top: 12px;
    margin-top: 4px;
    font-weight: 700;
    font-size: 1.1rem;
}

.total-amount {
    color: #3182ce;
    font-size: 1.2rem;
}

.discount-amount {
    color: #e53e3e;
    font-weight: 600;
}

.price {
    color: #38a169;
    font-weight: 600;
}

.notes-content {
    background: #f7fafc;
    border-radius: 8px;
    padding: 16px;
    border-right: 4px solid #3182ce;
    font-style: italic;
    color: #4a5568;
}

.alerts-section {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.alert {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 16px;
    border-radius: 8px;
    border-left: 4px solid;
}

.alert.info {
    background: #ebf8ff;
    border-left-color: #3182ce;
    color: #2c5282;
}

.alert.warning {
    background: #fef5e7;
    border-left-color: #dd6b20;
    color: #744210;
}

.alert.success {
    background: #f0fff4;
    border-left-color: #38a169;
    color: #22543d;
}

.alert-icon {
    font-size: 1.2rem;
    flex-shrink: 0;
}

.alert-content {
    flex: 1;
    font-size: 0.875rem;
}

.alert-content strong {
    display: block;
    margin-bottom: 2px;
}

.footer-actions {
    display: flex;
    gap: 12px;
}

.btn-confirm {
    background: #38a169;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    flex: 1;
    transition: background 0.3s ease;
}

.btn-confirm:hover {
    background: #2f855a;
}

.footer-note {
    text-align: center;
}

.footer-note small {
    color: #718096;
    font-size: 0.75rem;
}

@media (max-width: 768px) {
    
    .footer-actions {
        flex-direction: column;
    }
    
    .order-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    
    .item-details {
        align-items: flex-start;
        flex-direction: row;
        gap: 12px;
    }
}

@media (max-width: 480px) {
    
    .customer-info,
    .summary-grid,
    .notes-content {
        padding: 12px;
    }
    
    .alert {
        padding: 10px 12px;
    }
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>