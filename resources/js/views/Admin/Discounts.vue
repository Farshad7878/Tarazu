<template>
    <div class="discounts-page">
        <div class="page-header">
            <h2><IconTicket class="emo-icon" aria-hidden="true" /> مدیریت تخفیف‌ها</h2>
            <button @click="showAddModal = true" class="btn-primary">
                <IconPlus class="emo-icon" aria-hidden="true" /> ایجاد تخفیف جدید
            </button>
        </div>

        <div class="filters">
            <div class="filter-group">
                <label>فیلتر بر اساس وضعیت:</label>
                <select v-model="filters.status" @change="fetchDiscounts()">
                    <option value="">همه</option>
                    <option value="active">فعال</option>
                    <option value="inactive">غیرفعال</option>
                </select>
            </div>

            <div class="filter-group">
                <label>فیلتر بر اساس نوع:</label>
                <select v-model="filters.type" @change="fetchDiscounts()">
                    <option value="">همه</option>
                    <option value="percentage">درصدی</option>
                    <option value="fixed_amount">مبلغ ثابت</option>
                    <option value="free_product">محصول رایگان</option>
                </select>
            </div>
        </div>

        <div class="table-container">
            <div class="table-scroll"><table class="discounts-table">
                <thead>
                    <tr>
                        <th>نام تخفیف</th>
                        <th>نوع</th>
                        <th>مقدار</th>
                        <th>هدف</th>
                        <th>شرط اعمال</th>
                        <th>پیام</th>
                        <th>وضعیت</th>
                        <th>تاریخ ایجاد</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="discount in discounts" :key="discount.id">
                        <td data-label="نام تخفیف">
                            <span class="discount-name">{{ discount.name }}</span>
                        </td>
                        <td data-label="نوع">
                            <span :class="['type-badge', `type-${discount.type}`]">
                                {{ getDiscountTypeFa(discount.type) }}
                            </span>
                        </td>
                        <td data-label="مقدار">
                            <span v-if="discount.type === 'percentage'" class="discount-value">
                                {{ discount.value }}%
                            </span>
                            <span v-else-if="discount.type === 'fixed_amount'" class="discount-value">
                                {{ formatPrice(discount.value) }}
                            </span>
                            <span v-else-if="discount.type === 'free_product'" class="discount-value">
                                {{ discount.free_product || 'محصول رایگان' }}
                            </span>
                        </td>
                        <td data-label="هدف">
                            <span class="target-badge">
                                {{ discount.target === 'all' ? 'همه مشتریان' : 'مشتری خاص' }}
                            </span>
                        </td>
                        <td data-label="شرط اعمال">
                            <div class="condition-info">
                                <span class="condition-type">{{ getConditionTypeFa(discount.condition_type) }}</span>
                                <span v-if="discount.condition_value" class="condition-value">
                                    {{ formatConditionValue(discount.condition_type, discount.condition_value) }}
                                </span>
                            </div>
                        </td>
                        <td data-label="پیام">
                            <span class="discount-message" :title="discount.message">
                                {{ truncateText(discount.message, 30) }}
                            </span>
                        </td>
                        <td data-label="وضعیت">
                            <div class="status-cell">
                                <span :class="['status-badge', discount.is_active ? 'status-active' : 'status-inactive']">
                                    {{ discount.is_active ? 'فعال' : 'غیرفعال' }}
                                </span>
                                <span v-if="discount.auto_apply" class="auto-apply-badge">
                                    خودکار
                                </span>
                            </div>
                        </td>
                        <td data-label="تاریخ ایجاد">{{ discount.created_at }}</td>
                        <td data-label="عملیات">
                            <div class="actions">
                                <button 
                                    @click="editDiscount(discount)"
                                    class="btn-edit"
                                    title="ویرایش"
                                >
                                    <IconPencil class="emo-icon" aria-hidden="true" />
                                </button>
                                <button 
                                    @click="toggleDiscountStatus(discount)"
                                    :class="['btn-toggle', discount.is_active ? 'btn-deactivate' : 'btn-activate']"
                                    :title="discount.is_active ? 'غیرفعال کردن' : 'فعال کردن'"
                                >
                                    <component :is="emojiIcon(discount.is_active ? '⏸' : '▶')" class="emo-icon" />
                                </button>
                                <button 
                                    @click="assignToCustomer(discount)"
                                    class="btn-assign"
                                    title="اختصاص به مشتری"
                                >
                                    <IconUser class="emo-icon" aria-hidden="true" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table></div>

            <div v-if="loading" class="loading">
                در حال بارگذاری...
            </div>

            <div v-if="!loading && discounts.length === 0" class="empty-state">
                <IconTicket class="emo-icon" aria-hidden="true" /> هیچ تخفیفی یافت نشد
            </div>
        </div>

        <!-- Modal ایجاد/ویرایش تخفیف -->
        <div v-if="showAddModal || showEditModal" class="modal-overlay">
            <div class="modal modal-lg">
                <div class="modal-header">
                    <h3>{{ showEditModal ? 'ویرایش تخفیف' : 'ایجاد تخفیف جدید' }}</h3>
                    <button @click="closeModal" class="close-btn">×</button>
                </div>
                
                <form @submit.prevent="submitDiscountForm" class="modal-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label>نام تخفیف *</label>
                            <input 
                                type="text" 
                                v-model="discountForm.name"
                                required
                                placeholder="مثال: تخفیف ویژه خریدهای بالای ۲ میلیون"
                            >
                        </div>
                        <div class="form-group">
                            <label>نوع تخفیف *</label>
                            <select v-model="discountForm.type" required @change="handleTypeChange">
                                <option value="">انتخاب کنید</option>
                                <option value="percentage">درصدی</option>
                                <option value="fixed_amount">مبلغ ثابت</option>
                                <option value="free_product">محصول رایگان</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label v-if="discountForm.type === 'percentage'">درصد تخفیف *</label>
                            <label v-else-if="discountForm.type === 'fixed_amount'">مبلغ تخفیف *</label>
                            <label v-else>مقدار</label>
                            
                            <input 
                                v-if="discountForm.type !== 'free_product'"
                                type="number" 
                                v-model="discountForm.value"
                                :required="discountForm.type !== 'free_product'"
                                :min="discountForm.type === 'percentage' ? 1 : 0"
                                :max="discountForm.type === 'percentage' ? 100 : null"
                                :step="discountForm.type === 'percentage' ? 1 : 1000"
                                :placeholder="discountForm.type === 'percentage' ? '20' : '50000'"
                            >
                            <select v-else v-model="discountForm.free_product_id">
                                <option value="">انتخاب محصول رایگان</option>
                                <option 
                                    v-for="product in availableProducts" 
                                    :key="product.id" 
                                    :value="product.id"
                                >
                                    {{ product.name }} - {{ formatPrice(product.price) }}
                                </option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>هدف تخفیف *</label>
                            <select v-model="discountForm.target" required>
                                <option value="all">همه مشتریان</option>
                                <option value="specific_customer">مشتری خاص</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>شرط اعمال *</label>
                            <select v-model="discountForm.condition_type" required @change="handleConditionChange">
                                <option value="">انتخاب کنید</option>
                                <option value="total_purchases">مجموع خریدها</option>
                                <option value="monthly_purchases">خریدهای ماهانه</option>
                                <option value="birthday">تولد مشتری</option>
                                <option value="manual">دستی (فقط توسط ادمین)</option>
                            </select>
                        </div>

                        <div class="form-group" v-if="showConditionValue">
                            <label>مقدار شرط *</label>
                            <input 
                                type="number" 
                                v-model="discountForm.condition_value"
                                required
                                min="0"
                                step="1000"
                                placeholder="2000000"
                            >
                            <span class="input-hint">تومان</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>پیام تخفیف *</label>
                        <textarea 
                            v-model="discountForm.message"
                            required
                            rows="3"
                            placeholder="پیامی که به مشتری نمایش داده می‌شود"
                            maxlength="500"
                        ></textarea>
                        <div class="char-counter">
                            {{ discountForm.message.length }}/500
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group checkbox-group">
                            <label class="checkbox-label">
                                <input 
                                    type="checkbox" 
                                    v-model="discountForm.is_active"
                                >
                                <span>تخفیف فعال باشد</span>
                            </label>
                        </div>

                        <div class="form-group checkbox-group">
                            <label class="checkbox-label">
                                <input 
                                    type="checkbox" 
                                    v-model="discountForm.auto_apply"
                                    :disabled="discountForm.condition_type === 'manual'"
                                >
                                <span>اعمال خودکار</span>
                            </label>
                        </div>

                        <div class="form-group checkbox-group">
                            <label class="checkbox-label">
                                <input 
                                    type="checkbox" 
                                    v-model="discountForm.auto_disable_after_use"
                                >
                                <span>غیرفعال پس از استفاده</span>
                            </label>
                        </div>
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
                            {{ formLoading ? 'در حال ذخیره...' : 'ذخیره تخفیف' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal اختصاص به مشتری -->
        <div v-if="showAssignModal" class="modal-overlay">
            <div class="modal">
                <div class="modal-header">
                    <h3><IconUser class="emo-icon" aria-hidden="true" /> اختصاص تخفیف به مشتری</h3>
                    <button @click="closeAssignModal" class="close-btn">×</button>
                </div>
                
                <div class="modal-form">
                    <div class="form-group">
                        <label>تخفیف:</label>
                        <div class="selected-discount">
                            {{ selectedDiscount?.name }}
                        </div>
                    </div>

                    <div class="form-group">
                        <label>انتخاب مشتری *</label>
                        <select v-model="assignForm.customer_id" required>
                            <option value="">انتخاب مشتری</option>
                            <option 
                                v-for="customer in availableCustomers" 
                                :key="customer.id" 
                                :value="customer.id"
                            >
                                {{ customer.full_name }} - {{ customer.membership_number }}
                            </option>
                        </select>
                    </div>

                    <div class="modal-actions">
                        <button 
                            type="button" 
                            @click="closeAssignModal"
                            class="btn-cancel"
                        >
                            انصراف
                        </button>
                        <button 
                            @click="submitAssignForm"
                            :disabled="assignFormLoading"
                            class="btn-primary"
                        >
                            {{ assignFormLoading ? 'در حال ثبت...' : 'اختصاص تخفیف' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { emojiIcon } from '../../utils/emojiIcons';
import { Pencil as IconPencil, Plus as IconPlus, Ticket as IconTicket, User as IconUser } from 'lucide-vue-next';
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { useNotificationStore } from '../../stores/notifications';

import { formatPrice } from '../../utils/format';
// اولین خطای فیلد از پاسخ ۴۲۲ لاراول (مثلاً «درصد تخفیف نمی‌تواند بیشتر از ۱۰۰ باشد.»)
const firstValidationError = (error) => {
    const errors = error.response?.data?.errors;
    if (!errors) return null;
    const first = Object.values(errors)[0];
    return Array.isArray(first) ? first[0] : null;
};

export default {
    methods: { emojiIcon },
    components: { IconPencil, IconPlus, IconTicket, IconUser },
    name: 'Discounts',
    setup() {
        const discounts = ref([]);
        const loading = ref(false);
        const availableProducts = ref([]);
        const availableCustomers = ref([]);
        
        const showAddModal = ref(false);
        const showEditModal = ref(false);
        const showAssignModal = ref(false);
        const formLoading = ref(false);
        const assignFormLoading = ref(false);
        
        const selectedDiscount = ref(null);
        const discountForm = ref({
            name: '',
            type: '',
            value: null,
            target: 'all',
            condition_type: '',
            condition_value: null,
            free_product_id: null,
            message: '',
            is_active: true,
            auto_apply: false,
            auto_disable_after_use: true
        });

        const assignForm = ref({
            customer_id: ''
        });

        const filters = ref({
            status: '',
            type: ''
        });

        const notificationStore = useNotificationStore();

        // محاسبه نمایش فیلد مقدار شرط
        const showConditionValue = computed(() => {
            return ['total_purchases', 'monthly_purchases'].includes(discountForm.value.condition_type);
        });

        const fetchDiscounts = async () => {
            loading.value = true;
            try {
                const params = {};
                if (filters.value.status) {
                    params.is_active = filters.value.status === 'active';
                }
                if (filters.value.type) {
                    params.type = filters.value.type;
                }

                const response = await axios.get('/api/admin/discounts', { params });
                discounts.value = response.data.discounts;
            } catch (error) {
                console.error('Error fetching discounts:', error);
                notificationStore.addNotification({
                    type: 'error',
                    title: 'خطا',
                    message: 'خطا در بارگذاری تخفیف‌ها'
                });
            } finally {
                loading.value = false;
            }
        };

        const fetchAvailableProducts = async () => {
            try {
                const response = await axios.get('/api/admin/products');
                availableProducts.value = response.data.products;
            } catch (error) {
                console.error('Error fetching products:', error);
            }
        };

        const fetchAvailableCustomers = async () => {
            try {
                const response = await axios.get('/api/admin/customers');
                availableCustomers.value = response.data.customers;
            } catch (error) {
                console.error('Error fetching customers:', error);
            }
        };

        const submitDiscountForm = async () => {
            formLoading.value = true;
            try {
                const url = showEditModal.value 
                    ? `/api/admin/discounts/${selectedDiscount.value.id}`
                    : '/api/admin/discounts';
                
                const method = showEditModal.value ? 'put' : 'post';
                
                // تبدیل مقادیر عددی
                const formData = { ...discountForm.value };
                if (formData.value) formData.value = parseFloat(formData.value);
                if (formData.condition_value) formData.condition_value = parseFloat(formData.condition_value);
                
                await axios[method](url, formData);
                
                closeModal();
                fetchDiscounts();
                
                notificationStore.addNotification({
                    type: 'success',
                    title: 'موفقیت',
                    message: showEditModal.value ? 'تخفیف با موفقیت بروزرسانی شد' : 'تخفیف جدید با موفقیت ایجاد شد'
                });
            } catch (error) {
                console.error('Error saving discount:', error);
                notificationStore.addNotification({
                    type: 'error',
                    title: 'خطا',
                    message: firstValidationError(error) || error.response?.data?.message || 'خطا در ذخیره تخفیف'
                });
            } finally {
                formLoading.value = false;
            }
        };

        const editDiscount = (discount) => {
            selectedDiscount.value = discount;
            discountForm.value = {
                name: discount.name,
                type: discount.type,
                value: discount.value,
                target: discount.target,
                condition_type: discount.condition_type,
                condition_value: discount.condition_value,
                free_product_id: discount.free_product_id,
                message: discount.message,
                is_active: discount.is_active,
                auto_apply: discount.auto_apply,
                auto_disable_after_use: discount.auto_disable_after_use
            };
            showEditModal.value = true;
        };

        const toggleDiscountStatus = async (discount) => {
            try {
                await axios.put(`/api/admin/discounts/${discount.id}`, {
                    is_active: !discount.is_active
                });
                
                fetchDiscounts();
                
                notificationStore.addNotification({
                    type: 'success',
                    title: 'موفقیت',
                    message: `تخفیف ${!discount.is_active ? 'فعال' : 'غیرفعال'} شد`
                });
            } catch (error) {
                console.error('Error toggling discount status:', error);
                notificationStore.addNotification({
                    type: 'error',
                    title: 'خطا',
                    message: 'خطا در تغییر وضعیت تخفیف'
                });
            }
        };

        const assignToCustomer = (discount) => {
            selectedDiscount.value = discount;
            assignForm.value.customer_id = '';
            showAssignModal.value = true;
        };

        const submitAssignForm = async () => {
            if (!assignForm.value.customer_id) return;

            assignFormLoading.value = true;
            try {
                await axios.post(`/api/admin/discounts/${selectedDiscount.value.id}/assign`, assignForm.value);
                
                closeAssignModal();
                
                notificationStore.addNotification({
                    type: 'success',
                    title: 'موفقیت',
                    message: 'تخفیف با موفقیت به مشتری اختصاص داده شد'
                });
            } catch (error) {
                console.error('Error assigning discount:', error);
                notificationStore.addNotification({
                    type: 'error',
                    title: 'خطا',
                    message: firstValidationError(error) || error.response?.data?.message || 'خطا در اختصاص تخفیف'
                });
            } finally {
                assignFormLoading.value = false;
            }
        };

        const closeModal = () => {
            showAddModal.value = false;
            showEditModal.value = false;
            selectedDiscount.value = null;
            discountForm.value = {
                name: '',
                type: '',
                value: null,
                target: 'all',
                condition_type: '',
                condition_value: null,
                free_product_id: null,
                message: '',
                is_active: true,
                auto_apply: false,
                auto_disable_after_use: true
            };
        };

        const closeAssignModal = () => {
            showAssignModal.value = false;
            selectedDiscount.value = null;
            assignForm.value.customer_id = '';
        };

        const handleTypeChange = () => {
            // ریست کردن مقادیر وابسته
            if (discountForm.value.type !== 'free_product') {
                discountForm.value.free_product_id = null;
            }
        };

        const handleConditionChange = () => {
            // برای شرط تولد یا دستی، مقدار شرط پاک شود
            if (['birthday', 'manual'].includes(discountForm.value.condition_type)) {
                discountForm.value.condition_value = null;
            }
            
            // برای شرط دستی، اعمال خودکار غیرفعال شود
            if (discountForm.value.condition_type === 'manual') {
                discountForm.value.auto_apply = false;
            }
        };

        const getDiscountTypeFa = (type) => {
            const types = {
                percentage: 'درصدی',
                fixed_amount: 'مبلغ ثابت',
                free_product: 'محصول رایگان'
            };
            return types[type] || type;
        };

        const getConditionTypeFa = (conditionType) => {
            const conditions = {
                total_purchases: 'مجموع خرید',
                monthly_purchases: 'خرید ماهانه',
                birthday: 'تولد',
                manual: 'دستی'
            };
            return conditions[conditionType] || conditionType;
        };

        const formatConditionValue = (conditionType, value) => {
            if (['total_purchases', 'monthly_purchases'].includes(conditionType)) {
                return `${formatPrice(value)} تومان`;
            }
            return value;
        };

        const truncateText = (text, length) => {
            if (text.length <= length) return text;
            return text.substring(0, length) + '...';
        };

        onMounted(() => {
            fetchDiscounts();
            fetchAvailableProducts();
            fetchAvailableCustomers();
        });

        return {
            discounts,
            loading,
            availableProducts,
            availableCustomers,
            showAddModal,
            showEditModal,
            showAssignModal,
            formLoading,
            assignFormLoading,
            selectedDiscount,
            discountForm,
            assignForm,
            filters,
            showConditionValue,
            fetchDiscounts,
            submitDiscountForm,
            editDiscount,
            toggleDiscountStatus,
            assignToCustomer,
            submitAssignForm,
            closeModal,
            closeAssignModal,
            handleTypeChange,
            handleConditionChange,
            formatPrice,
            getDiscountTypeFa,
            getConditionTypeFa,
            formatConditionValue,
            truncateText
        };
    }
}
</script>

<style scoped src="./Discounts.css"></style>