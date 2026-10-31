<template>
    <div class="shift-page">
        <div class="page-header">
            <h2>🧾 صندوق کاری</h2>
        </div>

        <div v-if="loading" class="loading">در حال بارگذاری...</div>

        <!-- بدون صندوق باز -->
        <div v-else-if="!shift" class="card">
            <h3>صندوق باز نیست</h3>
            <p class="hint">برای ثبت سفارش نقدی، ابتدا صندوق را با مبلغ اولیه‌ی نقدی باز کنید.</p>
            <form @submit.prevent="openShift" class="form">
                <label>مبلغ اولیه‌ی صندوق (تومان)</label>
                <input type="number" min="0" step="1" v-model.number="openForm.opening_cash" required :disabled="busy">
                <label>یادداشت</label>
                <input type="text" maxlength="500" v-model="openForm.notes" :disabled="busy">
                <button type="submit" class="btn-primary" :disabled="busy">باز کردن صندوق</button>
            </form>
        </div>

        <!-- صندوق باز -->
        <div v-else class="card">
            <div class="shift-head">
                <h3>صندوق باز از {{ shift.opened_at }}</h3>
                <span class="badge badge-open">باز</span>
            </div>

            <div v-if="shift.summary" class="summary-grid">
                <div class="summary-item"><span>مبلغ اولیه</span><strong>{{ formatPrice(shift.summary.opening_cash) }}</strong></div>
                <div class="summary-item"><span>فروش نقدی</span><strong>{{ formatPrice(shift.summary.sales_cash) }}</strong></div>
                <div class="summary-item"><span>فروش کارتی</span><strong>{{ formatPrice(shift.summary.sales_card) }}</strong></div>
                <div class="summary-item"><span>مرجوعی نقدی</span><strong>{{ formatPrice(shift.summary.refunds_cash) }}</strong></div>
                <div class="summary-item"><span>مرجوعی کارتی</span><strong>{{ formatPrice(shift.summary.refunds_card) }}</strong></div>
                <div class="summary-item accent"><span>موجودی مورد انتظار نقد</span><strong>{{ formatPrice(shift.summary.expected_cash) }}</strong></div>
            </div>
            <p class="hint">تعداد سفارش‌های این صندوق: {{ shift.summary?.orders_count ?? 0 }}</p>

            <form @submit.prevent="closeShift" class="form close-form">
                <h4>بستن صندوق</h4>
                <label>نقد شمارش‌شده (تومان)</label>
                <input type="number" min="0" step="1" v-model.number="closeForm.counted_cash" required :disabled="busy">
                <label>یادداشت (مثلاً دلیل مغایرت)</label>
                <input type="text" maxlength="500" v-model="closeForm.notes" :disabled="busy">
                <button type="submit" class="btn-danger" :disabled="busy">بستن صندوق</button>
            </form>
        </div>

        <!-- نتیجه‌ی آخرین بستن -->
        <div v-if="lastClosed" class="card result-card">
            <h4>نتیجه‌ی بستن صندوق #{{ lastClosed.id }}</h4>
            <p>
                مورد انتظار: {{ formatPrice(lastClosed.expected_cash) }} —
                شمرده‌شده: {{ formatPrice(lastClosed.counted_cash) }}
            </p>
            <p :class="differenceClass(lastClosed.difference)">
                <template v-if="lastClosed.difference === 0">بدون مغایرت ✔</template>
                <template v-else-if="lastClosed.difference > 0">مازاد: {{ formatPrice(lastClosed.difference) }}</template>
                <template v-else>کسری: {{ formatPrice(Math.abs(lastClosed.difference)) }}</template>
            </p>
        </div>

        <div class="card">
            <h4>تاریخچه‌ی صندوق‌های من</h4>
            <table class="shift-table" v-if="history.length">
                <thead>
                    <tr>
                        <th>باز شد</th>
                        <th>بسته شد</th>
                        <th>مبلغ اولیه</th>
                        <th>مورد انتظار</th>
                        <th>شمرده‌شده</th>
                        <th>مغایرت</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="s in history" :key="s.id">
                        <td>{{ s.opened_at }}</td>
                        <td>{{ s.closed_at || 'باز' }}</td>
                        <td>{{ formatPrice(s.opening_cash) }}</td>
                        <td>{{ s.expected_cash !== null ? formatPrice(s.expected_cash) : '—' }}</td>
                        <td>{{ s.counted_cash !== null ? formatPrice(s.counted_cash) : '—' }}</td>
                        <td :class="differenceClass(s.difference)">{{ s.difference !== null ? formatPrice(s.difference) : '—' }}</td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="hint">هنوز صندوقی ثبت نشده است.</p>
        </div>

        <div v-if="message" :class="['toast', messageType]">{{ message }}</div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import axios from 'axios';

export default {
    name: 'ReceptionShift',
    setup() {
        const shift = ref(null);
        const history = ref([]);
        const loading = ref(true);
        const busy = ref(false);
        const message = ref('');
        const messageType = ref('success');
        const lastClosed = ref(null);

        const openForm = ref({ opening_cash: 0, notes: '' });
        const closeForm = ref({ counted_cash: 0, notes: '' });

        const formatPrice = (value) => new Intl.NumberFormat('fa-IR').format(value ?? 0) + ' تومان';

        const differenceClass = (diff) => {
            if (diff === null || diff === undefined || diff === 0) return 'diff-zero';
            return diff > 0 ? 'diff-plus' : 'diff-minus';
        };

        const notify = (text, type = 'success') => {
            message.value = text;
            messageType.value = type;
            setTimeout(() => { message.value = ''; }, 4000);
        };

        const errorText = (error) => {
            const errors = error.response?.data?.errors;
            return (errors && Object.values(errors).flat()[0]) || error.response?.data?.message || 'خطایی رخ داد';
        };

        const fetchAll = async () => {
            loading.value = true;
            try {
                const [current, past] = await Promise.all([
                    axios.get('/api/reception/cash-shifts/current'),
                    axios.get('/api/reception/cash-shifts'),
                ]);
                shift.value = current.data.shift;
                history.value = past.data.shifts;
            } catch (error) {
                notify(errorText(error), 'error');
            } finally {
                loading.value = false;
            }
        };

        const openShift = async () => {
            busy.value = true;
            try {
                const response = await axios.post('/api/reception/cash-shifts', openForm.value);
                shift.value = response.data.shift;
                lastClosed.value = null;
                openForm.value = { opening_cash: 0, notes: '' };
                notify(response.data.message);
                await fetchAll();
            } catch (error) {
                notify(errorText(error), 'error');
            } finally {
                busy.value = false;
            }
        };

        const closeShift = async () => {
            if (!window.confirm('صندوق بسته شود؟ پس از بستن، ثبت سفارش نقدی با این صندوق ادامه پیدا نمی‌کند.')) return;
            busy.value = true;
            try {
                const response = await axios.post(`/api/reception/cash-shifts/${shift.value.id}/close`, closeForm.value);
                lastClosed.value = response.data.shift;
                closeForm.value = { counted_cash: 0, notes: '' };
                notify(response.data.message);
                await fetchAll();
            } catch (error) {
                notify(errorText(error), 'error');
            } finally {
                busy.value = false;
            }
        };

        onMounted(fetchAll);

        return {
            shift, history, loading, busy, message, messageType, lastClosed,
            openForm, closeForm, formatPrice, differenceClass, openShift, closeShift,
        };
    },
};
</script>

<style scoped>
.shift-page { padding: 24px; max-width: 960px; }
.page-header h2 { margin: 0 0 16px; }
.card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; margin-bottom: 16px; }
.card h3, .card h4 { margin: 0 0 10px; }
.hint { color: #718096; font-size: 0.9rem; }
.form { display: flex; flex-direction: column; gap: 8px; max-width: 360px; }
.form input { padding: 8px 10px; border: 1px solid #cbd5e0; border-radius: 6px; }
.close-form { margin-top: 18px; padding-top: 14px; border-top: 1px solid #edf2f7; }
.shift-head { display: flex; justify-content: space-between; align-items: center; }
.badge { padding: 3px 10px; border-radius: 999px; font-size: 12px; }
.badge-open { background: #c6f6d5; color: #22543d; }
.summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(180px, 100%), 1fr)); gap: 10px; margin: 10px 0; }
.summary-item { background: #f7fafc; border-radius: 8px; padding: 10px; display: flex; flex-direction: column; gap: 4px; font-size: 0.85rem; color: #4a5568; }
.summary-item strong { font-size: 1rem; color: #1a202c; }
.summary-item.accent { background: #ebf8ff; }
.btn-primary, .btn-danger { padding: 9px 16px; border: none; border-radius: 6px; color: #fff; cursor: pointer; width: fit-content; }
.btn-primary { background: #3182ce; }
.btn-danger { background: #e53e3e; }
button:disabled { opacity: 0.6; cursor: not-allowed; }
.shift-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
.shift-table th, .shift-table td { padding: 8px; border-bottom: 1px solid #edf2f7; text-align: right; }
.diff-zero { color: #2d3748; }
.diff-plus { color: #2f855a; font-weight: 600; }
.diff-minus { color: #c53030; font-weight: 600; }
.toast { position: fixed; bottom: 20px; left: 20px; padding: 12px 16px; border-radius: 8px; color: #fff; }
.toast.success { background: #2f855a; }
.toast.error { background: #c53030; }
.loading { padding: 40px; text-align: center; color: #718096; }
</style>
