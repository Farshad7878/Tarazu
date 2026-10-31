<template>
    <div class="users-page transfers-page">
        <div class="page-header">
            <h2>🔁 انتقال موجودی بین شعبه‌ها</h2>
            <button @click="openCreate" class="btn-primary" :disabled="activeBranches.length < 2">➕ انتقال جدید</button>
        </div>

        <p class="page-hint">
            با ثبت انتقال، کالا فوراً از موجودی شعبه‌ی مبدا کم می‌شود و «در راه» است. پس از رسیدن کالا به شعبه‌ی مقصد،
            با «دریافت» به موجودی آن اضافه می‌شود. تا قبل از دریافت می‌توان انتقال را لغو کرد تا کالا به مبدا برگردد.
        </p>

        <div class="filters">
            <select v-model="filters.status" @change="fetchTransfers(1)">
                <option value="">همه‌ی وضعیت‌ها</option>
                <option v-for="(label, key) in statuses" :key="key" :value="key">{{ label }}</option>
            </select>
            <select v-model="filters.branch_id" @change="fetchTransfers(1)">
                <option value="">همه‌ی شعبه‌ها</option>
                <option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
            </select>
        </div>

        <div class="table-container">
            <div class="table-scroll"><table class="users-table">
                <thead>
                    <tr>
                        <th>شماره</th>
                        <th>مسیر</th>
                        <th>وضعیت</th>
                        <th>اقلام</th>
                        <th>ثبت‌کننده</th>
                        <th>تاریخ</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="t in transfers" :key="t.id">
                        <td><code>{{ t.transfer_number }}</code></td>
                        <td>از {{ t.from_branch_name }} به {{ t.to_branch_name }}</td>
                        <td>
                            <span :class="['status-badge', statusClass(t.status)]">{{ t.status_label }}</span>
                        </td>
                        <td>{{ t.items_count }} قلم / {{ formatNumber(t.total_quantity) }} عدد</td>
                        <td>{{ t.created_by || '—' }}</td>
                        <td>{{ t.created_at }}</td>
                        <td class="actions">
                            <button @click="openDetail(t)" class="btn-edit">جزئیات</button>
                            <button v-if="t.status === 'in_transit'" @click="receive(t)" class="btn-edit">دریافت</button>
                            <button v-if="t.status === 'in_transit'" @click="cancel(t)" class="btn-delete">لغو</button>
                        </td>
                    </tr>
                </tbody>
            </table></div>

            <div v-if="!loading && transfers.length === 0" class="empty-state">انتقالی ثبت نشده است.</div>
            <div v-if="loading" class="empty-state">در حال بارگذاری...</div>

            <div v-if="pagination.last_page > 1" class="pagination">
                <button @click="fetchTransfers(pagination.current_page - 1)" :disabled="pagination.current_page === 1" class="pagination-btn">قبلی</button>
                <span class="pagination-info">صفحه {{ pagination.current_page }} از {{ pagination.last_page }}</span>
                <button @click="fetchTransfers(pagination.current_page + 1)" :disabled="pagination.current_page === pagination.last_page" class="pagination-btn">بعدی</button>
            </div>
        </div>

        <!-- ایجاد انتقال -->
        <div v-if="showCreate" class="modal-overlay" @click.self="closeCreate">
            <div class="modal">
                <div class="modal-header">
                    <h3>انتقال موجودی جدید</h3>
                    <button @click="closeCreate" class="close-btn">×</button>
                </div>

                <form @submit.prevent="submitTransfer" class="modal-form">
                    <div class="form-group">
                        <label>شعبه‌ی مبدا *</label>
                        <select v-model.number="createForm.from_branch_id" required @change="onFromBranchChange">
                            <option :value="null" disabled>انتخاب کنید</option>
                            <option v-for="branch in activeBranches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>شعبه‌ی مقصد *</label>
                        <select v-model.number="createForm.to_branch_id" required>
                            <option :value="null" disabled>انتخاب کنید</option>
                            <option v-for="branch in destinationBranches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>کالاها *</label>
                        <div v-if="!createForm.from_branch_id" class="form-hint">ابتدا شعبه‌ی مبدا را انتخاب کنید.</div>
                        <div v-else-if="sourceProducts.length === 0" class="form-hint">در این شعبه کالایی با موجودی وجود ندارد.</div>
                        <div v-for="(row, index) in createForm.items" :key="index" class="item-row">
                            <select v-model.number="row.product_id" required>
                                <option :value="null" disabled>انتخاب کالا</option>
                                <option v-for="p in sourceProducts" :key="p.id" :value="p.id">
                                    {{ p.name }} (موجودی مبدا: {{ formatNumber(p.quantity) }})
                                </option>
                            </select>
                            <input
                                type="number"
                                min="1"
                                step="1"
                                v-model.number="row.quantity"
                                :max="maxFor(row.product_id)"
                                required
                                class="qty-input"
                            >
                            <button type="button" @click="removeRow(index)" class="btn-delete" :disabled="createForm.items.length === 1">×</button>
                        </div>
                        <button type="button" @click="addRow" class="btn-cancel" :disabled="!createForm.from_branch_id || sourceProducts.length === 0">➕ افزودن کالا</button>
                    </div>

                    <div class="form-group">
                        <label>یادداشت</label>
                        <input type="text" v-model="createForm.notes" maxlength="500">
                    </div>

                    <div v-if="createError" class="form-error">{{ createError }}</div>

                    <div class="modal-actions">
                        <button type="button" @click="closeCreate" class="btn-cancel">انصراف</button>
                        <button type="submit" :disabled="saving" class="btn-primary">
                            {{ saving ? 'در حال ثبت...' : 'ثبت و کسر از مبدا' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- جزئیات -->
        <div v-if="detail" class="modal-overlay" @click.self="closeDetail">
            <div class="modal">
                <div class="modal-header">
                    <h3>انتقال {{ detail.transfer_number }}</h3>
                    <button @click="closeDetail" class="close-btn">×</button>
                </div>
                <div class="modal-form">
                    <p><strong>مسیر:</strong> از {{ detail.from_branch_name }} به {{ detail.to_branch_name }}</p>
                    <p><strong>وضعیت:</strong> <span :class="['status-badge', statusClass(detail.status)]">{{ detail.status_label }}</span></p>
                    <p><strong>ثبت:</strong> {{ detail.created_by || '—' }} — {{ detail.created_at }}</p>
                    <p v-if="detail.received_at"><strong>دریافت:</strong> {{ detail.received_by || '—' }} — {{ detail.received_at }}</p>
                    <p v-if="detail.cancelled_at"><strong>لغو:</strong> {{ detail.cancelled_by || '—' }} — {{ detail.cancelled_at }}</p>
                    <p v-if="detail.notes"><strong>یادداشت:</strong> {{ detail.notes }}</p>

                    <div class="table-scroll"><table class="users-table">
                        <thead>
                            <tr><th>کالا</th><th>تعداد</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in detail.items" :key="item.id">
                                <td>{{ item.product_name }}</td>
                                <td>{{ formatNumber(item.quantity) }}</td>
                            </tr>
                        </tbody>
                    </table></div>

                    <div class="modal-actions">
                        <button v-if="detail.status === 'in_transit'" @click="cancel(detail)" class="btn-delete">لغو انتقال</button>
                        <button v-if="detail.status === 'in_transit'" @click="receive(detail)" class="btn-primary">دریافت در شعبه‌ی مقصد</button>
                        <button @click="closeDetail" class="btn-cancel">بستن</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { useNotificationStore } from '../../stores/notifications';

const emptyCreate = () => ({
    from_branch_id: null,
    to_branch_id: null,
    notes: '',
    items: [{ product_id: null, quantity: 1 }],
});

export default {
    name: 'AdminStockTransfers',
    setup() {
        const notificationStore = useNotificationStore();

        const transfers = ref([]);
        const branches = ref([]);
        const statuses = ref({});
        const loading = ref(false);
        const saving = ref(false);
        const filters = ref({ status: '', branch_id: '' });
        const pagination = ref({ current_page: 1, last_page: 1, total: 0 });

        const showCreate = ref(false);
        const createForm = ref(emptyCreate());
        const createError = ref('');
        const sourceProducts = ref([]);

        const detail = ref(null);

        const formatNumber = (n) => new Intl.NumberFormat('fa-IR').format(Number(n) || 0);
        const activeBranches = computed(() => branches.value.filter((b) => b.is_active));
        const destinationBranches = computed(() => activeBranches.value.filter((b) => b.id !== createForm.value.from_branch_id));

        const statusClass = (status) => ({
            in_transit: 'status-inactive',
            received: 'status-active',
            cancelled: 'role-badge',
        }[status] || 'role-badge');

        const notifyError = (error, fallback) => {
            notificationStore.addNotification({
                type: 'error',
                title: 'خطا',
                message: error.response?.data?.message || error.response?.data?.errors?.items?.[0] || fallback,
            });
        };

        const fetchBranches = async () => {
            const response = await axios.get('/api/admin/branches');
            branches.value = response.data.branches;
        };

        const fetchTransfers = async (page = 1) => {
            loading.value = true;
            try {
                const params = { page };
                if (filters.value.status) params.status = filters.value.status;
                if (filters.value.branch_id) params.branch_id = filters.value.branch_id;
                const response = await axios.get('/api/admin/stock-transfers', { params });
                transfers.value = response.data.transfers;
                pagination.value = response.data.pagination;
                statuses.value = response.data.statuses;
            } catch {
                notificationStore.addNotification({ type: 'error', title: 'خطا', message: 'خطا در دریافت لیست انتقال‌ها' });
            } finally {
                loading.value = false;
            }
        };

        const loadSourceProducts = async () => {
            sourceProducts.value = [];
            if (!createForm.value.from_branch_id) return;
            const response = await axios.get(`/api/admin/branches/${createForm.value.from_branch_id}/stock`);
            sourceProducts.value = response.data.products;
        };

        const onFromBranchChange = async () => {
            if (createForm.value.to_branch_id === createForm.value.from_branch_id) {
                createForm.value.to_branch_id = null;
            }
            createForm.value.items = [{ product_id: null, quantity: 1 }];
            await loadSourceProducts();
        };

        const maxFor = (productId) => sourceProducts.value.find((p) => p.id === productId)?.quantity || undefined;

        const addRow = () => createForm.value.items.push({ product_id: null, quantity: 1 });
        const removeRow = (index) => createForm.value.items.splice(index, 1);

        const openCreate = async () => {
            createForm.value = emptyCreate();
            createError.value = '';
            sourceProducts.value = [];
            showCreate.value = true;
        };

        const closeCreate = () => {
            showCreate.value = false;
        };

        const submitTransfer = async () => {
            createError.value = '';

            const chosen = createForm.value.items.map((row) => Number(row.product_id));
            if (new Set(chosen).size !== chosen.length) {
                createError.value = 'هر کالا را فقط یک بار در انتقال ثبت کنید.';
                return;
            }
            for (const row of createForm.value.items) {
                const available = maxFor(row.product_id);
                if (!row.product_id || !Number.isInteger(row.quantity) || row.quantity < 1) {
                    createError.value = 'کالا و تعداد معتبر وارد کنید.';
                    return;
                }
                if (available && row.quantity > available) {
                    createError.value = 'تعداد بیشتر از موجودی شعبه‌ی مبدا است.';
                    return;
                }
            }

            saving.value = true;
            try {
                const payload = {
                    from_branch_id: createForm.value.from_branch_id,
                    to_branch_id: createForm.value.to_branch_id,
                    notes: createForm.value.notes || null,
                    items: createForm.value.items.map((row) => ({ product_id: row.product_id, quantity: row.quantity })),
                };
                const response = await axios.post('/api/admin/stock-transfers', payload);
                notificationStore.addNotification({ type: 'success', title: 'ثبت شد', message: response.data.message });
                closeCreate();
                fetchTransfers(1);
            } catch (error) {
                createError.value = error.response?.data?.message || error.response?.data?.errors?.items?.[0] || 'خطا در ثبت انتقال';
            } finally {
                saving.value = false;
            }
        };

        const openDetail = async (transfer) => {
            try {
                const response = await axios.get(`/api/admin/stock-transfers/${transfer.id}`);
                detail.value = response.data.transfer;
            } catch {
                notifyError({}, 'خطا در دریافت جزئیات انتقال');
            }
        };

        const closeDetail = () => {
            detail.value = null;
        };

        const refreshAfterAction = async (updated) => {
            if (detail.value && detail.value.id === updated.id) detail.value = updated;
            await fetchTransfers(pagination.value.current_page);
        };

        const receive = async (transfer) => {
            if (!confirm(`کالاهای انتقال ${transfer.transfer_number} دریافت شده‌اند؟`)) return;
            try {
                const response = await axios.post(`/api/admin/stock-transfers/${transfer.id}/receive`);
                notificationStore.addNotification({ type: 'success', title: 'دریافت شد', message: response.data.message });
                await refreshAfterAction(response.data.transfer);
            } catch (error) {
                notifyError(error, 'خطا در دریافت انتقال');
            }
        };

        const cancel = async (transfer) => {
            if (!confirm(`انتقال ${transfer.transfer_number} لغو شود؟ کالاها به شعبه‌ی مبدا برمی‌گردند.`)) return;
            try {
                const response = await axios.post(`/api/admin/stock-transfers/${transfer.id}/cancel`);
                notificationStore.addNotification({ type: 'success', title: 'لغو شد', message: response.data.message });
                await refreshAfterAction(response.data.transfer);
            } catch (error) {
                notifyError(error, 'خطا در لغو انتقال');
            }
        };

        onMounted(async () => {
            await fetchBranches();
            await fetchTransfers(1);
        });

        return {
            transfers,
            branches,
            statuses,
            loading,
            saving,
            filters,
            pagination,
            showCreate,
            createForm,
            createError,
            sourceProducts,
            detail,
            formatNumber,
            activeBranches,
            destinationBranches,
            statusClass,
            fetchTransfers,
            onFromBranchChange,
            maxFor,
            addRow,
            removeRow,
            openCreate,
            closeCreate,
            submitTransfer,
            openDetail,
            closeDetail,
            receive,
            cancel,
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

.empty-state {
    padding: 24px;
    text-align: center;
    color: #718096;
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

.page-hint { color: #4a5568; font-size: 13px; margin: 0 0 16px; line-height: 1.8; }
.transfers-page code { background: #edf2f7; padding: 2px 6px; border-radius: 4px; }
.filters { display: flex; gap: 10px; margin-bottom: 14px; flex-wrap: wrap; }
.filters select { padding: 8px 10px; border: 1px solid #cbd5e0; border-radius: 6px; min-width: 180px; }
.item-row { display: flex; gap: 8px; align-items: center; margin-bottom: 8px; }
.item-row select { flex: 1; min-width: 0; padding: 8px; border: 1px solid #cbd5e0; border-radius: 6px; }
.qty-input { width: 90px; padding: 8px; border: 1px solid #cbd5e0; border-radius: 6px; }
</style>