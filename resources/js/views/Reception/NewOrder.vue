<template>
    <div class="new-order-page">
        <!-- هدر صفحه -->
        <div class="page-header">
            <div class="header-content">
                <h1><IconShoppingCart class="emo-icon" aria-hidden="true" /> ثبت سفارش جدید</h1>
                <p>سیستم ثبت خرید برای مشتریان</p>
            </div>
            <div class="header-actions">
                <label class="auto-print-toggle" title="بعد از ثبت موفق سفارش، رسید به‌طور خودکار چاپ شود">
                    <input type="checkbox" v-model="autoPrintReceipt" />
                    <IconPrinter class="emo-icon" aria-hidden="true" /> چاپ خودکار رسید
                </label>
                <button
                    type="button"
                    @click="holdCurrentCart"
                    class="btn-outline"
                    :disabled="orderLoading || holding || cartItems.length === 0 || !isOnline"
                    title="سبد فعلی را برای بعد نگه می‌دارد"
                >
                    <IconPause class="emo-icon" aria-hidden="true" /> معلق کردن سبد
                </button>
                <button
                    type="button"
                    @click="openHeldCarts"
                    class="btn-outline"
                    :disabled="orderLoading || !isOnline"
                >
                    <IconClipboardList class="emo-icon" aria-hidden="true" /> سبدهای معلق<span v-if="heldCount"> ({{ heldCount }})</span>
                </button>
                <button 
                    @click="resetOrder"
                    class="btn-secondary"
                    :disabled="orderLoading"
                >
                    <IconRefreshCw class="emo-icon" aria-hidden="true" /> شروع جدید
                </button>
            </div>
        </div>

        <!-- وضعیت آفلاین و صف سفارش‌های ذخیره‌شده -->
        <div v-if="!isOnline || offlineQueue.length" class="offline-banner" :class="{ 'offline-banner-off': !isOnline }">
            <div class="offline-line">
                <span v-if="!isOnline"><IconWifiOff class="emo-icon" aria-hidden="true" /> اینترنت قطع است. سفارش‌ها روی این دستگاه ذخیره و هنگام اتصال ارسال می‌شوند.</span>
                <span v-else><IconClock class="emo-icon" aria-hidden="true" /> {{ offlineQueue.length }} سفارش در صف ارسال است.</span>
                <span v-if="usingCachedData" class="offline-note">(منو و موجودی از آخرین بارگذاری)</span>
                <button
                    v-if="isOnline && offlineQueue.length && !flushing"
                    type="button"
                    class="btn-outline"
                    @click="flushQueue"
                >
                    ارسال صف
                </button>
                <span v-if="flushing" class="offline-note">در حال ارسال...</span>
            </div>
            <div v-if="offlineQueue.length" class="queue-list">
                <div v-for="entry in offlineQueue" :key="entry.client_uuid" class="queue-item" :class="{ 'queue-failed': entry.failed }">
                    <span>
                        مشتری {{ entry.payload.customer_membership_number }} —
                        {{ entry.payload.items.length }} قلم —
                        ذخیره: {{ new Date(entry.queued_at).toLocaleString('fa-IR') }}
                    </span>
                    <span v-if="!entry.failed && entry.stock_warnings && entry.stock_warnings.length" class="queue-error" style="color:#b45309">
                        <IconTriangleAlert class="emo-icon" aria-hidden="true" /> احتمال کمبود موجودی: {{ entry.stock_warnings.join('، ') }}
                    </span>
                    <span v-if="entry.failed" class="queue-error">رد شد: {{ entry.last_error }}</span>
                    <span v-if="entry.failed" class="queue-actions">
                        <button type="button" class="btn-outline" @click="retryFailedOrder(entry.client_uuid)">تلاش دوباره</button>
                        <button type="button" class="btn-remove-queue" @click="discardQueuedOrder(entry.client_uuid)">حذف</button>
                    </span>
                </div>
            </div>
        </div>

        <!-- پیام‌های سیستمی -->
        <div v-if="systemMessages.length" class="messages-section">
            <div 
                v-for="message in systemMessages" 
                :key="message.id"
                :class="['message', `message-${message.type}`]"
            >
                <span class="message-icon"><component :is="emojiIcon(message.icon)" v-if="emojiIcon(message.icon)" class="emo-icon" /><template v-else>{{ message.icon }}</template></span>
                <div class="message-content">
                    <div class="message-title">{{ message.title }}</div>
                    <div class="message-text">{{ message.text }}</div>
                </div>
                <button @click="removeMessage(message.id)" class="message-close">×</button>
            </div>
        </div>

        <div class="order-layout">
            <!-- سایدبار سمت راست - اطلاعات مشتری -->
            <div class="sidebar">
                <!-- بخش جستجوی مشتری -->
                <div class="customer-section">
                    <h3><IconUser class="emo-icon" aria-hidden="true" /> اطلاعات مشتری</h3>
                    
                    <div class="customer-search">
                        <div class="search-input-group">
                            <input
                                type="text"
                                v-model="membershipSearch"
                                placeholder="شماره اشتراک مشتری"
                                @keyup.enter="searchCustomer"
                                :disabled="orderLoading"
                                class="search-input"
                            >
                            <button 
                                @click="searchCustomer" 
                                class="btn-search"
                                :disabled="orderLoading || !membershipSearch.trim()"
                            >
                                <IconSearch class="emo-icon" aria-hidden="true" /> جستجو
                            </button>
                        </div>
                        
                        <div class="customer-actions">
                            <button 
                                @click="showAddCustomerModal = true"
                                class="btn-outline"
                                :disabled="orderLoading"
                            >
                                <IconPlus class="emo-icon" aria-hidden="true" /> مشتری جدید
                            </button>
                            <button 
                                v-if="customer"
                                @click="viewCustomerHistory"
                                class="btn-outline"
                            >
                                <IconChartColumn class="emo-icon" aria-hidden="true" /> تاریخچه
                            </button>
                        </div>
                    </div>

                    <!-- نمایش اطلاعات مشتری -->
                    <div v-if="customer" class="customer-info-card">
                        <div class="customer-header">
                            <div class="customer-avatar">
                                {{ getCustomerInitials(customer) }}
                            </div>
                            <div class="customer-main-info">
                                <h4>{{ customer.full_name }}</h4>
                                <p>شماره: {{ customer.membership_number }}</p>
                            </div>
                            <button @click="clearCustomer" class="btn-remove" title="حذف مشتری">
                                <IconX class="emo-icon" aria-hidden="true" />
                            </button>
                        </div>

                        <div class="customer-details">
                            <div class="detail-item">
                                <span class="label"><IconPhone class="emo-icon" aria-hidden="true" /> تلفن:</span>
                                <span class="value">{{ customer.phone }}</span>
                            </div>
                            <div v-if="customer.date_of_birth" class="detail-item">
                                <span class="label"><IconCake class="emo-icon" aria-hidden="true" /> تولد:</span>
                                <span class="value">{{ formatDate(customer.date_of_birth) }}</span>
                            </div>
                            <div class="detail-item">
                                <span class="label"><IconCoins class="emo-icon" aria-hidden="true" /> مجموع خرید:</span>
                                <span class="value price">{{ formatPrice(customer.total_purchases) }}</span>
                            </div>
                            <div class="detail-item">
                                <span class="label"><IconTrendingUp class="emo-icon" aria-hidden="true" /> خرید این ماه:</span>
                                <span class="value price">{{ formatPrice(customer.monthly_purchases || 0) }}</span>
                            </div>
                        </div>

                        <!-- تخفیف‌های فعال -->
                        <div v-if="customer.loyalty" class="loyalty-box">
                            <div class="loyalty-line">
                                <IconStar class="emo-icon" aria-hidden="true" /> امتیاز: <strong>{{ customer.loyalty.points_balance }}</strong>
                                <span class="loyalty-tier">سطح {{ customer.loyalty.tier }}</span>
                            </div>
                            <div v-if="customer.loyalty.points_balance >= customer.loyalty.min_redeem_points" class="loyalty-redeem">
                                <label for="redeem-points">استفاده از امتیاز (هر امتیاز {{ formatPrice(customer.loyalty.point_value) }} تومان):</label>
                                <input
                                    id="redeem-points"
                                    type="number"
                                    min="0"
                                    step="1"
                                    :max="customer.loyalty.points_balance"
                                    v-model.number="redeemPoints"
                                    :disabled="orderLoading"
                                >
                                <small>حداقل {{ customer.loyalty.min_redeem_points }} امتیاز</small>
                            </div>
                            <div v-if="redeemError" class="loyalty-error">{{ redeemError }}</div>
                        </div>

                        <div v-if="customer.active_discounts && customer.active_discounts.length > 0" 
                             class="discounts-section">
                            <h5><IconTicket class="emo-icon" aria-hidden="true" /> تخفیف‌های فعال</h5>
                            <div class="discounts-list">
                                <div 
                                    v-for="discount in customer.active_discounts" 
                                    :key="discount.id"
                                    class="discount-item"
                                    :class="`discount-${discount.type}`"
                                >
                                    <div class="discount-badge">
                                        {{ getDiscountBadgeText(discount) }}
                                    </div>
                                    <div class="discount-info">
                                        <div class="discount-name">{{ discount.name }}</div>
                                        <div class="discount-message">{{ discount.message }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- وضعیت جستجو -->
                    <div v-if="searchLoading" class="search-status">
                        <div class="loading-spinner"></div>
                        <span>در حال جستجوی مشتری...</span>
                    </div>
                </div>

                <!-- خلاصه سفارش -->
                <div v-if="customer && cartItems.length > 0" class="order-summary-card">
                    <h3><IconReceipt class="emo-icon" aria-hidden="true" /> خلاصه سفارش</h3>
                    
                    <div class="summary-items">
                        <div class="summary-item">
                            <span>تعداد آیتم‌ها:</span>
                            <span>{{ cartItems.length }}</span>
                        </div>
                        <div class="summary-item">
                            <span>تعداد کل:</span>
                            <span>{{ totalQuantity }}</span>
                        </div>
                        <div class="summary-item">
                            <span>جمع کل:</span>
                <span class="price">{{ formatPrice(realTimeSummary.subtotal) }}</span>
                        </div>
                        
                                    <!-- نمایش تخفیف‌های اعمال شده -->
            <div v-if="realTimeSummary.applied_discounts.length > 0" class="discounts-breakdown">
                <div class="summary-item discount-header">
                    <span>تخفیف‌ها:</span>
                </div>
                <div 
                    v-for="disc in realTimeSummary.applied_discounts" 
                    :key="disc.id"
                    class="summary-item discount-item"
                >
                    <span class="discount-name">{{ disc.name }}</span>
                    <span class="discount-amount">-{{ formatPrice(disc.amount) }}</span>
                </div>
            </div>
            
            <div v-else-if="realTimeSummary.discount > 0" class="summary-item discount">
                <span>تخفیف:</span>
                <span class="discount-amount">-{{ formatPrice(realTimeSummary.discount) }}</span>
            </div>
                        
                        <div v-if="realTimeSummary.gift_amount > 0" class="summary-item discount">
                            <span class="discount-name"><IconGift class="emo-icon" aria-hidden="true" /> کارت هدیه</span>
                            <span class="discount-amount">-{{ formatPrice(realTimeSummary.gift_amount) }}</span>
                        </div>
                        <div class="summary-item total">
                            <span>مبلغ نهایی:</span>
                <span class="total-amount">{{ formatPrice(realTimeSummary.total) }}</span>
                        </div>
                        <div v-if="vatAmount > 0" class="summary-item vat">
                            <span>شامل مالیات ارزش افزوده ({{ formatPrice(vatRate) }}٪):</span>
                            <span>{{ formatPrice(vatAmount) }}</span>
                        </div>
                    </div>

                    <!-- کارت هدیه -->
                    <div v-if="customer" class="gift-card-box">
                        <label><IconGift class="emo-icon" aria-hidden="true" /> کارت هدیه:</label>
                        <div v-if="appliedGiftCode" class="gift-applied">
                            <span>
                                کد <strong>{{ appliedGiftCode }}</strong>
                                <template v-if="giftCardInfo?.applied"> — {{ formatPrice(giftCardInfo.applied) }} اعمال شد</template>
                            </span>
                            <button type="button" class="btn-remove" title="حذف کارت هدیه" @click="removeGiftCard"><IconX class="emo-icon" aria-hidden="true" /></button>
                        </div>
                        <div v-else class="gift-input-row">
                            <input
                                v-model="giftCardInput"
                                type="text"
                                placeholder="کد کارت هدیه"
                                class="search-input"
                                :disabled="orderLoading"
                                @keyup.enter="applyGiftCard"
                            >
                            <button
                                type="button"
                                class="btn-outline"
                                :disabled="orderLoading || !giftCardInput.trim()"
                                @click="applyGiftCard"
                            >
                                اعمال
                            </button>
                        </div>
                        <div v-if="giftCardInfo?.error" class="loyalty-error">{{ giftCardInfo.error }}</div>
                    </div>

                    <!-- تنظیمات سفارش -->
                    <div class="order-settings">
                        <label class="checkbox-setting">
                            <input 
                                type="checkbox" 
                                v-model="applyDiscounts"
                                :disabled="!hasActiveDiscounts"
                            >
                            <span class="checkmark"></span>
                            اعمال تخفیف‌های خودکار
                        </label>

                        <div class="payment-section">
                            <label>روش پرداخت:</label>
                            <div class="payment-options">
                                <label><input type="radio" value="cash" v-model="paymentMethod" :disabled="orderLoading"> نقد</label>
                                <label><input type="radio" value="card" v-model="paymentMethod" :disabled="orderLoading"> کارت</label>
                            </div>
                        </div>
                        
                        <div class="notes-section">
                            <label>یادداشت سفارش (اختیاری):</label>
                            <textarea 
                                v-model="orderNotes"
                                placeholder="یادداشت یا توضیحات خاص برای این سفارش..."
                                rows="2"
                                maxlength="500"
                            ></textarea>
                            <div class="char-counter">{{ orderNotes.length }}/500</div>
                        </div>
                                <!-- نمایش پیام‌های تخفیف -->
        <div v-if="realTimeSummary.applied_discounts.length > 0" class="discount-messages">
            <div 
                v-for="disc in realTimeSummary.applied_discounts" 
                :key="disc.id"
                class="discount-message"
            >
                <IconTicket class="emo-icon" aria-hidden="true" /> {{ disc.message }}
            </div>
        </div>
                    </div>

                    <!-- اقدامات سفارش -->
                    <div class="order-actions">
                        <button 
                            @click="clearCart"
                            class="btn-clear"
                            :disabled="orderLoading"
                        >
                            <IconTrash2 class="emo-icon" aria-hidden="true" /> پاک کردن سبد
                        </button>
                        <button 
                            @click="submitOrder"
                            :disabled="orderLoading || cartItems.length === 0"
                            class="btn-submit"
                            :class="{ loading: orderLoading }"
                        >
                            <span v-if="orderLoading" class="loading-text">
                                <div class="spinner"></div>
                                در حال ثبت...
                            </span>
                            <span v-else>
                                <component :is="emojiIcon(isOnline ? '✅' : '📴')" class="emo-icon" /> {{ isOnline ? 'ثبت نهایی سفارش' : 'ذخیره‌ی آفلاین سفارش' }}
                            </span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- محتوای اصلی - محصولات -->
            <div class="main-content">
                <!-- دسته‌بندی محصولات -->
                <div class="categories-section">
                    <div class="categories-header">
                        <h3><IconShoppingBag class="emo-icon" aria-hidden="true" /> انتخاب محصولات</h3>
                        <div class="category-filters">
                            <button 
                                v-for="category in categories"
                                :key="category.value"
                                @click="setActiveCategory(category.value)"
                                :class="['category-btn', { active: activeCategory === category.value }]"
                            >
                                <component :is="emojiIcon(category.icon)" v-if="emojiIcon(category.icon)" class="emo-icon" /><template v-else>{{ category.icon }}</template> {{ category.label }}
                            </button>
                        </div>
                    </div>

                    <!-- جستجوی محصولات -->
                    <div class="products-search">
                        <div class="search-box">
                            <input
                                type="text"
                                v-model="productSearch"
                                placeholder="جستجوی محصولات..."
                                class="search-input"
                            >
                            <span class="search-icon"><IconSearch class="emo-icon" aria-hidden="true" /></span>
                        </div>
                        <div class="barcode-box">
                            <input
                                v-model="barcodeInput"
                                type="text"
                                inputmode="numeric"
                                placeholder="اسکن یا وارد کردن بارکد"
                                class="search-input barcode-input"
                                :disabled="orderLoading"
                                @keyup.enter="scanBarcode"
                            >
                            <button type="button" class="btn-outline" :disabled="!barcodeInput.trim() || orderLoading" @click="scanBarcode">
                                <IconPlus class="emo-icon" aria-hidden="true" /> افزودن
                            </button>
                        </div>
                        <div class="sort-options">
                            <select v-model="sortBy" class="sort-select">
                                <option value="name">مرتب سازی بر اساس نام</option>
                                <option value="price">مرتب سازی بر اساس قیمت</option>
                                <option value="popular">پرفروش‌ترین</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- لیست محصولات -->
                <div class="products-grid">
                    <div 
                        v-for="product in filteredProducts"
                        :key="product.id"
                        class="product-card"
                        :class="{
                            'out-of-stock': product.stock_quantity === 0,
                            'in-cart': getProductQuantity(product.id) > 0
                        }"
                    >
                        <div class="product-image">
                            <img
                                v-if="product.image_url && !failedImages[product.id]"
                                :src="product.image_url"
                                :alt="product.name"
                                class="product-photo"
                                loading="lazy"
                                @error="failedImages[product.id] = true"
                            >
                            <div v-else class="image-placeholder">
                                <component :is="emojiIcon('📦')" v-if="!product.category_icon" class="emo-icon" /><template v-else>{{ product.category_icon }}</template>
                            </div>
                            <div v-if="product.stock_quantity === 0" class="out-of-stock-overlay">
                                <IconX class="emo-icon" aria-hidden="true" /> ناموجود
                            </div>
                        </div>

                        <div class="product-info">
                            <h4 class="product-name">{{ product.name }}</h4>
                            <p class="product-description" v-if="product.description">
                                {{ product.description }}
                            </p>
                            <div class="product-meta">
                                <span class="product-type">{{ product.category_name }}</span>
                                <span v-if="product.variant_label" class="variant-badge"><IconPuzzle class="emo-icon" aria-hidden="true" /> {{ product.variant_label }}</span>
                                <span class="product-stock" v-if="product.stock_quantity > 0">
                                    موجودی: {{ product.stock_quantity }}
                                </span>
                            </div>
                            <div class="product-price">
                                {{ formatPrice(product.price) }}
                            </div>
                        </div>

                        <div class="product-actions">
                            <div class="quantity-controls">
                                <button 
                                    @click="decreaseQuantity(product.id)"
                                    :disabled="getProductQuantity(product.id) === 0"
                                    class="quantity-btn minus"
                                >
                                    −
                                </button>
                                <span class="quantity-display">
                                    {{ getProductQuantity(product.id) }}
                                </span>
                                <button 
                                    @click="increaseQuantity(product.id)"
                                    :disabled="product.stock_quantity === 0 || 
                                              getProductQuantity(product.id) >= product.stock_quantity"
                                    class="quantity-btn plus"
                                >
                                    +
                                </button>
                            </div>
                            
                            <button 
                                v-if="getProductQuantity(product.id) === 0"
                                @click="addToCart(product)"
                                class="btn-add-to-cart"
                                :disabled="product.stock_quantity === 0"
                            >
                                <IconPlus class="emo-icon" aria-hidden="true" /> افزودن
                            </button>
                        </div>
                    </div>
                </div>

                <!-- وضعیت محصولات -->
                <div v-if="productsLoading" class="products-loading">
                    <div class="loading-spinner large"></div>
                    <p>در حال بارگذاری محصولات...</p>
                </div>

                <div v-if="!productsLoading && filteredProducts.length === 0" class="no-products">
                    <div class="no-products-icon"><IconLeaf class="emo-icon" aria-hidden="true" /></div>
                    <h4>محصولی یافت نشد</h4>
                    <p>هیچ محصولی با فیلترهای انتخابی شما مطابقت ندارد.</p>
                </div>
            </div>
        </div>

        <!-- Modal ثبت مشتری جدید -->
        <CustomerModal 
            :show="showAddCustomerModal"
            @close="showAddCustomerModal = false"
            @saved="handleCustomerSaved"
        />

        <!-- Modal تاریخچه مشتری -->
        <CustomerHistoryModal 
            v-if="showCustomerHistoryModal && customer"
            :customer="customer"
            @close="showCustomerHistoryModal = false"
        />

        <!-- Modal تأیید نهایی سفارش -->
        <OrderConfirmationModal 
            v-if="showConfirmationModal"
            :order-data="finalOrderData"
            @confirm="confirmOrder"
            @cancel="showConfirmationModal = false"
        />

        <!-- Modal سبدهای معلق -->
        <HeldCartsModal
            v-if="showHeldCarts"
            :carts="heldCarts"
            :loading="heldLoading"
            :busy="heldBusy"
            @close="showHeldCarts = false"
            @resume="resumeHeldCart"
            @discard="discardHeldCart"
        />
    </div>
</template>


<script>
import { emojiIcon } from '../../utils/emojiIcons';
import { Cake as IconCake, ChartColumn as IconChartColumn, ClipboardList as IconClipboardList, Clock as IconClock, Coins as IconCoins, Gift as IconGift, Leaf as IconLeaf, Pause as IconPause, Phone as IconPhone, Plus as IconPlus, Printer as IconPrinter, Puzzle as IconPuzzle, Receipt as IconReceipt, RefreshCw as IconRefreshCw, Search as IconSearch, ShoppingBag as IconShoppingBag, ShoppingCart as IconShoppingCart, Star as IconStar, Ticket as IconTicket, Trash2 as IconTrash2, TrendingUp as IconTrendingUp, TriangleAlert as IconTriangleAlert, User as IconUser, WifiOff as IconWifiOff, X as IconX } from 'lucide-vue-next';
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { useNotificationStore } from '../../stores/notifications';
import CustomerModal from './CustomerModal.vue';
import CustomerHistoryModal from './CustomerHistoryModal.vue';
import OrderConfirmationModal from './OrderConfirmationModal.vue';
import HeldCartsModal from './HeldCartsModal.vue';
import { printReceipt } from '../../utils/receipt';

import { formatPrice, formatDate, vatIncluded } from '../../utils/format';
import {
    newClientUuid,
    enqueueOrder,
    getQueue,
    removeQueueEntry,
    updateQueueEntry,
    cacheProducts,
    readCachedProducts,
    cacheCustomer,
    readCachedCustomer,
    isNetworkError
} from '../../utils/offline';
// تعداد تلاش برای خطای موقت سرور در همگام‌سازی صف آفلاین
const MAX_TRANSIENT_ATTEMPTS = 5;

export default {
    methods: { emojiIcon },
    name: 'NewOrder',
    components: { IconCake, IconChartColumn, IconClipboardList, IconClock, IconCoins, IconGift, IconLeaf, IconPause, IconPhone, IconPlus, IconPrinter, IconPuzzle, IconReceipt, IconRefreshCw, IconSearch, IconShoppingBag, IconShoppingCart, IconStar, IconTicket, IconTrash2, IconTrendingUp, IconTriangleAlert, IconUser, IconWifiOff, IconX, 
        CustomerModal,
        CustomerHistoryModal,
        OrderConfirmationModal,
        HeldCartsModal
    },
    setup() {
        const router = useRouter();
        const notificationStore = useNotificationStore();

        // داده‌های اصلی
        const membershipSearch = ref('');
        const customer = ref(null);
        const availableProducts = ref([]);
        const cart = ref({});
        const orderNotes = ref('');
        
        // حالت‌های UI
        const orderLoading = ref(false);
        const searchLoading = ref(false);
        const productsLoading = ref(false);
        const applyDiscounts = ref(true);
        
        // Modal‌ها
        const showAddCustomerModal = ref(false);
        const showCustomerHistoryModal = ref(false);
        const showConfirmationModal = ref(false);
        
        // فیلترها و جستجو
        const productSearch = ref('');
        const activeCategory = ref('all');
        const sortBy = ref('name');
        
        // پیام‌های سیستمی
        const systemMessages = ref([]);

        // امتیاز وفاداری و روش پرداخت
        const redeemPoints = ref(0);
        const redeemError = ref('');
        const paymentMethod = ref('cash');

        // کارت هدیه، بارکد و حالت آفلاین
        const giftCardInput = ref('');
        const appliedGiftCode = ref('');
        const giftCardInfo = ref(null);
        const barcodeInput = ref('');
        const isOnline = ref(navigator.onLine);
        const offlineQueue = ref(getQueue());
        const flushing = ref(false);
        const pendingClientUuid = ref(null);
        const usingCachedData = ref(false);

        // نرخ ارزش افزوده (از سرور؛ در حالت آفلاین از کش منو)
        const vatRate = ref(0);

        // داده‌های محاسبه تخفیف
        const realTimeSummary = ref({
            subtotal: 0,
            discount: 0,
            gift_amount: 0,
            total: 0,
            applied_discounts: []
        });

        // دسته‌بندی محصولات (از سرور و از تعریف مدیر دریافت می‌شود)
        const categoryOptions = ref([]);
        const categories = computed(() => [
            { value: 'all', label: 'همه محصولات', icon: '📦' },
            ...categoryOptions.value.map(category => ({
                value: category.id,
                label: category.name,
                icon: category.icon || '🏷️'
            }))
        ]);

        // computed properties
        const cartItems = computed(() => {
            return Object.entries(cart.value)
                .filter(([_, quantity]) => quantity > 0)
                .map(([productId, quantity]) => {
                    const product = availableProducts.value.find(p => p.id === parseInt(productId));
                    return product ? { product, quantity } : null;
                })
                .filter(Boolean);
        });

        const totalQuantity = computed(() => {
            return cartItems.value.reduce((sum, item) => sum + item.quantity, 0);
        });

        const subtotal = computed(() => {
            return cartItems.value.reduce((sum, item) => {
                return sum + (item.product.price * item.quantity);
            }, 0);
        });

        // مالیات داخل مبلغ نهایی است و مبلغ پرداختی را تغییر نمی‌دهد
        const vatAmount = computed(() => vatIncluded(realTimeSummary.value.total, vatRate.value));

        const orderSummary = computed(() => {
            return {
                subtotal: realTimeSummary.value.subtotal,
                discount: realTimeSummary.value.discount,
                gift_amount: realTimeSummary.value.gift_amount,
                total: realTimeSummary.value.total,
                vat_rate: vatRate.value,
                vat_amount: vatAmount.value,
                applied_discounts: realTimeSummary.value.applied_discounts
            };
        });

        const hasActiveDiscounts = computed(() => {
            return customer.value?.active_discounts?.length > 0;
        });

        const filteredProducts = computed(() => {
            let products = [...availableProducts.value];

            // فیلتر بر اساس دسته‌بندی
            if (activeCategory.value !== 'all') {
                products = products.filter(p => p.category_id === activeCategory.value);
            }

            // فیلتر بر اساس جستجو
            if (productSearch.value.trim()) {
                const searchTerm = productSearch.value.toLowerCase();
                products = products.filter(p => 
                    p.name.toLowerCase().includes(searchTerm) ||
                    (p.description && p.description.toLowerCase().includes(searchTerm))
                );
            }

            // مرتب‌سازی
            switch (sortBy.value) {
                case 'price':
                    products.sort((a, b) => a.price - b.price);
                    break;
                case 'popular':
                    products.sort((a, b) => b.stock_quantity - a.stock_quantity);
                    break;
                default:
                    products.sort((a, b) => a.name.localeCompare(b.name));
            }

            return products;
        });

        const finalOrderData = computed(() => ({
            customer: customer.value,
            items: cartItems.value,
            summary: orderSummary.value,
            notes: orderNotes.value,
            offline: !isOnline.value
        }));

        // متدهای محاسبه تخفیف
        const calculateRealDiscounts = async () => {
            if (!customer.value || cartItems.value.length === 0) {
                return {
                    total_discount: 0,
                    applied_discounts: [],
                    final_amount: subtotal.value
                };
            }

            try {
                const items = cartItems.value.map(item => ({
                    product_id: item.product.id,
                    quantity: item.quantity
                }));

                const response = await axios.post('/api/reception/calculate-discount', {
                    customer_id: customer.value.id,
                    items: items,
                    apply_discounts: applyDiscounts.value,
                    redeem_points: Number(redeemPoints.value) || 0,
                    gift_card_code: appliedGiftCode.value || undefined
                });

                redeemError.value = '';
                return response.data;
            } catch (error) {
                console.error('Error calculating discounts:', error);
                redeemError.value = error.response?.status === 422
                    ? (error.response.data?.errors?.redeem_points?.[0] || error.response.data?.message || '')
                    : '';
                // در صورت خطا، تخفیف صفر برگردان
                return {
                    total_discount: 0,
                    applied_discounts: [],
                    final_amount: subtotal.value,
                    payable_amount: subtotal.value,
                    gift_card: null
                };
            }
        };

        const updateRealTimeSummary = async () => {
            const currentSubtotal = subtotal.value;

            // سرور خودش تخفیف‌های خودکار (اگر روشن باشند) و امتیاز را حساب می‌کند
            if (customer.value && cartItems.value.length > 0) {
                const discountResult = await calculateRealDiscounts();
                giftCardInfo.value = discountResult.gift_card ?? null;
                realTimeSummary.value = {
                    subtotal: currentSubtotal,
                    discount: discountResult.total_discount,
                    gift_amount: Number(discountResult.gift_card?.applied ?? 0),
                    total: Number(discountResult.payable_amount ?? discountResult.final_amount),
                    applied_discounts: discountResult.applied_discounts
                };
            } else {
                giftCardInfo.value = null;
                realTimeSummary.value = {
                    subtotal: currentSubtotal,
                    discount: 0,
                    gift_amount: 0,
                    total: currentSubtotal,
                    applied_discounts: []
                };
            }
        };

        // watch برای آپدیت real-time
        watch([cartItems, applyDiscounts, customer, redeemPoints, appliedGiftCode], async () => {
            await updateRealTimeSummary();
        }, { immediate: true, deep: true });

        // متدهای اصلی
        const searchCustomer = async () => {
            if (!membershipSearch.value.trim()) {
                addSystemMessage('error', 'خطا', 'لطفا شماره اشتراک مشتری را وارد کنید', '❌');
                return;
            }

            searchLoading.value = true;
            try {
                const response = await axios.get(`/api/reception/customers/lookup/${membershipSearch.value.trim()}`);
                customer.value = response.data.customer;
                cacheCustomer(customer.value);
                
                addSystemMessage('success', 'موفقیت', 
                    `مشتری ${customer.value.full_name} با موفقیت پیدا شد`, '✅');
                
                await loadProducts();
                
            } catch (error) {
                console.error('Error searching customer:', error);
                const offlineCustomer = isNetworkError(error) ? readCachedCustomer(membershipSearch.value.trim()) : null;
                if (offlineCustomer) {
                    customer.value = offlineCustomer;
                    addSystemMessage('info', 'آفلاین', 'مشتری از اطلاعات ذخیره‌شده‌ی این دستگاه انتخاب شد', '📴');
                    return;
                }
                const message = error.response?.status === 404 
                    ? 'مشتری با این شماره اشتراک یافت نشد'
                    : 'خطا در جستجوی مشتری';
                
                addSystemMessage('error', 'خطا', message, '❌');
                customer.value = null;
            } finally {
                searchLoading.value = false;
            }
        };

        const loadProducts = async () => {
            productsLoading.value = true;
            try {
                const response = await axios.get('/api/reception/products');
                availableProducts.value = response.data.products;
                categoryOptions.value = response.data.categories || [];
                vatRate.value = Number(response.data.vat_rate) || 0;
                cacheProducts(availableProducts.value, categoryOptions.value, vatRate.value);
                usingCachedData.value = false;
            } catch (error) {
                const cached = readCachedProducts();
                if (cached) {
                    availableProducts.value = cached.products || [];
                    categoryOptions.value = cached.categories || [];
                    vatRate.value = Number(cached.vat_rate) || 0;
                    usingCachedData.value = true;
                    addSystemMessage('info', 'آفلاین', `منو و موجودی از آخرین بارگذاری (${new Date(cached.cached_at).toLocaleString('fa-IR')}) نمایش داده می‌شود`, '📴');
                } else {
                    console.error('Error loading products:', error);
                    addSystemMessage('error', 'خطا', 'خطا در بارگذاری محصولات', '❌');
                }
            } finally {
                productsLoading.value = false;
            }
        };

        const addToCart = (product) => {
            if (!cart.value[product.id]) {
                cart.value[product.id] = 0;
            }
            cart.value[product.id]++;
            
            addSystemMessage('info', 'افزوده شد', 
                `${product.name} به سبد خرید افزوده شد`, '🛒');
        };

        const increaseQuantity = (productId) => {
            if (!cart.value[productId]) {
                cart.value[productId] = 0;
            }
            cart.value[productId]++;
        };

        const decreaseQuantity = (productId) => {
            if (cart.value[productId] && cart.value[productId] > 0) {
                cart.value[productId]--;
                
                if (cart.value[productId] === 0) {
                    delete cart.value[productId];
                }
            }
        };

        const getProductQuantity = (productId) => {
            return cart.value[productId] || 0;
        };

        const clearCart = () => {
            if (cartItems.value.length === 0) return;
            
            cart.value = {};
            addSystemMessage('info', 'سبد خرید', 'سبد خرید پاک شد', '🗑️');
        };

        const clearCustomer = () => {
            customer.value = null;
            redeemPoints.value = 0;
            redeemError.value = '';
            membershipSearch.value = '';
            appliedGiftCode.value = '';
            giftCardInput.value = '';
            giftCardInfo.value = null;
            clearCart();
            addSystemMessage('info', 'مشتری', 'مشتری فعلی حذف شد', '👤');
        };

        const setActiveCategory = (category) => {
            activeCategory.value = category;
        };

        const handleCustomerSaved = (newCustomer) => {
            membershipSearch.value = newCustomer.membership_number;
            searchCustomer();
        };

        const viewCustomerHistory = () => {
            showCustomerHistoryModal.value = true;
        };

        const submitOrder = async () => {
            if (cartItems.value.length === 0) {
                addSystemMessage('error', 'خطا', 'سبد خرید خالی است', '❌');
                return;
            }

            // آپدیت نهایی محاسبات قبل از نمایش تأیید
            if (!pendingClientUuid.value) {
                pendingClientUuid.value = newClientUuid();
            }
            await updateRealTimeSummary();
            showConfirmationModal.value = true;
        };

        const buildOrderPayload = () => {
            if (!pendingClientUuid.value) {
                pendingClientUuid.value = newClientUuid();
            }
            return {
                client_uuid: pendingClientUuid.value,
                customer_membership_number: customer.value.membership_number,
                items: cartItems.value.map(item => ({
                    product_id: item.product.id,
                    quantity: item.quantity
                })),
                apply_discounts: applyDiscounts.value,
                redeem_points: Number(redeemPoints.value) || 0,
                payment_method: paymentMethod.value,
                notes: orderNotes.value,
                ...(appliedGiftCode.value ? { gift_card_code: appliedGiftCode.value } : {})
            };
        };

        const refreshQueue = () => {
            offlineQueue.value = getQueue();
        };

        // سفارش را روی دستگاه نگه می‌دارد تا وقتی اینترنت برگردد ارسال شود
        // موجودی کش‌شده منهای قلم‌های سفارش‌های در صف (که هنوز ارسال نشده‌اند)
        const offlineStockWarnings = (items) => {
            const queuedQty = {};
            getQueue().filter(entry => !entry.failed).forEach(entry => {
                (entry.payload?.items || []).forEach(line => {
                    queuedQty[line.product_id] = (queuedQty[line.product_id] || 0) + line.quantity;
                });
            });
            return items.flatMap(line => {
                const product = availableProducts.value.find(p => p.id === line.product_id);
                if (!product) return [];
                const available = product.stock_quantity - (queuedQty[line.product_id] || 0);
                if (line.quantity <= available) return [];
                return [`${product.name} (خواسته ${line.quantity}، موجود تقریبی ${Math.max(0, available)})`];
            });
        };

        const queueOrderLocally = (orderData) => {
            const stockWarnings = offlineStockWarnings(orderData.items);
            enqueueOrder(orderData, { stock_warnings: stockWarnings });
            if (stockWarnings.length) {
                addSystemMessage('error', 'احتمال کمبود موجودی', stockWarnings.join('، ') + ' — هنگام همگام‌سازی ممکن است رد شود', '⚠️');
            }
            refreshQueue();
            resetOrder();
            addSystemMessage('info', 'ذخیره روی دستگاه', 'اینترنت قطع است. سفارش روی این دستگاه ذخیره شد و هنگام اتصال ارسال می‌شود.', '📴');
        };

        // ارسال سفارش‌های صف به ترتیب؛ با قطع بودن اینترنت متوقف می‌شود
        const flushQueue = async () => {
            if (flushing.value || !navigator.onLine) {
                return;
            }
            const entries = getQueue().filter(entry => !entry.failed);
            if (entries.length === 0) {
                refreshQueue();
                return;
            }

            flushing.value = true;
            let sent = 0;
            try {
                for (const entry of entries) {
                    try {
                        await axios.post('/api/reception/orders', entry.payload);
                        removeQueueEntry(entry.client_uuid);
                        sent++;
                    } catch (error) {
                        const status = error.response?.status;
                        if (isNetworkError(error) || [401, 403].includes(status)) {
                            break;
                        }
                        const attempts = (entry.attempts || 0) + 1;
                        // خطای موقت سرور (۵xx، ۴۲۹): دفعه‌ی بعد دوباره تلاش می‌شود و تا پنج بار صبر می‌کند
                        const transient = status === 429 || (status >= 500 && status < 600);
                        if (transient && attempts < MAX_TRANSIENT_ATTEMPTS) {
                            updateQueueEntry(entry.client_uuid, {
                                attempts,
                                last_error: 'سرور موقتاً در دسترس نیست؛ تلاش دوباره انجام می‌شود'
                            });
                            break;
                        }
                        // سرور سفارش را رد کرده (مثلاً موجودی کافی نیست): نگه داشته می‌شود تا بررسی شود
                        updateQueueEntry(entry.client_uuid, {
                            failed: true,
                            attempts,
                            last_error: error.response?.data?.message || 'ثبت سفارش انجام نشد'
                        });
                    }
                }
            } finally {
                flushing.value = false;
                refreshQueue();
            }

            if (sent > 0) {
                addSystemMessage('success', 'همگام‌سازی', `${sent} سفارش ذخیره‌شده‌ی آفلاین ثبت شد`, '✅');
            }
        };

        const retryFailedOrder = (clientUuid) => {
            updateQueueEntry(clientUuid, { failed: false, last_error: null });
            refreshQueue();
            flushQueue();
        };

        const discardQueuedOrder = (clientUuid) => {
            if (!window.confirm('این سفارش از صف حذف شود؟ این کار برگشت‌پذیر نیست.')) return;
            removeQueueEntry(clientUuid);
            refreshQueue();
        };

        const onOnline = () => {
            isOnline.value = true;
            flushQueue();
        };

        const onOffline = () => {
            isOnline.value = false;
        };

        const scanBarcode = async () => {
            const code = barcodeInput.value.trim();
            if (!code) return;
            barcodeInput.value = '';

            // ابتدا در فهرست محلی (کار می‌کند حتی آفلاین)، سپس از سرور
            let product = availableProducts.value.find(p => p.barcode === code) || null;
            if (!product && navigator.onLine) {
                try {
                    const response = await axios.get(`/api/reception/products/barcode/${encodeURIComponent(code)}`);
                    product = response.data.product;
                } catch (error) {
                    if (error.response?.status === 404) {
                        addSystemMessage('error', 'خطا', 'کالایی با این بارکد پیدا نشد', '❌');
                    } else if (!isNetworkError(error)) {
                        addSystemMessage('error', 'خطا', 'خطا در جستجوی بارکد', '❌');
                    }
                    return;
                }
            }

            if (!product) {
                addSystemMessage('error', 'خطا', 'کالایی با این بارکد در فهرست ذخیره‌شده یافت نشد', '❌');
                return;
            }

            if (!availableProducts.value.some(p => p.id === product.id)) {
                availableProducts.value.push(product);
            }
            if (product.stock_quantity <= 0) {
                addSystemMessage('error', 'ناموجود', `${product.name} موجود نیست`, '⚠️');
                return;
            }
            addToCart(product);
        };

        const applyGiftCard = () => {
            const code = giftCardInput.value.trim().toUpperCase();
            if (!code) return;
            if (!navigator.onLine) {
                addSystemMessage('error', 'آفلاین', 'کارت هدیه فقط در حالت آنلاین قابل استفاده است', '📴');
                return;
            }
            appliedGiftCode.value = code;
            giftCardInput.value = '';
        };

        const removeGiftCard = () => {
            appliedGiftCode.value = '';
            giftCardInfo.value = null;
        };

        const confirmOrder = async () => {
            if (appliedGiftCode.value && giftCardInfo.value?.error) {
                addSystemMessage('error', 'خطا', giftCardInfo.value.error, '❌');
                return;
            }

            // آفلاین: مستقیم در صف ذخیره می‌شود
            if (!navigator.onLine) {
                if (appliedGiftCode.value) {
                    addSystemMessage('error', 'آفلاین', 'کارت هدیه فقط در حالت آنلاین قابل استفاده است', '📴');
                    return;
                }
                showConfirmationModal.value = false;
                queueOrderLocally(buildOrderPayload());
                return;
            }

            orderLoading.value = true;
            try {
                const orderData = buildOrderPayload();

                const response = await axios.post('/api/reception/orders', orderData);
                pendingClientUuid.value = null;
                
                notificationStore.addNotification({
                    type: 'success',
                    title: 'سفارش ثبت شد',
                    message: `سفارش #${response.data.order.order_number} با موفقیت ثبت شد`
                        + (response.data.order.loyalty_points_earned > 0 ? ` — ${response.data.order.loyalty_points_earned} امتیاز کسب شد` : ''),
                    duration: 5000
                });

                if (autoPrintReceipt.value) {
                    printOrderById(response.data.order.id);
                }

                resetOrder();
                
                setTimeout(() => {
                    router.push('/reception/orders');
                }, 2000);
                
            } catch (error) {
                console.error('Error submitting order:', error);
                if (isNetworkError(error)) {
                    showConfirmationModal.value = false;
                    orderLoading.value = false;
                    queueOrderLocally(buildOrderPayload());
                    return;
                }
                const message = error.response?.data?.message || 'خطا در ثبت سفارش';
                addSystemMessage('error', 'خطا', message, '❌');
            } finally {
                orderLoading.value = false;
                showConfirmationModal.value = false;
            }
        };

        // ---- تصویر محصول و چاپ رسید ----
        const failedImages = ref({});
        const autoPrintReceipt = ref(localStorage.getItem('receipt_auto_print') === '1');
        watch(autoPrintReceipt, (value) => {
            localStorage.setItem('receipt_auto_print', value ? '1' : '0');
        });

        const printOrderById = async (orderId) => {
            try {
                const { data } = await axios.get(`/api/reception/orders/${orderId}`);
                printReceipt(data.order);
            } catch (error) {
                console.error('Error printing receipt:', error);
                notificationStore.addNotification({
                    type: 'error',
                    title: 'چاپ رسید',
                    message: 'رسید چاپ نشد. از صفحه‌ی سفارشات می‌توانید دوباره چاپ کنید.'
                });
            }
        };

        // ---- سبد معلق ----
        const heldCarts = ref([]);
        const heldCount = ref(0);
        const heldLoading = ref(false);
        const heldBusy = ref(false);
        const showHeldCarts = ref(false);
        const holding = ref(false);

        const errorText = (error, fallback) => {
            const errors = error.response?.data?.errors;
            if (errors) {
                const first = Object.values(errors).flat()[0];
                if (first) return first;
            }
            return error.response?.data?.message || fallback;
        };

        const loadHeldCarts = async () => {
            if (!isOnline.value) return;
            try {
                const response = await axios.get('/api/reception/held-carts');
                heldCarts.value = response.data.held_carts || [];
                heldCount.value = heldCarts.value.length;
            } catch (error) {
                console.error('Error loading held carts:', error);
            }
        };

        const openHeldCarts = async () => {
            showHeldCarts.value = true;
            heldLoading.value = true;
            await loadHeldCarts();
            heldLoading.value = false;
        };

        const holdCurrentCart = async () => {
            if (holding.value || cartItems.value.length === 0) return;

            holding.value = true;
            try {
                await axios.post('/api/reception/held-carts', {
                    customer_membership_number: customer.value?.membership_number || null,
                    items: cartItems.value.map(item => ({ product_id: item.product.id, quantity: item.quantity })),
                    notes: orderNotes.value.trim() || null,
                });

                resetOrder();
                addSystemMessage('success', 'معلق شد', 'سبد خرید معلق شد. می‌توانید سفارش بعدی را ثبت کنید.', '⏸');
                await loadHeldCarts();
            } catch (error) {
                console.error('Error holding cart:', error);
                addSystemMessage('error', 'خطا', errorText(error, 'معلق‌کردن سبد انجام نشد'), '❌');
            } finally {
                holding.value = false;
            }
        };

        const resumeHeldCart = async (id) => {
            if (heldBusy.value) return;

            if (cartItems.value.length > 0) {
                showHeldCarts.value = false;
                addSystemMessage('error', 'خطا', 'ابتدا سبد فعلی را معلق یا خالی کنید', '❌');
                return;
            }

            heldBusy.value = true;
            try {
                const { data } = await axios.post(`/api/reception/held-carts/${id}/resume`);
                showHeldCarts.value = false;
                resetOrder();

                if (data.customer_membership_number) {
                    membershipSearch.value = data.customer_membership_number;
                    await searchCustomer();
                }
                if (availableProducts.value.length === 0) {
                    await loadProducts();
                }

                for (const line of data.items) {
                    const product = availableProducts.value.find(p => p.id === line.product_id);
                    if (product) {
                        cart.value[product.id] = line.quantity;
                    }
                }

                orderNotes.value = data.notes || '';

                if (data.skipped?.length) {
                    addSystemMessage('info', 'بازیابی', `کالاهای ناموجود یا غیرفعال حذف شدند: ${data.skipped.join('، ')}`, '⚠️');
                }
                if (data.adjusted?.length) {
                    addSystemMessage('info', 'بازیابی', `تعداد برخی کالاها به موجودی فعلی محدود شد: ${data.adjusted.join('، ')}`, '⚠️');
                }
                addSystemMessage('success', 'بازیابی شد', 'سبد معلق بازیابی شد', '▶️');
                await loadHeldCarts();
            } catch (error) {
                console.error('Error resuming held cart:', error);
                addSystemMessage('error', 'خطا', errorText(error, 'بازیابی سبد انجام نشد. شاید قبلاً بازیابی شده است.'), '❌');
                await loadHeldCarts();
            } finally {
                heldBusy.value = false;
            }
        };

        const discardHeldCart = async (id) => {
            if (heldBusy.value) return;
            if (!confirm('این سبد معلق حذف شود؟')) return;

            heldBusy.value = true;
            try {
                await axios.delete(`/api/reception/held-carts/${id}`);
                await loadHeldCarts();
            } catch (error) {
                console.error('Error discarding held cart:', error);
                addSystemMessage('error', 'خطا', errorText(error, 'حذف سبد انجام نشد'), '❌');
            } finally {
                heldBusy.value = false;
            }
        };

        const resetOrder = () => {
            customer.value = null;
            redeemPoints.value = 0;
            redeemError.value = '';
            paymentMethod.value = 'cash';
            membershipSearch.value = '';
            cart.value = {};
            orderNotes.value = '';
            productSearch.value = '';
            activeCategory.value = 'all';
            systemMessages.value = [];
            appliedGiftCode.value = '';
            giftCardInput.value = '';
            giftCardInfo.value = null;
            barcodeInput.value = '';
            pendingClientUuid.value = null;
            realTimeSummary.value = {
                subtotal: 0,
                discount: 0,
                gift_amount: 0,
                total: 0,
                applied_discounts: []
            };
        };

        const addSystemMessage = (type, title, text, icon) => {
            const message = {
                id: Date.now() + Math.random(),
                type,
                title,
                text,
                icon
            };
            systemMessages.value.unshift(message);
            
            setTimeout(() => {
                removeMessage(message.id);
            }, 5000);
        };

        const removeMessage = (messageId) => {
            const index = systemMessages.value.findIndex(m => m.id === messageId);
            if (index !== -1) {
                systemMessages.value.splice(index, 1);
            }
        };

        // utility functions
        const getCustomerInitials = (customer) => {
            if (!customer || !customer.first_name || !customer.last_name) return '??';
            return (customer.first_name[0] + customer.last_name[0]).toUpperCase();
        };

        const getDiscountBadgeText = (discount) => {
            if (!discount) return 'تخفیف';
            
            switch (discount.type) {
                case 'percentage':
                    return `${discount.value}%`;
                case 'fixed_amount':
                    return `${formatPrice(discount.value)}`;
                case 'free_product':
                    return 'رایگان';
                default:
                    return 'تخفیف';
            }
        };

        // watchها
        watch(activeCategory, () => {
            productSearch.value = '';
        });

        // lifecycle
        onMounted(() => {
            window.addEventListener('online', onOnline);
            window.addEventListener('offline', onOffline);
            refreshQueue();
            loadProducts();
            flushQueue();
            loadHeldCarts();
        });

        onBeforeUnmount(() => {
            window.removeEventListener('online', onOnline);
            window.removeEventListener('offline', onOffline);
        });

        return {
            // داده‌ها
            membershipSearch,
            customer,
            redeemPoints,
            redeemError,
            paymentMethod,
            giftCardInput,
            appliedGiftCode,
            giftCardInfo,
            barcodeInput,
            isOnline,
            offlineQueue,
            flushing,
            usingCachedData,
            scanBarcode,
            applyGiftCard,
            removeGiftCard,
            flushQueue,
            retryFailedOrder,
            discardQueuedOrder,
            availableProducts,
            cartItems,
            orderNotes,
            productSearch,
            activeCategory,
            sortBy,
            applyDiscounts,
            
            // حالت‌ها
            orderLoading,
            searchLoading,
            productsLoading,
            
            // Modal‌ها
            showAddCustomerModal,
            showCustomerHistoryModal,
            showConfirmationModal,
            
            // پیام‌ها
            systemMessages,
            
            // computed
            totalQuantity,
            orderSummary,
            vatAmount,
            vatRate,
            subtotal,
            hasActiveDiscounts,
            filteredProducts,
            finalOrderData,
            realTimeSummary,
            
            // ثابت‌ها
            categories,
            
            // متدها
            searchCustomer,
            addToCart,
            increaseQuantity,
            decreaseQuantity,
            getProductQuantity,
            clearCart,
            clearCustomer,
            setActiveCategory,
            handleCustomerSaved,
            viewCustomerHistory,
            submitOrder,
            confirmOrder,
            resetOrder,
            removeMessage,
            heldCarts,
            heldCount,
            heldLoading,
            heldBusy,
            failedImages,
            autoPrintReceipt,
            showHeldCarts,
            holding,
            holdCurrentCart,
            openHeldCarts,
            resumeHeldCart,
            discardHeldCart,
            
            // utility functions
            formatPrice,
            formatDate,
            getCustomerInitials,
            getDiscountBadgeText
        };
    }
}
</script>

<style scoped src="./NewOrder.css"></style>