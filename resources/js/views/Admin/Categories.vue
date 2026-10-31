<template>
    <div class="categories-page">
        <div class="page-header">
            <h2><IconFolderOpen class="emo-icon" aria-hidden="true" /> مدیریت دسته‌بندی‌ها</h2>
            <button @click="openAddModal" class="btn-primary">
                <IconPlus class="emo-icon" aria-hidden="true" /> افزودن دسته‌بندی
            </button>
        </div>

        <p class="page-description">
            دسته‌بندی‌های محصولات را برای کسب‌وکار خود تعریف کنید. هر دسته‌بندی آیکون، پیشوند کد SKU و وضعیت فعال/غیرفعال دارد.
        </p>

        <div class="table-container">
            <div class="table-scroll"><table class="categories-table">
                <thead>
                    <tr>
                        <th>آیکون</th>
                        <th>نام دسته‌بندی</th>
                        <th>پیشوند SKU</th>
                        <th>ترتیب نمایش</th>
                        <th>تعداد محصولات</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="category in categories" :key="category.id">
                        <td class="icon-cell" data-label="آیکون">{{ category.icon || '—' }}</td>
                        <td data-label="نام دسته‌بندی">
                            <div class="category-name">{{ category.name }}</div>
                            <div v-if="category.description" class="category-description">
                                {{ category.description }}
                            </div>
                        </td>
                        <td data-label="پیشوند SKU"><code>{{ category.sku_prefix || 'PROD' }}</code></td>
                        <td data-label="ترتیب نمایش">{{ category.sort_order }}</td>
                        <td data-label="تعداد محصولات">{{ category.products_count }}</td>
                        <td data-label="وضعیت">
                            <span :class="['status-badge', category.is_active ? 'status-active' : 'status-inactive']">
                                {{ category.is_active ? 'فعال' : 'غیرفعال' }}
                            </span>
                        </td>
                        <td data-label="عملیات">
                            <div class="actions">
                                <button @click="openEditModal(category)" class="btn-edit" title="ویرایش">
                                    <IconPencil class="emo-icon" aria-hidden="true" />
                                </button>
                                <button
                                    @click="deleteCategory(category)"
                                    class="btn-delete"
                                    title="حذف"
                                    :disabled="category.products_count > 0"
                                >
                                    <IconTrash2 class="emo-icon" aria-hidden="true" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table></div>

            <div v-if="loading" class="loading">
                در حال بارگذاری...
            </div>

            <div v-if="!loading && categories.length === 0" class="empty-state">
                <IconFolderOpen class="emo-icon" aria-hidden="true" /> هنوز دسته‌بندی‌ای تعریف نشده است
            </div>
        </div>

        <!-- Modal افزودن/ویرایش دسته‌بندی -->
        <div v-if="showModal" class="modal-overlay">
            <div class="modal">
                <div class="modal-header">
                    <h3>{{ selectedCategory ? 'ویرایش دسته‌بندی' : 'افزودن دسته‌بندی جدید' }}</h3>
                    <button @click="closeModal" class="close-btn">×</button>
                </div>

                <form @submit.prevent="submitForm" class="modal-form">
                    <div class="form-group">
                        <label>نام دسته‌بندی *</label>
                        <input
                            type="text"
                            v-model="form.name"
                            required
                            maxlength="100"
                            placeholder="مثال: نوشیدنی گرم"
                        >
                        <small v-if="errors.name" class="field-error">{{ errors.name }}</small>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>آیکون (ایموجی)</label>
                            <input type="text" v-model="form.icon" maxlength="16" placeholder="">
                            <small v-if="errors.icon" class="field-error">{{ errors.icon }}</small>
                        </div>

                        <div class="form-group">
                            <label>پیشوند SKU</label>
                            <input
                                type="text"
                                v-model="form.sku_prefix"
                                maxlength="10"
                                placeholder="HOT"
                                style="text-transform: uppercase"
                            >
                            <small class="form-hint">فقط حروف و اعداد انگلیسی. خالی = PROD</small>
                            <small v-if="errors.sku_prefix" class="field-error">{{ errors.sku_prefix }}</small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>ترتیب نمایش</label>
                            <input type="number" v-model.number="form.sort_order" min="0" max="9999">
                            <small v-if="errors.sort_order" class="field-error">{{ errors.sort_order }}</small>
                        </div>

                        <div class="form-group checkbox-group">
                            <label>
                                <input type="checkbox" v-model="form.is_active">
                                دسته‌بندی فعال باشد
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>توضیحات</label>
                        <textarea v-model="form.description" rows="2" maxlength="500"></textarea>
                        <small v-if="errors.description" class="field-error">{{ errors.description }}</small>
                    </div>

                    <div v-if="formError" class="field-error form-error">{{ formError }}</div>

                    <div class="modal-actions">
                        <button type="button" @click="closeModal" class="btn-cancel">انصراف</button>
                        <button type="submit" :disabled="formLoading" class="btn-primary">
                            {{ formLoading ? 'در حال ذخیره...' : 'ذخیره' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import { FolderOpen as IconFolderOpen, Pencil as IconPencil, Plus as IconPlus, Trash2 as IconTrash2 } from 'lucide-vue-next';
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { useNotificationStore } from '../../stores/notifications';

const emptyForm = () => ({
    name: '',
    icon: '',
    sku_prefix: '',
    sort_order: 0,
    is_active: true,
    description: ''
});

export default {
    components: { IconFolderOpen, IconPencil, IconPlus, IconTrash2 },
    name: 'Categories',
    setup() {
        const notificationStore = useNotificationStore();

        const categories = ref([]);
        const loading = ref(false);

        const showModal = ref(false);
        const formLoading = ref(false);
        const selectedCategory = ref(null);
        const form = ref(emptyForm());
        const errors = ref({});
        const formError = ref('');

        const fetchCategories = async () => {
            loading.value = true;
            try {
                const response = await axios.get('/api/admin/product-categories');
                categories.value = response.data.categories;
            } catch (error) {
                console.error('Error fetching categories:', error);
                notificationStore.addNotification({
                    type: 'error',
                    title: 'خطا',
                    message: 'خطا در بارگذاری دسته‌بندی‌ها'
                });
            } finally {
                loading.value = false;
            }
        };

        const openAddModal = () => {
            selectedCategory.value = null;
            form.value = emptyForm();
            errors.value = {};
            formError.value = '';
            showModal.value = true;
        };

        const openEditModal = (category) => {
            selectedCategory.value = category;
            form.value = {
                name: category.name,
                icon: category.icon || '',
                sku_prefix: category.sku_prefix || '',
                sort_order: category.sort_order,
                is_active: category.is_active,
                description: category.description || ''
            };
            errors.value = {};
            formError.value = '';
            showModal.value = true;
        };

        const closeModal = () => {
            showModal.value = false;
            selectedCategory.value = null;
        };

        const submitForm = async () => {
            formLoading.value = true;
            errors.value = {};
            formError.value = '';

            const payload = {
                ...form.value,
                icon: form.value.icon || null,
                sku_prefix: form.value.sku_prefix || null,
                description: form.value.description || null,
                sort_order: form.value.sort_order || 0
            };

            try {
                if (selectedCategory.value) {
                    await axios.put(`/api/admin/product-categories/${selectedCategory.value.id}`, payload);
                } else {
                    await axios.post('/api/admin/product-categories', payload);
                }

                notificationStore.addNotification({
                    type: 'success',
                    title: 'موفق',
                    message: selectedCategory.value ? 'دسته‌بندی بروزرسانی شد' : 'دسته‌بندی ایجاد شد'
                });

                closeModal();
                fetchCategories();
            } catch (error) {
                if (error.response?.status === 422) {
                    const data = error.response.data;
                    if (data.errors) {
                        errors.value = Object.fromEntries(
                            Object.entries(data.errors).map(([field, messages]) => [field, messages[0]])
                        );
                    } else {
                        formError.value = data.message;
                    }
                } else {
                    formError.value = 'خطا در ذخیره دسته‌بندی';
                }
            } finally {
                formLoading.value = false;
            }
        };

        const deleteCategory = async (category) => {
            if (!confirm(`آیا از حذف دسته‌بندی «${category.name}» اطمینان دارید؟`)) {
                return;
            }

            try {
                await axios.delete(`/api/admin/product-categories/${category.id}`);
                notificationStore.addNotification({
                    type: 'success',
                    title: 'موفق',
                    message: 'دسته‌بندی حذف شد'
                });
                fetchCategories();
            } catch (error) {
                notificationStore.addNotification({
                    type: 'error',
                    title: 'خطا',
                    message: error.response?.data?.message || 'خطا در حذف دسته‌بندی'
                });
            }
        };

        onMounted(fetchCategories);

        return {
            categories,
            loading,
            showModal,
            formLoading,
            selectedCategory,
            form,
            errors,
            formError,
            openAddModal,
            openEditModal,
            closeModal,
            submitForm,
            deleteCategory
        };
    }
};
</script>

<style scoped>
.categories-page {
    padding: 20px;
}

.page-description {
    color: #718096;
    margin-bottom: 24px;
    font-size: 0.9rem;
}

.table-container {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.categories-table {
    width: 100%;
    border-collapse: collapse;
}

.categories-table th,
.categories-table td {
    padding: 16px;
    text-align: right;
    border-bottom: 1px solid #e2e8f0;
}

.categories-table th {
    background: #f7fafc;
    font-weight: 600;
    color: #4a5568;
}

.icon-cell {
    font-size: 1.4rem;
    width: 60px;
}

.category-name {
    font-weight: 500;
    color: #2d3748;
}

.category-description {
    color: #a0aec0;
    font-size: 0.8rem;
    margin-top: 4px;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
}

.status-active {
    background: #f0fff4;
    color: #38a169;
}

.status-inactive {
    background: #edf2f7;
    color: #718096;
}

.actions {
    display: flex;
    gap: 8px;
}

.actions button {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 1rem;
}

.actions button:disabled {
    opacity: 0.35;
    cursor: not-allowed;
}

.loading,
.empty-state {
    padding: 32px;
    text-align: center;
    color: #a0aec0;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}

.checkbox-group label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 400;
}

.form-hint {
    color: #a0aec0;
    font-size: 0.75rem;
}

.field-error {
    color: #e53e3e;
    font-size: 0.8rem;
}

.form-error {
    text-align: center;
}
</style>
