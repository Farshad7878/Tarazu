<template>
    <div class="products-page">
        <div class="page-header">
            <h2>📦 مدیریت محصولات</h2>
            <div class="header-actions">
                <router-link to="/admin/barcode-labels" class="btn-secondary">🏷️ چاپ برچسب</router-link>
                <button @click="showAddModal = true" class="btn-primary">
                    ➕ افزودن محصول جدید
                </button>
            </div>
        </div>

        <div class="filters">
            <div class="filter-group">
                <label>فیلتر بر اساس دسته‌بندی:</label>
                <select v-model="filters.category_id" @change="fetchProducts(1)">
                    <option value="">همه</option>
                    <option v-for="category in categories" :key="category.id" :value="category.id">
                        {{ category.icon }} {{ category.name }}
                    </option>
                </select>
            </div>

            <div class="filter-group">
                <label>فیلتر بر اساس وضعیت:</label>
                <select v-model="filters.status" @change="fetchProducts(1)">
                    <option value="">همه</option>
                    <option value="approved">تایید شده</option>
                    <option value="pending">در انتظار تایید</option>
                </select>
            </div>

            <div class="search-box">
                <input
                    type="text"
                    v-model="search"
                    placeholder="جستجو بر اساس نام محصول..."
                    @input="handleSearch"
                >
                <span class="search-icon">🔍</span>
            </div>
        </div>

        <div class="table-container">
            <table class="products-table">
                <thead>
                    <tr>
                        <th>نام محصول</th>
                        <th>دسته‌بندی</th>
                        <th>قیمت (تومان)</th>
                        <th>موجودی</th>
                        <th>فعال</th>
                        <th>وضعیت</th>
                        <th>ایجاد کننده</th>
                        <th>تاریخ ایجاد</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="product in products" :key="product.id">
                        <td>
                            <div class="product-info">
                                <span class="product-name">{{ product.name }}</span>
                                <small v-if="product.barcode" class="barcode-text">▌ {{ product.barcode }}</small>
                                <small v-if="product.variant_label" class="variant-text">🧩 نسخه‌ی «{{ product.variant_label }}» از {{ product.parent?.name }}</small>
                                <small v-else-if="product.variants_count > 0" class="variant-text">🧩 {{ product.variants_count }} نسخه</small>
                            </div>
                        </td>
                        <td>
                            <span class="category-badge">
                                {{ product.category_icon }} {{ product.category_name }}
                            </span>
                        </td>
                        <td>
                            <span class="price">
                                {{ formatPrice(product.price) }}
                            </span>
                        </td>
                        <td>
                            <span :class="['stock-badge', product.stock_quantity <= product.min_stock ? 'stock-low' : 'stock-ok']">
                                {{ formatPrice(product.stock_quantity) }}
                            </span>
                            <small class="form-hint">حداقل: {{ formatPrice(product.min_stock) }}</small>
                        </td>
                        <td>
                            <span :class="['status-badge', product.is_active ? 'status-approved' : 'status-pending']">
                                {{ product.is_active ? 'فعال' : 'غیرفعال' }}
                            </span>
                        </td>
                        <td>
                            <span :class="['status-badge', `status-${product.status}`]">
                                {{ product.status_fa }}
                            </span>
                        </td>
                        <td>{{ product.created_by }}</td>
                        <td>{{ product.created_at }}</td>
                        <td>
                            <div class="actions">
                                <button
                                    @click="openStockModal(product)"
                                    class="btn-stock"
                                    title="موجودی و تاریخچه"
                                >
                                    📊
                                </button>
                                <button
                                    v-if="!product.parent_product_id"
                                    @click="variantProduct = product"
                                    class="btn-stock"
                                    title="افزودن نسخه (سایز/رنگ)"
                                >
                                    🧩
                                </button>
                                <button 
                                    @click="editProduct(product)"
                                    class="btn-edit"
                                    title="ویرایش"
                                >
                                    ✏️
                                </button>
                                <button 
                                    @click="deleteProduct(product.id)"
                                    class="btn-delete"
                                    title="حذف"
                                    :disabled="product.status === 'approved'"
                                >
                                    🗑️
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="loading" class="loading">
                در حال بارگذاری...
            </div>

            <div v-if="!loading && products.length === 0" class="empty-state">
                📦 هیچ محصولی یافت نشد
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

        <!-- Modal موجودی و تاریخچه‌ی انبار -->
        <div v-if="showStockModal && stockProduct" class="modal-overlay">
            <div class="modal modal-wide">
                <div class="modal-header">
                    <h3>📦 موجودی — {{ stockProduct.name }}</h3>
                    <button @click="closeStockModal" class="close-btn">×</button>
                </div>

                <div class="stock-summary">
                    <span>موجودی کل (همه‌ی شعبه‌ها): <strong>{{ stockCurrent }}</strong></span>
                    <span>موجودی شعبه‌ی انتخابی: <strong>{{ selectedBranchQuantity }}</strong></span>
                    <span>حداقل موجودی: {{ stockProduct.min_stock }}</span>
                </div>

                <form @submit.prevent="submitStockAdjustment" class="modal-form">
                    <div class="form-group">
                        <label>شعبه‌ی مورد نظر *</label>
                        <select v-model.number="stockBranchId" @change="fetchStockHistory(1)" required>
                            <option v-for="branch in stockBranches" :key="branch.id" :value="branch.id">
                                {{ branch.name }}{{ branch.is_active ? '' : ' (غیرفعال)' }} — موجودی: {{ branch.quantity }}
                            </option>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group form-group-wide">
                            <label>نوع تعدیل *</label>
                            <select v-model="stockForm.type">
                                <option value="damage">ضایعات / خرابی (کاهش)</option>
                                <option value="count">شمارش انبار (تنظیم به تعداد شمرده‌شده)</option>
                                <option value="correction">تعدیل دستی (افزایش یا کاهش)</option>
                            </select>
                        </div>

                        <div v-if="stockForm.type === 'count'" class="form-group">
                            <label>تعداد شمرده‌شده *</label>
                            <input type="number" min="0" step="1" v-model.number="stockForm.counted_quantity" required>
                        </div>
                        <div v-else class="form-group">
                            <label>{{ stockForm.type === 'damage' ? 'تعداد ضایعات *' : 'مقدار تعدیل * (منفی برای کاهش)' }}</label>
                            <input
                                type="number"
                                :min="stockForm.type === 'damage' ? 1 : undefined"
                                step="1"
                                v-model.number="stockForm.quantity"
                                required
                            >
                        </div>
                    </div>

                    <div class="form-group">
                        <label>
                            دلیل {{ stockForm.type === 'count' ? '' : '*' }}
                        </label>
                        <input type="text" maxlength="255" v-model="stockForm.reason" :required="stockForm.type !== 'count'">
                    </div>

                    <p v-if="stockError" class="stock-error">{{ stockError }}</p>

                    <div class="modal-actions">
                        <button type="button" @click="closeStockModal" class="btn-cancel">بستن</button>
                        <button type="submit" :disabled="stockSubmitting" class="btn-primary">
                            {{ stockSubmitting ? 'در حال ثبت...' : 'ثبت تعدیل' }}
                        </button>
                    </div>
                </form>

                <div class="stock-history">
                    <h4>تاریخچه‌ی موجودی</h4>
                    <div v-if="stockLoading" class="loading">در حال بارگذاری...</div>
                    <div v-else-if="stockHistory.length === 0" class="empty-state">هنوز تغییری ثبت نشده است</div>
                    <table v-else class="stock-table">
                        <thead>
                            <tr>
                                <th>تاریخ</th>
                                <th>نوع</th>
                                <th>تغییر</th>
                                <th>موجودی</th>
                                <th>دلیل / سفارش</th>
                                <th>کاربر</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in stockHistory" :key="row.id">
                                <td>{{ row.created_at }}</td>
                                <td>{{ row.type_label }}</td>
                                <td :class="row.quantity_change > 0 ? 'change-plus' : (row.quantity_change < 0 ? 'change-minus' : '')">
                                    {{ row.quantity_change > 0 ? '+' : '' }}{{ row.quantity_change }}
                                </td>
                                <td>{{ row.quantity_before }} ← {{ row.quantity_after }}</td>
                                <td>
                                    {{ row.reason || row.order_number || '—' }}
                                </td>
                                <td>{{ row.user_name || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-if="stockPagination.last_page > 1" class="pagination">
                        <button @click="fetchStockHistory(stockPagination.current_page - 1)" :disabled="stockPagination.current_page === 1" class="pagination-btn">قبلی</button>
                        <span class="pagination-info">صفحه {{ stockPagination.current_page }} از {{ stockPagination.last_page }}</span>
                        <button @click="fetchStockHistory(stockPagination.current_page + 1)" :disabled="stockPagination.current_page === stockPagination.last_page" class="pagination-btn">بعدی</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal افزودن/ویرایش محصول -->
        <div v-if="showAddModal || showEditModal" class="modal-overlay">
            <div class="modal">
                <div class="modal-header">
                    <h3>{{ showEditModal ? 'ویرایش محصول' : 'افزودن محصول جدید' }}</h3>
                    <button @click="closeModal" class="close-btn">×</button>
                </div>
                
                <form @submit.prevent="submitProductForm" class="modal-form">
                    <div class="form-group">
                        <label>نام محصول *</label>
                        <input 
                            type="text" 
                            v-model="productForm.name"
                            required
                            placeholder="مثال: نام محصول"
                        >
                    </div>

                    <div class="form-group">
                        <label>دسته‌بندی *</label>
                        <select v-model="productForm.category_id" required>
                            <option value="">انتخاب کنید</option>
                            <option v-for="category in categories" :key="category.id" :value="category.id">
                                {{ category.icon }} {{ category.name }}{{ category.is_active ? '' : ' (غیرفعال)' }}
                            </option>
                        </select>
                        <small v-if="!categories.length" class="form-hint">
                            هنوز دسته‌بندی‌ای تعریف نشده است. ابتدا از بخش «مدیریت دسته‌بندی‌ها» یک دسته‌بندی بسازید.
                        </small>
                    </div>

                    <div class="form-group">
                        <label>قیمت (تومان) *</label>
                        <input 
                            type="number" 
                            v-model="productForm.price"
                            required
                            min="0"
                            step="1000"
                            placeholder="25000"
                        >
                    </div>

                    <div class="form-group">
                        <label>موجودی فعلی * <small class="form-hint">(تغییر، به شعبه‌ی اصلی اعمال می‌شود؛ برای سایر شعبه‌ها از «موجودی» استفاده کنید)</small></label>
                        <input
                            type="number"
                            v-model="productForm.stock_quantity"
                            required
                            min="0"
                            step="1"
                        >
                    </div>

                    <div class="form-group">
                        <label>حداقل موجودی (هشدار کمبود)</label>
                        <input
                            type="number"
                            v-model="productForm.min_stock"
                            min="0"
                            step="1"
                        >
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" v-model="productForm.is_active">
                            محصول فعال باشد (در سفارش‌گیری نمایش داده شود)
                        </label>
                    </div>

                    <div v-if="showEditModal" class="form-group">
                        <label>وضعیت</label>
                        <select v-model="productForm.status">
                            <option value="pending">در انتظار تایید</option>
                            <option value="approved">تایید شده</option>
                        </select>
                    </div>

                    <div class="form-group image-field">
                        <label>تصویر محصول</label>
                        <div class="image-row">
                            <div class="image-preview">
                                <img v-if="imagePreview" :src="imagePreview" alt="پیش‌نمایش تصویر محصول">
                                <span v-else>بدون تصویر</span>
                            </div>
                            <div class="image-actions">
                                <input
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    :disabled="formLoading"
                                    @change="onImageSelected"
                                >
                                <button
                                    v-if="imagePreview"
                                    type="button"
                                    class="btn-cancel"
                                    :disabled="formLoading"
                                    @click="removeImage"
                                >
                                    حذف تصویر
                                </button>
                            </div>
                        </div>
                        <small class="form-hint">JPG، PNG یا WebP تا ۲ مگابایت. تصویر با ذخیره‌ی محصول آپلود می‌شود.</small>
                        <small v-if="imageError" class="form-error">{{ imageError }}</small>
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
        <ProductVariantModal
            v-if="variantProduct"
            :product="variantProduct"
            @close="variantProduct = null"
            @saved="onVariantSaved"
        />
    </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

import { formatPrice } from '../../utils/format';
import ProductVariantModal from './ProductVariantModal.vue';

export default {
    name: 'Products',
    components: { ProductVariantModal },
    setup() {
        const products = ref([]);
        const loading = ref(false);
        const search = ref('');
        const searchTimeout = ref(null);
        
        const showAddModal = ref(false);
        const showEditModal = ref(false);
        const formLoading = ref(false);
        
        const selectedProduct = ref(null);
        const productForm = ref({
            name: '',
            category_id: '',
            price: '',
            stock_quantity: 0,
            min_stock: 5,
            is_active: true,
            status: 'approved'
        });

        const categories = ref([]);

        // تصویر محصول: فایل بعد از ذخیره‌ی خود محصول آپلود می‌شود
        const MAX_IMAGE_BYTES = 2 * 1024 * 1024;
        const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
        const imageFile = ref(null);
        const imagePreview = ref(null);
        const imageRemoved = ref(false);
        const imageError = ref('');

        const revokePreview = () => {
            if (imagePreview.value && imagePreview.value.startsWith('blob:')) {
                URL.revokeObjectURL(imagePreview.value);
            }
        };

        const resetImageState = () => {
            revokePreview();
            imageFile.value = null;
            imagePreview.value = null;
            imageRemoved.value = false;
            imageError.value = '';
        };

        const onImageSelected = (event) => {
            const file = event.target.files?.[0];
            imageError.value = '';
            if (!file) return;

            if (!ALLOWED_IMAGE_TYPES.includes(file.type)) {
                imageError.value = 'فقط فایل‌های JPG، PNG یا WebP پذیرفته می‌شوند.';
                event.target.value = '';
                return;
            }
            if (file.size > MAX_IMAGE_BYTES) {
                imageError.value = 'حجم تصویر بیشتر از ۲ مگابایت است.';
                event.target.value = '';
                return;
            }

            revokePreview();
            imageFile.value = file;
            imageRemoved.value = false;
            imagePreview.value = URL.createObjectURL(file);
        };

        const removeImage = () => {
            revokePreview();
            imageFile.value = null;
            imagePreview.value = null;
            imageRemoved.value = true;
            imageError.value = '';
        };

        // موجودی و تاریخچه‌ی انبار
        const showStockModal = ref(false);
        const stockProduct = ref(null);
        const stockCurrent = ref(0);
        const stockHistory = ref([]);
        const stockLoading = ref(false);
        const stockSubmitting = ref(false);
        const stockError = ref('');
        const stockPagination = ref({ current_page: 1, last_page: 1, total: 0 });
        const stockBranches = ref([]);
        const stockBranchId = ref(null);
        const selectedBranchQuantity = computed(() => stockBranches.value.find((b) => b.id === stockBranchId.value)?.quantity ?? 0);
        const stockForm = ref({ type: 'damage', quantity: 1, counted_quantity: 0, reason: '' });

        const filters = ref({
            category_id: '',
            status: ''
        });

        const pagination = ref({
            current_page: 1,
            last_page: 1,
            per_page: 20,
            total: 0
        });

        const fetchCategories = async () => {
            try {
                const response = await axios.get('/api/admin/product-categories');
                categories.value = response.data.categories;
            } catch (error) {
                console.error('Error fetching categories:', error);
            }
        };

        const fetchProducts = async (page = 1) => {
            loading.value = true;
            try {
                const params = { page, ...filters.value };
                if (search.value) {
                    params.search = search.value;
                }

                const response = await axios.get('/api/admin/products', { params });
                products.value = response.data.products;
                pagination.value = response.data.pagination;
            } catch (error) {
                console.error('Error fetching products:', error);
                // نمایش خطا
            } finally {
                loading.value = false;
            }
        };

        const handleSearch = () => {
            clearTimeout(searchTimeout.value);
            searchTimeout.value = setTimeout(() => {
                fetchProducts(1);
            }, 500);
        };

        const changePage = (page) => {
            if (page >= 1 && page <= pagination.value.last_page) {
                fetchProducts(page);
            }
        };

        const submitProductForm = async () => {
            formLoading.value = true;
            try {
                const url = showEditModal.value 
                    ? `/api/admin/products/${selectedProduct.value.id}`
                    : '/api/admin/products';
                
                const method = showEditModal.value ? 'put' : 'post';

                const { data } = await axios[method](url, productForm.value);
                const productId = showEditModal.value ? selectedProduct.value.id : data.product?.id;

                // خطای تصویر، ذخیره‌ی خود محصول را برنمی‌گرداند
                try {
                    if (imageFile.value) {
                        const form = new FormData();
                        form.append('image', imageFile.value);
                        await axios.post(`/api/admin/products/${productId}/image`, form);
                    } else if (imageRemoved.value && showEditModal.value) {
                        await axios.delete(`/api/admin/products/${productId}/image`);
                    }
                } catch (uploadError) {
                    console.error('Error saving product image:', uploadError);
                    const reason = uploadError.response?.data?.errors?.image?.[0] || uploadError.response?.data?.message || 'خطای نامشخص';
                    alert(`محصول ذخیره شد، اما تصویر ذخیره نشد: ${reason}`);
                }

                closeModal();
                fetchProducts();

                // نمایش پیام موفقیت
            } catch (error) {
                console.error('Error saving product:', error);
                // نمایش خطا
            } finally {
                formLoading.value = false;
            }
        };

        const editProduct = (product) => {
            selectedProduct.value = product;
            resetImageState();
            imagePreview.value = product.image_url || null;
            productForm.value = {
                name: product.name,
                category_id: product.category_id,
                price: product.price,
                stock_quantity: product.stock_quantity,
                min_stock: product.min_stock,
                is_active: !!product.is_active,
                status: product.status
            };
            showEditModal.value = true;
        };

        const deleteProduct = async (productId) => {
            if (!confirm('آیا از حذف این محصول اطمینان دارید؟')) {
                return;
            }

            try {
                await axios.delete(`/api/admin/products/${productId}`);
                fetchProducts();
                // نمایش پیام موفقیت
            } catch (error) {
                console.error('Error deleting product:', error);
                if (error.response?.status === 422) {
                    // پیام سرور دلیل دقیق (سفارش یا تخفیف هدیه) را می‌گوید
                    alert(error.response.data?.message || 'امکان حذف محصول وجود ندارد.');
                } else {
                    alert('خطا در حذف محصول');
                }
            }
        };

        const fetchStockHistory = async (page = 1) => {
            if (!stockProduct.value) return;
            stockLoading.value = true;
            try {
                const params = { page };
                if (stockBranchId.value) params.branch_id = stockBranchId.value;
                const response = await axios.get(`/api/admin/products/${stockProduct.value.id}/stock-history`, { params });
                stockHistory.value = response.data.history;
                stockPagination.value = response.data.pagination;
                stockCurrent.value = response.data.product.stock_quantity;
                stockBranches.value = response.data.branches;
                stockBranchId.value = response.data.selected_branch_id;
            } catch (error) {
                console.error('Error fetching stock history:', error);
                stockError.value = 'خطا در بارگذاری تاریخچه‌ی موجودی';
            } finally {
                stockLoading.value = false;
            }
        };

        const openStockModal = (product) => {
            stockProduct.value = product;
            stockCurrent.value = product.stock_quantity;
            stockError.value = '';
            stockForm.value = { type: 'damage', quantity: 1, counted_quantity: product.stock_quantity, reason: '' };
            stockBranches.value = [];
            stockBranchId.value = null;
            showStockModal.value = true;
            fetchStockHistory(1);
        };

        const closeStockModal = () => {
            showStockModal.value = false;
            stockProduct.value = null;
            stockHistory.value = [];
        };

        const submitStockAdjustment = async () => {
            stockError.value = '';
            stockSubmitting.value = true;
            try {
                const payload = { type: stockForm.value.type, reason: stockForm.value.reason || null, branch_id: stockBranchId.value };
                if (stockForm.value.type === 'count') {
                    payload.counted_quantity = stockForm.value.counted_quantity;
                } else {
                    payload.quantity = stockForm.value.quantity;
                }

                const response = await axios.post(`/api/admin/products/${stockProduct.value.id}/stock-adjustments`, payload);
                stockCurrent.value = response.data.stock_quantity;
                stockForm.value.reason = '';
                stockForm.value.quantity = 1;
                stockForm.value.counted_quantity = response.data.stock_quantity;

                await fetchStockHistory(1);
                fetchProducts(pagination.value.current_page);
            } catch (error) {
                const errors = error.response?.data?.errors;
                stockError.value = (errors && Object.values(errors).flat()[0])
                    || error.response?.data?.message
                    || 'خطا در ثبت تعدیل موجودی';
            } finally {
                stockSubmitting.value = false;
            }
        };

        // نسخه‌ها (سایز/رنگ)
        const variantProduct = ref(null);
        const onVariantSaved = () => {
            fetchProducts(pagination.value.current_page);
        };

        const closeModal = () => {
            showAddModal.value = false;
            showEditModal.value = false;
            selectedProduct.value = null;
            resetImageState();
            productForm.value = {
                name: '',
                category_id: '',
                price: '',
                stock_quantity: 0,
                min_stock: 5,
                is_active: true,
                status: 'approved'
            };
        };

        onMounted(() => {
            fetchCategories();
            fetchProducts();
        });

        return {
            imagePreview,
            imageError,
            onImageSelected,
            removeImage,
            products,
            loading,
            search,
            filters,
            showAddModal,
            showEditModal,
            formLoading,
            selectedProduct,
            productForm,
            categories,
            pagination,
            handleSearch,
            changePage,
            submitProductForm,
            editProduct,
            deleteProduct,
            closeModal,
            variantProduct,
            onVariantSaved,
            formatPrice,
            fetchProducts,
            showStockModal,
            stockProduct,
            stockCurrent,
            stockHistory,
            stockBranches,
            stockBranchId,
            selectedBranchQuantity,
            stockLoading,
            stockSubmitting,
            stockError,
            stockPagination,
            stockForm,
            openStockModal,
            closeStockModal,
            fetchStockHistory,
            submitStockAdjustment
        };
    }
}
</script>

<style scoped>
.header-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.btn-secondary { padding: 8px 14px; background: #fff; border: 1px solid #cbd5e0; border-radius: 8px; text-decoration: none; color: #2d3748; }
.barcode-text { display: block; font-family: monospace; color: #4a5568; direction: ltr; text-align: right; }
.variant-text { display: block; color: #6b46c1; }

.products-page {
    padding: 20px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}

.page-header h2 {
    color: #2d3748;
    font-size: 1.5rem;
}

.filters {
    display: flex;
    gap: 20px;
    align-items: flex-end;
    margin-bottom: 24px;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.filter-group label {
    color: #4a5568;
    font-weight: 500;
    font-size: 0.875rem;
}

.filter-group select {
    padding: 8px 12px;
    border: 2px solid #e2e8f0;
    border-radius: 6px;
    background: white;
    min-width: 150px;
}

.search-box {
    position: relative;
    min-width: 300px;
}

.search-box input {
    width: 100%;
    padding: 8px 40px 8px 12px;
    border: 2px solid #e2e8f0;
    border-radius: 6px;
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

.products-table {
    width: 100%;
    border-collapse: collapse;
}

.products-table th,
.products-table td {
    padding: 16px;
    text-align: right;
    border-bottom: 1px solid #e2e8f0;
}

.products-table th {
    background: #f7fafc;
    font-weight: 600;
    color: #4a5568;
}

.product-info {
    display: flex;
    align-items: center;
    gap: 8px;
}

.product-name {
    font-weight: 500;
    color: #2d3748;
}

.category-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
    background: #ebf8ff;
    color: #2b6cb0;
}

.form-hint {
    display: block;
    margin-top: 6px;
    color: #a0aec0;
    font-size: 0.8rem;
}

.price {
    color: #3182ce;
    font-weight: 600;
}

.status-badge {
    white-space: nowrap;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
}

.status-approved {
    background: #f0fff4;
    color: #38a169;
}

.stock-badge {
    font-weight: 600;
}

.stock-ok {
    color: #2d3748;
}

.stock-low {
    color: #e53e3e;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}

.checkbox-label input {
    width: auto;
}

.status-pending {
    background: #fef5e7;
    color: #dd6b20;
}

.actions {
    display: flex;
    gap: 8px;
}

.btn-stock {
    border: none;
    padding: 6px 10px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 1rem;
    background: #e6fffa;
}

.btn-stock:hover {
    background: #b2f5ea;
}

.modal-wide {
    max-width: 860px;
    width: 95%;
}

.stock-summary {
    display: flex;
    gap: 24px;
    padding: 0 20px 12px;
    color: #4a5568;
    font-size: 0.95rem;
}

.form-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 12px;
}

.form-row > * {
    min-width: 0;
}

.form-row > .form-group-wide {
    grid-column: 1 / -1;
}

.stock-error {
    color: #c53030;
    font-size: 0.9rem;
    margin: 4px 0 8px;
}

.stock-history {
    margin-top: 16px;
    padding: 0 20px 20px;
}

.stock-history h4 {
    color: #2d3748;
    margin-bottom: 8px;
}

.stock-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
}

.stock-table th,
.stock-table td {
    padding: 6px 8px;
    border-bottom: 1px solid #edf2f7;
    text-align: right;
}

.stock-table th {
    color: #718096;
    font-weight: 600;
}

.change-plus {
    color: #2f855a;
    font-weight: 600;
}

.change-minus {
    color: #c53030;
    font-weight: 600;
}

.btn-edit, .btn-delete {
    border: none;
    padding: 6px 10px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 1rem;
    transition: background 0.3s ease;
}

.btn-edit {
    background: #fef5e7;
}

.btn-edit:hover {
    background: #fed7aa;
}

.btn-delete {
    background: #fed7d7;
}

.btn-delete:hover:not(:disabled) {
    background: #feb2b2;
}

.btn-delete:disabled {
    opacity: 0.5;
    cursor: not-allowed;
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

/* Modal Styles (مشابه کامپوننت قبلی) */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    padding: 20px;
}

.modal {
    background: white;
    border-radius: 12px;
    width: 100%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid #e2e8f0;
}

.modal-header h3 {
    color: #2d3748;
    margin: 0;
}

.close-btn {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #a0aec0;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.close-btn:hover {
    color: #4a5568;
}

.modal-form {
    padding: 24px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 20px;
}

.form-group label {
    color: #4a5568;
    font-weight: 500;
    font-size: 0.875rem;
}

.form-group input,
.form-group select {
    padding: 12px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.3s ease;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #3182ce;
}

.modal-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid #e2e8f0;
}

.btn-cancel {
    background: #f7fafc;
    border: 1px solid #e2e8f0;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    color: #4a5568;
    font-weight: 500;
}

.btn-cancel:hover {
    background: #edf2f7;
}

.btn-primary {
    background: #3182ce;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
    transition: background 0.3s ease;
}

.btn-primary:hover:not(:disabled) {
    background: #2c5aa0;
}

.btn-primary:disabled {
    background: #a0aec0;
    cursor: not-allowed;
}

.image-row {
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
}

.image-preview {
    width: 96px;
    height: 96px;
    border: 1px dashed #cbd5e1;
    border-radius: 10px;
    background: #f8fafc;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    color: #94a3b8;
    font-size: 0.8rem;
    flex-shrink: 0;
}

.image-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.image-actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-error {
    display: block;
    color: #b91c1c;
    margin-top: 4px;
}
</style>