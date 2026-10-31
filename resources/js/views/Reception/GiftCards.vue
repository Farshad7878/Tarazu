<template>
    <div class="gift-reception-page">
        <div class="page-header">
            <h2><IconGift class="emo-icon" aria-hidden="true" /> کارت هدیه</h2>
            <p class="muted">صدور کارت هدیه برای مشتری و استعلام مانده‌ی کارت.</p>
        </div>

        <div v-if="message" :class="['flash', messageType]">{{ message }}</div>

        <div class="grid">
            <form class="card-form" @submit.prevent="issue">
                <h3>صدور کارت جدید</h3>
                <label>مبلغ (تومان)</label>
                <input v-model.number="form.amount" type="number" min="1000" step="1000" required>
                <label>روش پرداخت</label>
                <select v-model="form.payment_method">
                    <option value="cash">نقد</option>
                    <option value="card">کارت بانکی</option>
                </select>
                <label>شماره اشتراک مشتری (اختیاری)</label>
                <input v-model="form.customer_membership_number" placeholder="مثلاً ۱۰۲۳۴۵">
                <button type="submit" class="btn-primary" :disabled="saving">صدور کارت</button>
                <p class="muted">فروش نقدی کارت در صندوق باز ثبت می‌شود.</p>
            </form>

            <form class="card-form" @submit.prevent="lookup">
                <h3>استعلام مانده</h3>
                <label>کد کارت هدیه</label>
                <input v-model="lookupCode" placeholder="GC..." required>
                <button type="submit" class="btn-primary" :disabled="loadingLookup">استعلام</button>
                <div v-if="card" class="result">
                    <div>کد: <span class="code">{{ card.code }}</span></div>
                    <div>مانده: <strong>{{ money(card.balance) }}</strong></div>
                    <div>وضعیت: <span :class="card.is_usable ? 'ok' : 'off'">{{ card.is_usable ? 'قابل استفاده' : 'قابل استفاده نیست' }}</span></div>
                    <div v-if="card.expires_at">انقضا: {{ card.expires_at }}</div>
                </div>
            </form>
        </div>
    </div>
</template>

<script>
import { Gift as IconGift } from 'lucide-vue-next';
import { ref } from 'vue';
import axios from 'axios';

const emptyForm = () => ({ amount: 100000, payment_method: 'cash', customer_membership_number: '' });

export default {
    components: { IconGift },
    name: 'ReceptionGiftCards',
    setup() {
        const form = ref(emptyForm());
        const saving = ref(false);
        const loadingLookup = ref(false);
        const lookupCode = ref('');
        const card = ref(null);
        const message = ref('');
        const messageType = ref('success');

        const money = (value) => new Intl.NumberFormat('fa-IR').format(Number(value) || 0) + ' تومان';

        const flash = (text, type = 'success') => {
            message.value = text;
            messageType.value = type;
            setTimeout(() => {
                if (message.value === text) message.value = '';
            }, 8000);
        };

        const errorText = (error, fallback) => {
            const first = Object.values(error.response?.data?.errors || {}).flat()[0];
            return error.response?.data?.message || first || fallback;
        };

        const issue = async () => {
            saving.value = true;
            try {
                const payload = { ...form.value, customer_membership_number: form.value.customer_membership_number || null };
                const response = await axios.post('/api/reception/gift-cards', payload);
                flash(`${response.data.message} کد: ${response.data.gift_card.code}`);
                form.value = emptyForm();
                lookupCode.value = response.data.gift_card.code;
                await lookup();
            } catch (error) {
                flash(errorText(error, 'صدور کارت انجام نشد'), 'error');
            } finally {
                saving.value = false;
            }
        };

        const lookup = async () => {
            loadingLookup.value = true;
            try {
                const response = await axios.get(`/api/reception/gift-cards/${encodeURIComponent(lookupCode.value.trim())}`);
                card.value = response.data.gift_card;
            } catch (error) {
                card.value = null;
                flash(errorText(error, 'استعلام انجام نشد'), 'error');
            } finally {
                loadingLookup.value = false;
            }
        };

        return { form, saving, loadingLookup, lookupCode, card, message, messageType, money, issue, lookup };
    },
};
</script>

<style scoped>
.gift-reception-page { padding: 20px; }
.muted { color: #718096; font-size: 0.85rem; margin: 0; }
.flash { padding: 10px 14px; border-radius: 8px; margin: 12px 0; }
.flash.success { background: #f0fff4; color: #276749; border: 1px solid #9ae6b4; }
.flash.error { background: #fff5f5; color: #9b2c2c; border: 1px solid #feb2b2; }
.grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(280px, 100%), 1fr)); gap: 16px; margin-top: 12px; }
.card-form { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; display: flex; flex-direction: column; gap: 6px; }
.card-form h3 { margin: 0 0 6px; font-size: 1rem; }
.card-form input, .card-form select { padding: 8px; border: 1px solid #cbd5e0; border-radius: 6px; }
.result { margin-top: 8px; padding: 10px; background: #f7fafc; border-radius: 8px; line-height: 1.9; }
.code { font-family: monospace; direction: ltr; display: inline-block; }
.ok { color: #276749; font-weight: 600; }
.off { color: #c53030; font-weight: 600; }
</style>
