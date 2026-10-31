<template>
    <div class="gift-page">
        <div class="page-header">
            <h2><IconGift class="emo-icon" aria-hidden="true" /> گیفت کارت‌ها</h2>
        </div>

        <div v-if="message" :class="['flash', messageType]">{{ message }}</div>

        <form class="card-form" @submit.prevent="issue">
            <h3>صدور گیفت کارت</h3>
            <div class="form-grid">
                <input v-model.number="form.amount" type="number" min="1000" step="1000" placeholder="مبلغ (تومان) *" required>
                <select v-model="form.payment_method" required>
                    <option value="cash">پرداخت نقد</option>
                    <option value="card">پرداخت کارت</option>
                </select>
                <input v-model="form.customer_membership_number" placeholder="شماره اشتراک مشتری (اختیاری)">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-primary" :disabled="saving">صدور</button>
            </div>
            <p class="muted">کد کارت یک بار کامل نمایش داده می‌شود؛ در جدول زیر هم قابل مشاهده است. مبلغ کارت در فروش خود را در صندوق ثبت کنید.</p>
        </form>

        <div class="toolbar">
            <input v-model="search" placeholder="جستجوی کد کارت" @keyup.enter="fetchCards(1)">
            <button class="btn-small" @click="fetchCards(1)">جستجو</button>
        </div>

        <div class="table-container">
            <div class="table-scroll" v-if="cards.length"><table class="data-table">
                <thead>
                    <tr>
                        <th>کد</th>
                        <th>مبلغ اولیه</th>
                        <th>مانده</th>
                        <th>وضعیت</th>
                        <th>مشتری</th>
                        <th>صادرکننده</th>
                        <th>انقضا</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="card in cards" :key="card.id">
                        <td class="code" data-label="کد">{{ card.code }}</td>
                        <td data-label="مبلغ اولیه">{{ money(card.initial_amount) }}</td>
                        <td data-label="مانده">{{ money(card.balance) }}</td>
                        <td data-label="وضعیت">
                            <span :class="['badge', card.is_usable ? 'badge-ok' : 'badge-off']">
                                {{ card.is_usable ? 'قابل استفاده' : (card.is_active ? 'تمام‌شده/منقضی' : 'غیرفعال') }}
                            </span>
                        </td>
                        <td data-label="مشتری">{{ card.customer_name || '—' }}</td>
                        <td data-label="صادرکننده">{{ card.issued_by || '—' }}</td>
                        <td data-label="انقضا">{{ card.expires_at || '—' }}</td>
                        <td data-label="عملیات">
                            <button class="btn-small" @click="toggle(card)">
                                {{ card.is_active ? 'غیرفعال کردن' : 'فعال کردن' }}
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table></div>
            <div v-else-if="!loading" class="empty">گیفت کارتی یافت نشد.</div>
            <div v-if="loading" class="empty">در حال بارگذاری...</div>
        </div>

        <div v-if="pagination.last_page > 1" class="pagination">
            <button :disabled="pagination.current_page <= 1" @click="fetchCards(pagination.current_page - 1)">قبلی</button>
            <span>صفحه {{ pagination.current_page }} از {{ pagination.last_page }}</span>
            <button :disabled="pagination.current_page >= pagination.last_page" @click="fetchCards(pagination.current_page + 1)">بعدی</button>
        </div>
    </div>
</template>

<script>
import { Gift as IconGift } from 'lucide-vue-next';
import { ref, onMounted } from 'vue';
import axios from 'axios';

const emptyForm = () => ({ amount: 100000, payment_method: 'cash', customer_membership_number: '' });

export default {
    components: { IconGift },
    name: 'AdminGiftCards',
    setup() {
        const cards = ref([]);
        const loading = ref(false);
        const saving = ref(false);
        const search = ref('');
        const form = ref(emptyForm());
        const message = ref('');
        const messageType = ref('success');
        const pagination = ref({ current_page: 1, last_page: 1, total: 0 });

        const money = (value) => new Intl.NumberFormat('fa-IR').format(Number(value) || 0) + ' تومان';

        const flash = (text, type = 'success') => {
            message.value = text;
            messageType.value = type;
            setTimeout(() => {
                if (message.value === text) message.value = '';
            }, 5000);
        };

        const errorText = (error, fallback) => {
            const first = Object.values(error.response?.data?.errors || {}).flat()[0];
            return error.response?.data?.message || first || fallback;
        };

        const fetchCards = async (page = 1) => {
            loading.value = true;
            try {
                const response = await axios.get('/api/admin/gift-cards', { params: { page, search: search.value || undefined } });
                cards.value = response.data.gift_cards;
                pagination.value = response.data.pagination;
            } catch (error) {
                flash(errorText(error, 'خطا در بارگذاری گیفت کارت‌ها'), 'error');
            } finally {
                loading.value = false;
            }
        };

        const issue = async () => {
            saving.value = true;
            try {
                const payload = { ...form.value, customer_membership_number: form.value.customer_membership_number || null };
                const response = await axios.post('/api/admin/gift-cards', payload);
                flash(`${response.data.message} کد: ${response.data.gift_card.code}`);
                form.value = emptyForm();
                fetchCards(1);
            } catch (error) {
                flash(errorText(error, 'صدور گیفت کارت انجام نشد'), 'error');
            } finally {
                saving.value = false;
            }
        };

        const toggle = async (card) => {
            try {
                const response = await axios.patch(`/api/admin/gift-cards/${card.id}`, { is_active: !card.is_active });
                flash(response.data.message);
                fetchCards(pagination.value.current_page);
            } catch (error) {
                flash(errorText(error, 'تغییر وضعیت انجام نشد'), 'error');
            }
        };

        onMounted(() => fetchCards(1));

        return { cards, loading, saving, search, form, message, messageType, pagination, money, fetchCards, issue, toggle };
    },
};
</script>

<style scoped>
.gift-page { padding: 24px; }
.flash { padding: 10px 14px; border-radius: 8px; margin-bottom: 12px; }
.flash.success { background: #f0fff4; color: #276749; border: 1px solid #9ae6b4; }
.flash.error { background: #fff5f5; color: #9b2c2c; border: 1px solid #feb2b2; }
.card-form { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; margin-bottom: 14px; }
.card-form h3 { margin: 0 0 10px; font-size: 1rem; }
.form-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(200px, 100%), 1fr)); gap: 8px; }
.form-grid input, .form-grid select, .toolbar input { padding: 8px; border: 1px solid #cbd5e0; border-radius: 6px; }
.form-actions { display: flex; justify-content: flex-end; margin-top: 10px; }
.toolbar { display: flex; gap: 8px; margin-bottom: 12px; }
.muted { color: #718096; font-size: 0.85rem; }
.table-container { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
.data-table th, .data-table td { padding: 10px; border-bottom: 1px solid #edf2f7; text-align: right; white-space: nowrap; }
.data-table th { background: #f7fafc; color: #4a5568; }
.code { font-family: monospace; direction: ltr; }
.badge { padding: 3px 8px; border-radius: 999px; background: #edf2f7; }
.badge-ok { background: #c6f6d5; color: #22543d; }
.badge-off { background: #fed7d7; color: #822727; }
.empty { padding: 30px; text-align: center; color: #718096; }
.pagination { display: flex; gap: 12px; align-items: center; justify-content: center; margin-top: 14px; }
.pagination button { padding: 6px 12px; border: 1px solid #cbd5e0; background: #fff; border-radius: 6px; cursor: pointer; }
.pagination button:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
