<template>
    <div class="po-page">
        <div class="page-header">
            <h2>🚚 خرید و تأمین‌کنندگان</h2>
            <div class="tabs">
                <button :class="{ active: tab === 'orders' }" @click="tab = 'orders'">سفارش‌های خرید</button>
                <button :class="{ active: tab === 'suppliers' }" @click="tab = 'suppliers'">تأمین‌کنندگان</button>
            </div>
        </div>

        <div v-if="message" :class="['flash', messageType]">{{ message }}</div>

        <!-- سفارش‌های خرید -->
        <template v-if="tab === 'orders'">
            <div class="toolbar">
                <select v-model="statusFilter" @change="fetchOrders(1)">
                    <option value="">همه وضعیت‌ها</option>
                    <option v-for="(label, key) in statusLabels" :key="key" :value="key">{{ label }}</option>
                </select>
                <button class="btn-primary" @click="openCreate">➕ سفارش خرید جدید</button>
            </div>

            <div class="table-container">
                <table v-if="orders.length" class="data-table">
                    <thead>
                        <tr>
                            <th>شماره</th>
                            <th>تأمین‌کننده</th>
                            <th>وضعیت</th>
                            <th>مبلغ کل</th>
                            <th>ثبت</th>
                            <th>دریافت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="po in orders" :key="po.id">
                            <td>{{ po.po_number }}</td>
                            <td>{{ po.supplier_name }}<small v-if="po.branch_name" class="form-hint"> — {{ po.branch_name }}</small></td>
                            <td><span :class="['badge', `badge-${po.status}`]">{{ po.status_label }}</span></td>
                            <td>{{ money(po.total_cost) }}</td>
                            <td>{{ po.ordered_at }}</td>
                            <td>{{ po.received_at || '—' }}</td>
                            <td class="actions">
                                <button class="btn-small" @click="openDetail(po.id)">
                                    {{ ['ordered', 'partially_received'].includes(po.status) ? 'دریافت کالا' : 'مشاهده' }}
                                </button>
                                <button
                                    v-if="po.status === 'ordered'"
                                    class="btn-small btn-danger"
                                    @click="cancelOrder(po)"
                                >
                                    لغو
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-else-if="!loading" class="empty">سفارش خریدی ثبت نشده است.</div>
                <div v-if="loading" class="empty">در حال بارگذاری...</div>
            </div>

            <div v-if="pagination.last_page > 1" class="pagination">
                <button :disabled="pagination.current_page <= 1" @click="fetchOrders(pagination.current_page - 1)">قبلی</button>
                <span>صفحه {{ pagination.current_page }} از {{ pagination.last_page }}</span>
                <button :disabled="pagination.current_page >= pagination.last_page" @click="fetchOrders(pagination.current_page + 1)">بعدی</button>
            </div>
        </template>

        <!-- تأمین‌کنندگان -->
        <template v-else>
            <form class="card-form" @submit.prevent="saveSupplier">
                <h3>{{ supplierForm.id ? 'ویرایش تأمین‌کننده' : 'تأمین‌کننده‌ی جدید' }}</h3>
                <div class="form-grid">
                    <input v-model="supplierForm.name" placeholder="نام تأمین‌کننده *" required maxlength="150">
                    <input v-model="supplierForm.phone" placeholder="تلفن" maxlength="30">
                    <input v-model="supplierForm.email" type="email" placeholder="ایمیل" maxlength="150">
                    <input v-model="supplierForm.address" placeholder="نشانی" maxlength="1000">
                    <input v-model="supplierForm.notes" placeholder="یادداشت" maxlength="1000">
                </div>
                <div class="form-actions">
                    <button v-if="supplierForm.id" type="button" class="btn-cancel" @click="resetSupplierForm">انصراف</button>
                    <button type="submit" class="btn-primary" :disabled="savingSupplier">{{ supplierForm.id ? 'ذخیره' : 'افزودن' }}</button>
                </div>
            </form>

            <div class="table-container">
                <table v-if="suppliers.length" class="data-table">
                    <thead>
                        <tr>
                            <th>نام</th>
                            <th>تلفن</th>
                            <th>ایمیل</th>
                            <th>تعداد سفارش</th>
                            <th>وضعیت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="s in suppliers" :key="s.id">
                            <td>{{ s.name }}</td>
                            <td>{{ s.phone || '—' }}</td>
                            <td>{{ s.email || '—' }}</td>
                            <td>{{ s.purchase_orders_count }}</td>
                            <td>{{ s.is_active ? 'فعال' : 'غیرفعال' }}</td>
                            <td class="actions">
                                <button class="btn-small" @click="editSupplier(s)">ویرایش</button>
                                <button class="btn-small btn-danger" @click="deleteSupplier(s)">حذف</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-else class="empty">تأمین‌کننده‌ای ثبت نشده است.</div>
            </div>
        </template>

        <!-- ساخت سفارش خرید -->
        <div v-if="showCreate" class="modal-overlay" @click.self="showCreate = false">
            <div class="modal">
                <div class="modal-header">
                    <h3>سفارش خرید جدید</h3>
                    <button class="close-btn" @click="showCreate = false">×</button>
                </div>
                <div class="modal-body">
                    <label>تأمین‌کننده *</label>
                    <select v-model="createForm.supplier_id">
                        <option value="">انتخاب کنید</option>
                        <option v-for="s in suppliers.filter((x) => x.is_active)" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>

                    <label>شعبه‌ی دریافت کالا</label>
                    <select v-model="createForm.branch_id">
                        <option value="">شعبه‌ی اصلی (پیش‌فرض)</option>
                        <option v-for="b in branches.filter((x) => x.is_active)" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>

                    <label>افزودن کالا (جستجو با نام یا بارکد)</label>
                    <div class="search-row">
                        <input v-model="productQuery" placeholder="نام یا بارکد کالا" @keyup.enter="searchProducts">
                        <button class="btn-small" @click="searchProducts">جستجو</button>
                    </div>
                    <div v-if="productResults.length" class="results">
                        <button v-for="p in productResults" :key="p.id" class="result-item" @click="addLine(p)">
                            {{ p.name }} <small>{{ p.barcode || '' }}</small>
                        </button>
                    </div>

                    <table v-if="createForm.items.length" class="data-table compact">
                        <thead>
                            <tr><th>کالا</th><th>تعداد</th><th>بهای واحد (تومان)</th><th>جمع</th><th></th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="(line, index) in createForm.items" :key="line.product_id">
                                <td>{{ line.name }}</td>
                                <td><input v-model.number="line.quantity" type="number" min="1" step="1"></td>
                                <td><input v-model.number="line.unit_cost" type="number" min="0" step="1"></td>
                                <td>{{ money((line.quantity || 0) * (line.unit_cost || 0)) }}</td>
                                <td><button class="btn-small btn-danger" @click="createForm.items.splice(index, 1)">×</button></td>
                            </tr>
                        </tbody>
                    </table>

                    <label>یادداشت</label>
                    <input v-model="createForm.notes" maxlength="1000">
                    <div class="total-line">جمع کل: <strong>{{ money(createTotal) }}</strong></div>
                </div>
                <div class="modal-footer">
                    <button class="btn-cancel" @click="showCreate = false">انصراف</button>
                    <button class="btn-primary" :disabled="saving || !createForm.supplier_id || !createForm.items.length" @click="submitCreate">ثبت سفارش</button>
                </div>
            </div>
        </div>

        <!-- جزئیات و دریافت کالا -->
        <div v-if="detail" class="modal-overlay" @click.self="closeDetail">
            <div class="modal">
                <div class="modal-header">
                    <h3>{{ detail.po_number }} — {{ detail.supplier_name }}</h3>
                    <button class="close-btn" @click="closeDetail">×</button>
                </div>
                <div class="modal-body">
                    <p>وضعیت: <strong>{{ detail.status_label }}</strong> — جمع کل: {{ money(detail.total_cost) }}</p>
                    <p v-if="detail.notes" class="muted">{{ detail.notes }}</p>

                    <table class="data-table compact">
                        <thead>
                            <tr><th>کالا</th><th>سفارش</th><th>دریافت‌شده</th><th>باقی‌مانده</th><th>دریافت الان</th><th>بهای واحد</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in detail.items" :key="item.id">
                                <td>{{ item.product_name }}</td>
                                <td>{{ item.quantity_ordered }}</td>
                                <td>{{ item.quantity_received }}</td>
                                <td>{{ item.remaining }}</td>
                                <td>
                                    <input
                                        v-if="item.remaining > 0 && canReceive"
                                        v-model.number="receiveForm[item.id].quantity"
                                        type="number" min="0" :max="item.remaining" step="1"
                                    >
                                    <span v-else>—</span>
                                </td>
                                <td>
                                    <input
                                        v-if="item.remaining > 0 && canReceive"
                                        v-model.number="receiveForm[item.id].unit_cost"
                                        type="number" min="0" step="1"
                                    >
                                    <span v-else>{{ money(item.unit_cost) }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="muted">بهای واحد را فقط وقتی تغییر دهید که با فاکتور فرق دارد؛ بهای تمام‌شده‌ی کالا به میانگین موزون به‌روز می‌شود.</p>
                </div>
                <div class="modal-footer">
                    <button class="btn-cancel" @click="closeDetail">بستن</button>
                    <button v-if="detail.status === 'ordered'" class="btn-danger" :disabled="saving" @click="cancelOrder(detail)">لغو سفارش</button>
                    <button v-if="canReceive" class="btn-primary" :disabled="saving" @click="submitReceive">ثبت دریافت</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const emptyCreate = () => ({ supplier_id: '', branch_id: '', notes: '', items: [] });
const emptySupplier = () => ({ id: null, name: '', phone: '', email: '', address: '', notes: '' });

export default {
    name: 'PurchaseOrders',
    setup() {
        const tab = ref('orders');
        const message = ref('');
        const messageType = ref('success');
        const loading = ref(false);
        const saving = ref(false);

        const orders = ref([]);
        const statusLabels = ref({});
        const statusFilter = ref('');
        const pagination = ref({ current_page: 1, last_page: 1, total: 0 });

        const suppliers = ref([]);
        const branches = ref([]);
        const supplierForm = ref(emptySupplier());
        const savingSupplier = ref(false);

        const showCreate = ref(false);
        const createForm = ref(emptyCreate());
        const productQuery = ref('');
        const productResults = ref([]);

        const detail = ref(null);
        const receiveForm = ref({});

        const money = (value) => new Intl.NumberFormat('fa-IR').format(Number(value) || 0) + ' تومان';

        const flash = (text, type = 'success') => {
            message.value = text;
            messageType.value = type;
            setTimeout(() => {
                if (message.value === text) message.value = '';
            }, 5000);
        };

        const errorText = (error, fallback) => {
            const errors = error.response?.data?.errors || {};
            const first = Object.values(errors).flat()[0];
            return error.response?.data?.message || first || fallback;
        };

        const fetchOrders = async (page = 1) => {
            loading.value = true;
            try {
                const response = await axios.get('/api/admin/purchase-orders', {
                    params: { page, status: statusFilter.value || undefined },
                });
                orders.value = response.data.purchase_orders;
                pagination.value = response.data.pagination;
                statusLabels.value = response.data.statuses || {};
            } catch (error) {
                flash(errorText(error, 'خطا در بارگذاری سفارش‌های خرید'), 'error');
            } finally {
                loading.value = false;
            }
        };

        const fetchSuppliers = async () => {
            try {
                const response = await axios.get('/api/admin/suppliers');
                suppliers.value = response.data.suppliers;
            } catch (error) {
                flash(errorText(error, 'خطا در بارگذاری تأمین‌کنندگان'), 'error');
            }
            try {
                const response = await axios.get('/api/admin/branches');
                branches.value = response.data.branches;
            } catch {
                // شعبه‌ها اختیاری‌اند؛ در صورت خطا سفارش به شعبه‌ی اصلی ثبت می‌شود
            }
        };

        // ---- تأمین‌کنندگان ----
        const resetSupplierForm = () => {
            supplierForm.value = emptySupplier();
        };

        const editSupplier = (supplier) => {
            supplierForm.value = { ...supplier };
        };

        const saveSupplier = async () => {
            savingSupplier.value = true;
            const { id, ...data } = supplierForm.value;
            try {
                if (id) {
                    await axios.put(`/api/admin/suppliers/${id}`, data);
                    flash('تأمین‌کننده بروزرسانی شد.');
                } else {
                    await axios.post('/api/admin/suppliers', data);
                    flash('تأمین‌کننده ثبت شد.');
                }
                resetSupplierForm();
                await fetchSuppliers();
            } catch (error) {
                flash(errorText(error, 'ذخیره‌ی تأمین‌کننده انجام نشد'), 'error');
            } finally {
                savingSupplier.value = false;
            }
        };

        const deleteSupplier = async (supplier) => {
            if (!window.confirm(`تأمین‌کننده «${supplier.name}» حذف شود؟`)) return;
            try {
                await axios.delete(`/api/admin/suppliers/${supplier.id}`);
                flash('تأمین‌کننده حذف شد.');
                await fetchSuppliers();
            } catch (error) {
                flash(errorText(error, 'حذف انجام نشد'), 'error');
            }
        };

        // ---- ساخت سفارش ----
        const createTotal = computed(() =>
            createForm.value.items.reduce((sum, line) => sum + (Number(line.quantity) || 0) * (Number(line.unit_cost) || 0), 0)
        );

        const openCreate = () => {
            createForm.value = emptyCreate();
            productQuery.value = '';
            productResults.value = [];
            showCreate.value = true;
        };

        const searchProducts = async () => {
            const q = productQuery.value.trim();
            if (!q) return;
            try {
                const response = await axios.get('/api/admin/products', { params: { search: q } });
                productResults.value = response.data.products;
            } catch (error) {
                flash(errorText(error, 'جستجوی کالا انجام نشد'), 'error');
            }
        };

        const addLine = (product) => {
            if (createForm.value.items.some((l) => l.product_id === product.id)) {
                flash('این کالا قبلاً به سفارش اضافه شده است.', 'error');
                return;
            }
            createForm.value.items.push({
                product_id: product.id,
                name: product.name,
                quantity: 1,
                unit_cost: product.cost_price ?? 0,
            });
        };

        const submitCreate = async () => {
            saving.value = true;
            try {
                await axios.post('/api/admin/purchase-orders', {
                    supplier_id: createForm.value.supplier_id,
                    branch_id: createForm.value.branch_id || null,
                    notes: createForm.value.notes || null,
                    items: createForm.value.items.map((l) => ({
                        product_id: l.product_id,
                        quantity: Number(l.quantity),
                        unit_cost: Number(l.unit_cost),
                    })),
                });
                showCreate.value = false;
                flash('سفارش خرید ثبت شد.');
                fetchOrders(1);
            } catch (error) {
                flash(errorText(error, 'ثبت سفارش انجام نشد'), 'error');
            } finally {
                saving.value = false;
            }
        };

        // ---- جزئیات و دریافت ----
        const canReceive = computed(() => detail.value && ['ordered', 'partially_received'].includes(detail.value.status));

        const applyDetail = (po) => {
            detail.value = po;
            receiveForm.value = Object.fromEntries(
                po.items.map((item) => [item.id, { quantity: 0, unit_cost: item.unit_cost }])
            );
        };

        const openDetail = async (id) => {
            try {
                const response = await axios.get(`/api/admin/purchase-orders/${id}`);
                applyDetail(response.data.purchase_order);
            } catch (error) {
                flash(errorText(error, 'خطا در بارگذاری سفارش'), 'error');
            }
        };

        const closeDetail = () => {
            detail.value = null;
        };

        const submitReceive = async () => {
            const items = Object.entries(receiveForm.value)
                .map(([id, line]) => ({ item_id: Number(id), quantity: Number(line.quantity) || 0, unit_cost: line.unit_cost }))
                .filter((line) => line.quantity > 0);

            if (!items.length) {
                flash('تعداد دریافتی را برای حداقل یک ردیف وارد کنید.', 'error');
                return;
            }

            saving.value = true;
            try {
                const response = await axios.post(`/api/admin/purchase-orders/${detail.value.id}/receive`, { items });
                applyDetail(response.data.purchase_order);
                flash('کالاها دریافت و موجودی به‌روز شد.');
                fetchOrders(pagination.value.current_page);
            } catch (error) {
                flash(errorText(error, 'دریافت انجام نشد'), 'error');
            } finally {
                saving.value = false;
            }
        };

        const cancelOrder = async (po) => {
            if (!window.confirm(`سفارش ${po.po_number} لغو شود؟`)) return;
            saving.value = true;
            try {
                await axios.post(`/api/admin/purchase-orders/${po.id}/cancel`);
                flash('سفارش لغو شد.');
                detail.value = null;
                fetchOrders(pagination.value.current_page);
            } catch (error) {
                flash(errorText(error, 'لغو انجام نشد'), 'error');
            } finally {
                saving.value = false;
            }
        };

        onMounted(() => {
            fetchOrders(1);
            fetchSuppliers();
        });

        return {
            tab, message, messageType, loading, saving,
            orders, statusLabels, statusFilter, pagination, fetchOrders,
            suppliers, branches, supplierForm, savingSupplier, resetSupplierForm, editSupplier, saveSupplier, deleteSupplier,
            showCreate, createForm, createTotal, productQuery, productResults, openCreate, searchProducts, addLine, submitCreate,
            detail, receiveForm, canReceive, openDetail, closeDetail, submitReceive, cancelOrder,
            money,
        };
    },
};
</script>

<style scoped>
.po-page { padding: 24px; }
.page-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; }
.page-header h2 { margin: 0; }
.tabs { display: flex; gap: 6px; }
.tabs button { padding: 8px 14px; border: 1px solid #cbd5e0; background: #fff; border-radius: 8px; cursor: pointer; }
.tabs button.active { background: #2b6cb0; color: #fff; border-color: #2b6cb0; }
.flash { padding: 10px 14px; border-radius: 8px; margin-bottom: 12px; }
.flash.success { background: #f0fff4; color: #276749; border: 1px solid #9ae6b4; }
.flash.error { background: #fff5f5; color: #9b2c2c; border: 1px solid #feb2b2; }
.toolbar { display: flex; justify-content: space-between; gap: 10px; margin-bottom: 12px; flex-wrap: wrap; }
.toolbar select, .form-grid input, .modal input, .modal select, .search-row input { padding: 8px; border: 1px solid #cbd5e0; border-radius: 6px; }
.table-container { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
.data-table th, .data-table td { padding: 10px; border-bottom: 1px solid #edf2f7; text-align: right; white-space: nowrap; }
.data-table th { background: #f7fafc; color: #4a5568; }
.data-table.compact input { width: 90px; padding: 4px; }
.badge { padding: 3px 8px; border-radius: 999px; background: #edf2f7; font-size: 0.8rem; }
.badge-received { background: #c6f6d5; color: #22543d; }
.badge-partially_received { background: #fefcbf; color: #744210; }
.badge-cancelled { background: #fed7d7; color: #822727; }
.actions { display: flex; gap: 6px; }
.btn-small { padding: 5px 10px; border: 1px solid #cbd5e0; background: #fff; border-radius: 6px; cursor: pointer; }
.btn-danger { color: #c53030; border-color: #feb2b2; background: #fff; border-radius: 6px; padding: 6px 12px; cursor: pointer; }
.btn-primary { padding: 8px 14px; background: #2b6cb0; color: #fff; border: none; border-radius: 8px; cursor: pointer; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-cancel { padding: 8px 14px; background: #fff; border: 1px solid #cbd5e0; border-radius: 8px; cursor: pointer; }
.card-form { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; margin-bottom: 14px; }
.card-form h3 { margin: 0 0 10px; font-size: 1rem; }
.form-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(180px, 100%), 1fr)); gap: 8px; }
.form-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 10px; }
.empty { padding: 30px; text-align: center; color: #718096; }
.muted { color: #718096; font-size: 0.85rem; }
.pagination { display: flex; gap: 12px; align-items: center; justify-content: center; margin-top: 14px; }
.pagination button { padding: 6px 12px; border: 1px solid #cbd5e0; background: #fff; border-radius: 6px; cursor: pointer; }
.pagination button:disabled { opacity: 0.5; cursor: not-allowed; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0, 0, 0, 0.4); display: flex; align-items: center; justify-content: center; z-index: 50; padding: 16px; }
.modal { background: #fff; border-radius: 12px; width: min(760px, 100%); max-height: 90vh; display: flex; flex-direction: column; }
.modal-header, .modal-footer { display: flex; justify-content: space-between; align-items: center; padding: 14px 18px; border-bottom: 1px solid #edf2f7; }
.modal-footer { border-top: 1px solid #edf2f7; border-bottom: none; justify-content: flex-end; gap: 8px; }
.modal-header h3 { margin: 0; }
.modal-body { padding: 14px 18px; overflow-y: auto; display: flex; flex-direction: column; gap: 8px; }
.close-btn { background: none; border: none; font-size: 1.4rem; cursor: pointer; }
.search-row { display: flex; gap: 6px; }
.search-row input { flex: 1; }
.results { display: flex; flex-direction: column; gap: 4px; max-height: 160px; overflow-y: auto; }
.result-item { text-align: right; padding: 6px 10px; border: 1px solid #e2e8f0; background: #f7fafc; border-radius: 6px; cursor: pointer; }
.total-line { text-align: left; margin-top: 6px; }
</style>
