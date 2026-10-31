<template>
    <div class="cash-shifts-page">
        <div class="page-header">
            <h2>🧾 صندوق‌ها و مغایرت نقد</h2>
            <label class="filter-check">
                <input type="checkbox" v-model="onlyMismatched" @change="fetchShifts(1)">
                فقط صندوق‌های دارای مغایرت
            </label>
        </div>

        <div class="table-container">
            <table v-if="shifts.length" class="shifts-table">
                <thead>
                    <tr>
                        <th>کاربر</th>
                        <th>باز شد</th>
                        <th>بسته شد</th>
                        <th>مبلغ اولیه</th>
                        <th>مورد انتظار</th>
                        <th>شمرده‌شده</th>
                        <th>مغایرت</th>
                        <th>یادداشت</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="s in shifts" :key="s.id">
                        <td>{{ s.user_name }}</td>
                        <td>{{ s.opened_at }}</td>
                        <td>{{ s.closed_at || 'باز' }}</td>
                        <td>{{ formatPrice(s.opening_cash) }}</td>
                        <td>{{ s.expected_cash !== null ? formatPrice(s.expected_cash) : '—' }}</td>
                        <td>{{ s.counted_cash !== null ? formatPrice(s.counted_cash) : '—' }}</td>
                        <td :class="diffClass(s.difference)">
                            <template v-if="s.difference === null">—</template>
                            <template v-else>{{ s.difference > 0 ? '+' : '' }}{{ formatPrice(s.difference) }}</template>
                        </td>
                        <td class="notes">{{ s.notes || '' }}</td>
                    </tr>
                </tbody>
            </table>
            <div v-else-if="!loading" class="empty">موردی یافت نشد.</div>
            <div v-if="loading" class="empty">در حال بارگذاری...</div>
        </div>

        <div v-if="pagination.last_page > 1" class="pagination">
            <button :disabled="pagination.current_page <= 1" @click="fetchShifts(pagination.current_page - 1)">قبلی</button>
            <span>صفحه {{ pagination.current_page }} از {{ pagination.last_page }}</span>
            <button :disabled="pagination.current_page >= pagination.last_page" @click="fetchShifts(pagination.current_page + 1)">بعدی</button>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import axios from 'axios';

export default {
    name: 'AdminCashShifts',
    setup() {
        const shifts = ref([]);
        const loading = ref(false);
        const onlyMismatched = ref(false);
        const pagination = ref({ current_page: 1, last_page: 1, total: 0 });

        const formatPrice = (value) => new Intl.NumberFormat('fa-IR').format(value ?? 0) + ' تومان';

        const diffClass = (diff) => {
            if (diff === null || diff === 0) return '';
            return diff > 0 ? 'diff-plus' : 'diff-minus';
        };

        const fetchShifts = async (page = 1) => {
            loading.value = true;
            try {
                const response = await axios.get('/api/admin/cash-shifts', {
                    params: { page, only_mismatched: onlyMismatched.value ? 1 : 0 },
                });
                shifts.value = response.data.shifts;
                pagination.value = response.data.pagination;
            } catch (error) {
                console.error('Error fetching cash shifts:', error);
            } finally {
                loading.value = false;
            }
        };

        onMounted(() => fetchShifts(1));

        return { shifts, loading, onlyMismatched, pagination, formatPrice, diffClass, fetchShifts };
    },
};
</script>

<style scoped>
.cash-shifts-page { padding: 24px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px; }
.page-header h2 { margin: 0; }
.filter-check { display: flex; align-items: center; gap: 6px; font-size: 0.9rem; cursor: pointer; }
.table-container { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; overflow-x: auto; }
.shifts-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
.shifts-table th, .shifts-table td { padding: 10px; border-bottom: 1px solid #edf2f7; text-align: right; white-space: nowrap; }
.shifts-table th { background: #f7fafc; color: #4a5568; }
.notes { white-space: normal; max-width: 260px; color: #4a5568; }
.diff-plus { color: #2f855a; font-weight: 600; }
.diff-minus { color: #c53030; font-weight: 600; }
.empty { padding: 30px; text-align: center; color: #718096; }
.pagination { display: flex; gap: 12px; align-items: center; justify-content: center; margin-top: 14px; }
.pagination button { padding: 6px 12px; border: 1px solid #cbd5e0; background: #fff; border-radius: 6px; cursor: pointer; }
.pagination button:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
