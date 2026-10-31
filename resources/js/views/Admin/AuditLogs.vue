<template>
    <div class="audit-page">
        <div class="page-header">
            <h2><IconUserSearch class="emo-icon" aria-hidden="true" /> گزارش فعالیت‌ها (Audit Log)</h2>
            <span class="muted">هر ثبت فقط برای خواندن است و ویرایش یا حذف نمی‌شود.</span>
        </div>

        <form class="filters" @submit.prevent="fetchLogs(1)">
            <input v-model="filters.user" placeholder="نام کاربر" maxlength="120">
            <select v-model="filters.action">
                <option value="">همه‌ی عملیات</option>
                <option v-for="(label, key) in actions" :key="key" :value="key">{{ label }}</option>
            </select>
            <select v-model="filters.subject_type">
                <option value="">همه‌ی موجودیت‌ها</option>
                <option v-for="(label, key) in subjectTypes" :key="key" :value="key">{{ label }}</option>
            </select>
            <input v-model="filters.search" placeholder="جستجو در توضیحات" maxlength="120">
            <label>از <input v-model="filters.from" type="date"></label>
            <label>تا <input v-model="filters.to" type="date"></label>
            <button type="submit" class="btn-primary">اعمال فیلتر</button>
            <button type="button" class="btn-cancel" @click="resetFilters">پاک کردن</button>
        </form>

        <div class="table-container">
            <div class="table-scroll" v-if="logs.length"><table class="data-table">
                <thead>
                    <tr>
                        <th>زمان</th>
                        <th>کاربر</th>
                        <th>عملیات</th>
                        <th>موضوع</th>
                        <th>شرح</th>
                        <th>جزئیات</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="log in logs" :key="log.id">
                        <td class="nowrap">{{ log.created_at }}</td>
                        <td>{{ log.user_name }}</td>
                        <td><span class="badge">{{ log.action_label }}</span></td>
                        <td>{{ log.subject_label || '—' }}</td>
                        <td class="desc">{{ log.description }}</td>
                        <td>
                            <details v-if="log.changes">
                                <summary>تغییرات</summary>
                                <pre>{{ JSON.stringify(log.changes, null, 2) }}</pre>
                            </details>
                            <span v-else>—</span>
                        </td>
                    </tr>
                </tbody>
            </table></div>
            <div v-else-if="!loading" class="empty">رویدادی با این فیلتر پیدا نشد.</div>
            <div v-if="loading" class="empty">در حال بارگذاری...</div>
        </div>

        <div v-if="pagination.last_page > 1" class="pagination">
            <button :disabled="pagination.current_page <= 1" @click="fetchLogs(pagination.current_page - 1)">قبلی</button>
            <span>صفحه {{ pagination.current_page }} از {{ pagination.last_page }} ({{ pagination.total }} رویداد)</span>
            <button :disabled="pagination.current_page >= pagination.last_page" @click="fetchLogs(pagination.current_page + 1)">بعدی</button>
        </div>
    </div>
</template>

<script>
import { UserSearch as IconUserSearch } from 'lucide-vue-next';
import { ref, onMounted } from 'vue';
import axios from 'axios';

const emptyFilters = () => ({ user: '', action: '', subject_type: '', search: '', from: '', to: '' });

export default {
    components: { IconUserSearch },
    name: 'AuditLogs',
    setup() {
        const logs = ref([]);
        const loading = ref(false);
        const filters = ref(emptyFilters());
        const pagination = ref({ current_page: 1, last_page: 1, total: 0 });
        const actions = ref({});
        const subjectTypes = ref({
            Product: 'محصول',
            ProductCategory: 'دسته‌بندی',
            Discount: 'تخفیف',
            Customer: 'مشتری',
            User: 'کاربر',
            Order: 'سفارش',
            CashShift: 'صندوق',
            SmsCampaign: 'کمپین پیامکی',
            GiftCard: 'گیفت کارت',
            Supplier: 'تأمین‌کننده',
            PurchaseOrder: 'سفارش خرید',
        });

        const fetchLogs = async (page = 1) => {
            loading.value = true;
            try {
                const params = { page };
                Object.entries(filters.value).forEach(([key, value]) => {
                    if (value) params[key] = value;
                });
                const response = await axios.get('/api/admin/audit-logs', { params });
                logs.value = response.data.logs;
                pagination.value = response.data.pagination;
                actions.value = response.data.actions || {};
            } catch (error) {
                console.error('Error fetching audit logs:', error);
            } finally {
                loading.value = false;
            }
        };

        const resetFilters = () => {
            filters.value = emptyFilters();
            fetchLogs(1);
        };

        onMounted(() => fetchLogs(1));

        return { logs, loading, filters, pagination, actions, subjectTypes, fetchLogs, resetFilters };
    },
};
</script>

<style scoped>
.audit-page { padding: 24px; }
.muted { color: #718096; font-size: 0.85rem; }
.filters { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 14px; background: #fff; padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px; }
.filters input, .filters select { padding: 7px; border: 1px solid #cbd5e0; border-radius: 6px; }
.filters label { display: flex; gap: 4px; align-items: center; font-size: 0.85rem; }
.table-container { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; font-size: 0.86rem; }
.data-table th, .data-table td { padding: 9px; border-bottom: 1px solid #edf2f7; text-align: right; vertical-align: top; }
.data-table th { background: #f7fafc; color: #4a5568; }
.nowrap { white-space: nowrap; }
.desc { max-width: 320px; }
.badge { padding: 3px 8px; border-radius: 999px; background: #edf2f7; white-space: nowrap; }
pre { background: #f7fafc; padding: 8px; border-radius: 6px; font-size: 0.78rem; max-width: 360px; overflow-x: auto; direction: ltr; text-align: left; }
.empty { padding: 30px; text-align: center; color: #718096; }
.pagination { display: flex; gap: 12px; align-items: center; justify-content: center; margin-top: 14px; }
.pagination button { padding: 6px 12px; border: 1px solid #cbd5e0; background: #fff; border-radius: 6px; cursor: pointer; }
.pagination button:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
