<template>
    <div class="users-page">
        <div class="page-header">
            <h2>👤 مدیریت کاربران</h2>
            <button @click="openCreate" class="btn-primary">
                ➕ افزودن کاربر
            </button>
        </div>

        <div class="table-container">
            <table class="users-table">
                <thead>
                    <tr>
                        <th>نام</th>
                        <th>ایمیل</th>
                        <th>نقش</th>
                        <th>شعبه</th>
                        <th>وضعیت</th>
                        <th>تاریخ ایجاد</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="user in users" :key="user.id">
                        <td>{{ user.name }}</td>
                        <td>{{ user.email }}</td>
                        <td>
                            <span class="role-badge">{{ roleLabel(user.role) }}</span>
                        </td>
                        <td>{{ user.role === 'reception' ? (user.branch_name || 'بدون شعبه') : '—' }}</td>
                        <td>
                            <span :class="['status-badge', user.is_active ? 'status-active' : 'status-inactive']">
                                {{ user.is_active ? 'فعال' : 'غیرفعال' }}
                            </span>
                        </td>
                        <td>{{ user.created_at }}</td>
                        <td class="actions">
                            <button @click="openEdit(user)" class="btn-edit">ویرایش</button>
                            <button
                                @click="deleteUser(user)"
                                class="btn-delete"
                                :disabled="isSelf(user)"
                                :title="isSelf(user) ? 'نمی‌توانید حساب خودتان را حذف کنید' : ''"
                            >
                                حذف
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="!loading && users.length === 0" class="empty-state">
                هیچ کاربری ثبت نشده است.
            </div>
            <div v-if="loading" class="empty-state">در حال بارگذاری...</div>
        </div>

        <!-- مودال افزودن / ویرایش -->
        <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
            <div class="modal">
                <div class="modal-header">
                    <h3>{{ editing ? 'ویرایش کاربر' : 'افزودن کاربر' }}</h3>
                    <button @click="closeModal" class="close-btn">×</button>
                </div>

                <form @submit.prevent="submitForm" class="modal-form">
                    <div class="form-group">
                        <label>نام *</label>
                        <input type="text" v-model="form.name" required maxlength="255">
                    </div>

                    <div class="form-group">
                        <label>ایمیل *</label>
                        <input type="email" v-model="form.email" required maxlength="255">
                    </div>

                    <div class="form-group">
                        <label>نقش *</label>
                        <select v-model="form.role" required>
                            <option value="reception">ریسپشن</option>
                            <option value="admin">ادمین</option>
                        </select>
                    </div>

                    <div v-if="form.role === 'reception'" class="form-group">
                        <label>شعبه * (فروش و موجودی این کاربر از همین شعبه است)</label>
                        <select v-model.number="form.branch_id" required>
                            <option :value="null" disabled>انتخاب شعبه</option>
                            <option
                                v-for="branch in branches"
                                :key="branch.id"
                                :value="branch.id"
                                :disabled="!branch.is_active && branch.id !== form.branch_id"
                            >
                                {{ branch.name }}{{ branch.is_active ? '' : ' (غیرفعال)' }}
                            </option>
                        </select>
                    </div>

                    <div v-if="editing" class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" v-model="form.is_active" :disabled="isSelf(selectedUser)">
                            کاربر فعال باشد (ورود به سیستم)
                        </label>
                    </div>

                    <div class="form-group">
                        <label>{{ editing ? 'رمز عبور جدید (اختیاری)' : 'رمز عبور *' }}</label>
                        <input type="password" v-model="form.password" :required="!editing" autocomplete="new-password">
                    </div>

                    <div class="form-group">
                        <label>تکرار رمز عبور{{ editing ? '' : ' *' }}</label>
                        <input type="password" v-model="form.password_confirmation" :required="!editing || !!form.password" autocomplete="new-password">
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
import { useAuthStore } from '../../stores/auth';
import { useNotificationStore } from '../../stores/notifications';

const emptyForm = () => ({
    name: '',
    email: '',
    role: 'reception',
    branch_id: null,
    is_active: true,
    password: '',
    password_confirmation: '',
});

export default {
    name: 'AdminUsers',
    setup() {
        const authStore = useAuthStore();
        const notificationStore = useNotificationStore();

        const users = ref([]);
        const branches = ref([]);
        const loading = ref(false);
        const saving = ref(false);
        const showModal = ref(false);
        const editing = ref(false);
        const selectedUser = ref(null);
        const form = ref(emptyForm());
        const errorMessage = ref('');

        const roleLabel = (role) => (role === 'admin' ? 'ادمین' : 'ریسپشن');
        const isSelf = (user) => !!user && user.id === authStore.user?.id;

        const fetchUsers = async () => {
            loading.value = true;
            try {
                const [usersResponse, branchesResponse] = await Promise.all([
                    axios.get('/api/admin/users'),
                    axios.get('/api/admin/branches'),
                ]);
                users.value = usersResponse.data.users;
                branches.value = branchesResponse.data.branches;
            } catch {
                notificationStore.addNotification({ type: 'error', title: 'خطا', message: 'خطا در دریافت لیست کاربران' });
            } finally {
                loading.value = false;
            }
        };

        const openCreate = () => {
            editing.value = false;
            selectedUser.value = null;
            form.value = emptyForm();
            errorMessage.value = '';
            showModal.value = true;
        };

        const openEdit = (user) => {
            editing.value = true;
            selectedUser.value = user;
            form.value = {
                ...emptyForm(),
                name: user.name,
                email: user.email,
                role: user.role,
                branch_id: user.branch_id ?? null,
                is_active: !!user.is_active,
            };
            errorMessage.value = '';
            showModal.value = true;
        };

        const closeModal = () => {
            showModal.value = false;
        };

        // پیام خطای اعتبارسنجی را از پاسخ Laravel بیرون می‌کشیم
        const extractError = (error) => {
            const errors = error.response?.data?.errors;
            if (errors) {
                return Object.values(errors).flat().join(' ');
            }
            return error.response?.data?.message || error.response?.data?.error || 'خطا در ذخیره‌سازی';
        };

        const submitForm = async () => {
            saving.value = true;
            errorMessage.value = '';

            const payload = {
                name: form.value.name,
                email: form.value.email,
                role: form.value.role,
                branch_id: form.value.role === 'reception' ? form.value.branch_id : null,
            };

            if (editing.value) {
                payload.is_active = form.value.is_active;
                if (form.value.password) {
                    payload.password = form.value.password;
                    payload.password_confirmation = form.value.password_confirmation;
                }
            } else {
                payload.password = form.value.password;
                payload.password_confirmation = form.value.password_confirmation;
            }

            try {
                if (editing.value) {
                    await axios.put(`/api/admin/users/${selectedUser.value.id}`, payload);
                } else {
                    await axios.post('/api/admin/users', payload);
                }

                notificationStore.addNotification({
                    type: 'success',
                    title: 'ذخیره شد',
                    message: editing.value ? 'کاربر بروزرسانی شد' : 'کاربر ایجاد شد',
                });
                closeModal();
                fetchUsers();
            } catch (error) {
                errorMessage.value = extractError(error);
            } finally {
                saving.value = false;
            }
        };

        const deleteUser = async (user) => {
            if (isSelf(user)) return;
            if (!confirm(`آیا از حذف کاربر «${user.name}» اطمینان دارید؟`)) return;

            try {
                await axios.delete(`/api/admin/users/${user.id}`);
                notificationStore.addNotification({ type: 'success', title: 'حذف شد', message: 'کاربر حذف شد' });
                fetchUsers();
            } catch (error) {
                notificationStore.addNotification({
                    type: 'error',
                    title: 'خطا',
                    message: error.response?.data?.error || error.response?.data?.message || 'خطا در حذف کاربر',
                });
            }
        };

        onMounted(fetchUsers);

        return {
            users,
            branches,
            loading,
            saving,
            showModal,
            editing,
            selectedUser,
            form,
            errorMessage,
            roleLabel,
            isSelf,
            openCreate,
            openEdit,
            closeModal,
            submitForm,
            deleteUser,
        };
    }
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
</style>
