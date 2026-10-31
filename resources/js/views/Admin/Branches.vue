<template>
    <div class="users-page branches-page">
        <div class="page-header">
            <h2>🏬 شعبه‌ها</h2>
            <button @click="openCreate" class="btn-primary">➕ افزودن شعبه</button>
        </div>

        <p class="page-hint">
            هر شعبه موجودی جدا دارد. فروش و برگشت کالا از شعبه‌ی کاربر پذیرش کم/زیاد می‌شود.
            برای جابه‌جایی کالا بین شعبه‌ها از بخش «انتقال موجودی» استفاده کنید. شعبه‌ی اصلی قابل غیرفعال‌سازی نیست.
        </p>

        <div class="table-container">
            <table class="users-table">
                <thead>
                    <tr>
                        <th>نام شعبه</th>
                        <th>کد</th>
                        <th>نشانی</th>
                        <th>تلفن</th>
                        <th>کاربران</th>
                        <th>مجموع موجودی</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="branch in branches" :key="branch.id">
                        <td>
                            {{ branch.name }}
                            <span v-if="branch.is_main" class="role-badge">اصلی</span>
                        </td>
                        <td><code>{{ branch.code }}</code></td>
                        <td>{{ branch.address || '—' }}</td>
                        <td>{{ branch.phone || '—' }}</td>
                        <td>{{ branch.users_count }}</td>
                        <td>{{ formatNumber(branch.stock_units) }}</td>
                        <td>
                            <span :class="['status-badge', branch.is_active ? 'status-active' : 'status-inactive']">
                                {{ branch.is_active ? 'فعال' : 'غیرفعال' }}
                            </span>
                        </td>
                        <td class="actions">
                            <button @click="openEdit(branch)" class="btn-edit">ویرایش</button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="!loading && branches.length === 0" class="empty-state">هنوز شعبه‌ای ثبت نشده است.</div>
            <div v-if="loading" class="empty-state">در حال بارگذاری...</div>
        </div>

        <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
            <div class="modal">
                <div class="modal-header">
                    <h3>{{ editing ? 'ویرایش شعبه' : 'افزودن شعبه' }}</h3>
                    <button @click="closeModal" class="close-btn">×</button>
                </div>

                <form @submit.prevent="submitForm" class="modal-form">
                    <div class="form-group">
                        <label>نام شعبه *</label>
                        <input type="text" v-model="form.name" required maxlength="120">
                    </div>

                    <div class="form-group">
                        <label>کد شعبه * (حروف انگلیسی، عدد، - و _)</label>
                        <input type="text" v-model="form.code" required maxlength="30" dir="ltr" style="text-transform: uppercase">
                    </div>

                    <div class="form-group">
                        <label>نشانی</label>
                        <input type="text" v-model="form.address" maxlength="255">
                    </div>

                    <div class="form-group">
                        <label>تلفن</label>
                        <input type="text" v-model="form.phone" maxlength="30" dir="ltr">
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" v-model="form.is_active" :disabled="editing && selected && selected.is_main">
                            شعبه فعال باشد (فروش و انتقال)
                        </label>
                        <small v-if="editing && selected && selected.is_main" class="form-hint">شعبه‌ی اصلی همیشه فعال است.</small>
                    </div>

                    <div v-if="errorMessage" class="form-error">{{ errorMessage }}</div>

                    <div class="modal-actions">
                        <button type="button" @click="closeModal" class="btn-cancel">انصراف</button>
                        <button type="submit" :disabled="saving" class="btn-primary">
                            {{ saving ? 'در حال ذخیره...' : 'ذخیره' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { useNotificationStore } from '../../stores/notifications';

const emptyForm = () => ({
    name: '',
    code: '',
    address: '',
    phone: '',
    is_active: true,
});

export default {
    name: 'AdminBranches',
    setup() {
        const notificationStore = useNotificationStore();

        const branches = ref([]);
        const loading = ref(false);
        const saving = ref(false);
        const showModal = ref(false);
        const editing = ref(false);
        const selected = ref(null);
        const form = ref(emptyForm());
        const errorMessage = ref('');

        const formatNumber = (n) => new Intl.NumberFormat('fa-IR').format(Number(n) || 0);

        const extractError = (error) => {
            const errors = error.response?.data?.errors;
            if (errors) {
                return Object.values(errors).flat().join(' ');
            }
            return error.response?.data?.message || 'خطا در ذخیره‌سازی';
        };

        const fetchBranches = async () => {
            loading.value = true;
            try {
                const response = await axios.get('/api/admin/branches');
                branches.value = response.data.branches;
            } catch {
                notificationStore.addNotification({ type: 'error', title: 'خطا', message: 'خطا در دریافت لیست شعبه‌ها' });
            } finally {
                loading.value = false;
            }
        };

        const openCreate = () => {
            editing.value = false;
            selected.value = null;
            form.value = emptyForm();
            errorMessage.value = '';
            showModal.value = true;
        };

        const openEdit = (branch) => {
            editing.value = true;
            selected.value = branch;
            form.value = {
                name: branch.name,
                code: branch.code,
                address: branch.address || '',
                phone: branch.phone || '',
                is_active: !!branch.is_active,
            };
            errorMessage.value = '';
            showModal.value = true;
        };

        const closeModal = () => {
            showModal.value = false;
        };

        const submitForm = async () => {
            saving.value = true;
            errorMessage.value = '';
            const payload = { ...form.value, address: form.value.address || null, phone: form.value.phone || null };

            try {
                if (editing.value) {
                    await axios.put(`/api/admin/branches/${selected.value.id}`, payload);
                } else {
                    await axios.post('/api/admin/branches', payload);
                }
                notificationStore.addNotification({ type: 'success', title: 'ذخیره شد', message: 'اطلاعات شعبه ذخیره شد' });
                closeModal();
                fetchBranches();
            } catch (error) {
                errorMessage.value = extractError(error);
            } finally {
                saving.value = false;
            }
        };

        onMounted(fetchBranches);

        return {
            branches,
            loading,
            saving,
            showModal,
            editing,
            selected,
            form,
            errorMessage,
            formatNumber,
            openCreate,
            openEdit,
            closeModal,
            submitForm,
        };
    },
};
</script>

<style scoped>
.users-page {
    padding: 20px;
    max-width: 1200px;
    margin: 0 auto;
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
    margin: 0;
}

.table-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    overflow-x: auto;
}

.users-table {
    width: 100%;
    border-collapse: collapse;
}

.users-table th,
.users-table td {
    padding: 12px 16px;
    text-align: right;
    border-bottom: 1px solid #edf2f7;
}

.users-table th {
    background: #f7fafc;
    color: #4a5568;
    font-weight: 600;
}

.role-badge {
    background: #ebf8ff;
    color: #2b6cb0;
    padding: 2px 10px;
    border-radius: 999px;
    font-size: 0.85rem;
}

.status-badge {
    padding: 2px 10px;
    border-radius: 999px;
    font-size: 0.85rem;
}

.status-active {
    background: #f0fff4;
    color: #38a169;
}

.status-inactive {
    background: #fed7d7;
    color: #c53030;
}

.actions {
    display: flex;
    gap: 8px;
}

.btn-primary,
.btn-edit,
.btn-delete,
.btn-cancel {
    padding: 8px 14px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    font-size: 0.9rem;
}

.btn-primary {
    background: #3182ce;
    color: white;
}

.btn-edit {
    background: #edf2f7;
    color: #2d3748;
}

.btn-delete {
    background: #fed7d7;
    color: #c53030;
}

.btn-delete:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.btn-cancel {
    background: #edf2f7;
    color: #2d3748;
}

.empty-state {
    padding: 24px;
    text-align: center;
    color: #718096;
}

.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 50;
}

.modal {
    background: white;
    border-radius: 12px;
    width: 100%;
    max-width: 460px;
    padding: 24px;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.modal-header h3 {
    margin: 0;
    color: #2d3748;
}

.close-btn {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #718096;
}

.modal-form {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.form-group label {
    color: #4a5568;
    font-size: 0.9rem;
}

.form-group input,
.form-group select {
    padding: 8px 12px;
    border: 2px solid #e2e8f0;
    border-radius: 6px;
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

.form-error {
    color: #c53030;
    font-size: 0.9rem;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.page-hint { color: #4a5568; font-size: 13px; margin: 0 0 16px; line-height: 1.8; }
.branches-page code { background: #edf2f7; padding: 2px 6px; border-radius: 4px; }

</style>