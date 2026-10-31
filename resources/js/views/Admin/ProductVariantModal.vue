<template>
    <div class="modal-overlay" @click.self="$emit('close')">
        <div class="modal">
            <div class="modal-header">
                <h3>🧩 نسخه‌ی جدید برای «{{ product.name }}»</h3>
                <button class="close-btn" @click="$emit('close')">×</button>
            </div>
            <form @submit.prevent="submit">
                <div class="modal-body">
                    <p class="muted">نسخه‌ها (مثلاً سایز یا رنگ) جداگانه موجودی و بارکد دارند. قیمت را اگر خالی بگذارید، قیمت محصول اصلی استفاده می‌شود.</p>
                    <label>عنوان نسخه (مثلاً M، قرمز) *</label>
                    <input v-model="form.variant_label" required maxlength="100" placeholder="M">
                    <label>موجودی اولیه</label>
                    <input v-model.number="form.stock_quantity" type="number" min="0" step="1">
                    <label>قیمت (اختیاری)</label>
                    <input v-model.number="form.price" type="number" min="0" step="1" placeholder="همان قیمت محصول">
                    <label>بارکد (اختیاری، خالی = ساخت خودکار)</label>
                    <input v-model="form.barcode" maxlength="100" class="code">
                    <div v-if="error" class="error">{{ error }}</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" @click="$emit('close')">انصراف</button>
                    <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'در حال ذخیره...' : 'ایجاد نسخه' }}</button>
                </div>
            </form>
        </div>
    </div>
</template>

<script>
import { ref } from 'vue';
import axios from 'axios';

export default {
    name: 'ProductVariantModal',
    props: {
        product: { type: Object, required: true },
    },
    emits: ['close', 'saved'],
    setup(props, { emit }) {
        const form = ref({ variant_label: '', stock_quantity: 0, price: null, barcode: '' });
        const saving = ref(false);
        const error = ref('');

        const submit = async () => {
            saving.value = true;
            error.value = '';
            const payload = {
                variant_label: form.value.variant_label.trim(),
                stock_quantity: Number(form.value.stock_quantity) || 0,
                barcode: form.value.barcode.trim() || null,
            };
            if (form.value.price !== null && form.value.price !== '') {
                payload.price = Number(form.value.price);
            }
            try {
                await axios.post(`/api/admin/products/${props.product.id}/variants`, payload);
                emit('saved');
                emit('close');
            } catch (err) {
                const first = Object.values(err.response?.data?.errors || {}).flat()[0];
                error.value = err.response?.data?.message || first || 'ایجاد نسخه انجام نشد';
            } finally {
                saving.value = false;
            }
        };

        return { form, saving, error, submit };
    },
};
</script>

<style scoped>
.modal-overlay { position: fixed; inset: 0; background: rgba(0, 0, 0, 0.4); display: flex; align-items: center; justify-content: center; z-index: 60; padding: 16px; }
.modal { background: #fff; border-radius: 12px; width: min(460px, 100%); }
.modal-header, .modal-footer { display: flex; justify-content: space-between; align-items: center; padding: 14px 18px; border-bottom: 1px solid #edf2f7; }
.modal-footer { border-top: 1px solid #edf2f7; border-bottom: none; justify-content: flex-end; gap: 8px; }
.modal-header h3 { margin: 0; font-size: 1rem; }
.modal-body { padding: 14px 18px; display: flex; flex-direction: column; gap: 6px; }
.modal-body input { padding: 8px; border: 1px solid #cbd5e0; border-radius: 6px; }
.code { direction: ltr; }
.muted { color: #718096; font-size: 0.85rem; margin: 0 0 6px; }
.error { color: #c53030; font-size: 0.88rem; }
.close-btn { background: none; border: none; font-size: 1.4rem; cursor: pointer; }
.btn-primary { padding: 8px 14px; background: #2b6cb0; color: #fff; border: none; border-radius: 8px; cursor: pointer; }
.btn-primary:disabled { opacity: 0.5; }
.btn-cancel { padding: 8px 14px; background: #fff; border: 1px solid #cbd5e0; border-radius: 8px; cursor: pointer; }
</style>
