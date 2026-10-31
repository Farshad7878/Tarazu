<template>
    <div class="modal-overlay" @click.self="$emit('close')">
        <div class="modal modal-medium">
            <div class="modal-header">
                <h3>⏸ سبدهای معلق</h3>
                <button type="button" class="close-btn" @click="$emit('close')">×</button>
            </div>

            <div class="modal-content">
                <p v-if="loading" class="hint">در حال بارگذاری...</p>
                <p v-else-if="carts.length === 0" class="hint">سبد معلقی وجود ندارد.</p>

                <div v-for="cart in carts" :key="cart.id" class="held-card">
                    <div class="held-head">
                        <strong>{{ cart.customer ? cart.customer.name : 'بدون مشتری' }}</strong>
                        <span class="held-meta">{{ cart.created_at }} — {{ formatPrice(cart.items_count) }} عدد</span>
                    </div>

                    <ul class="held-lines">
                        <li v-for="line in cart.lines" :key="line.product_id" :class="{ unavailable: !line.available }">
                            {{ line.name }} × {{ formatPrice(line.quantity) }}
                            <span v-if="!line.available" class="badge">ناموجود یا غیرفعال</span>
                        </li>
                    </ul>

                    <p v-if="cart.notes" class="held-notes">📝 {{ cart.notes }}</p>

                    <div class="held-actions">
                        <button type="button" class="btn-primary" :disabled="busy" @click="$emit('resume', cart.id)">
                            ▶️ بازیابی
                        </button>
                        <button type="button" class="btn-danger" :disabled="busy" @click="$emit('discard', cart.id)">
                            🗑️ حذف
                        </button>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-secondary" @click="$emit('close')">بستن</button>
            </div>
        </div>
    </div>
</template>

<script>
import { formatPrice } from '../../utils/format';

export default {
    name: 'HeldCartsModal',
    props: {
        carts: { type: Array, default: () => [] },
        loading: { type: Boolean, default: false },
        busy: { type: Boolean, default: false },
    },
    emits: ['resume', 'discard', 'close'],
    setup() {
        return { formatPrice };
    },
};
</script>

<style scoped>
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    padding: 16px;
}

.modal {
    background: #fff;
    border-radius: 12px;
    width: 100%;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.modal-medium {
    max-width: 560px;
}

.modal-header,
.modal-footer {
    padding: 14px 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #e2e8f0;
}

.modal-footer {
    border-top: 1px solid #e2e8f0;
    border-bottom: none;
    justify-content: flex-end;
}

.modal-header h3 {
    margin: 0;
    font-size: 1.1rem;
}

.close-btn {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    line-height: 1;
}

.modal-content {
    padding: 14px 18px;
    overflow-y: auto;
}

.hint {
    color: #64748b;
    text-align: center;
    margin: 16px 0;
}

.held-card {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 12px;
    margin-bottom: 12px;
}

.held-head {
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 6px;
}

.held-meta {
    color: #64748b;
    font-size: 0.85rem;
}

.held-lines {
    margin: 8px 0;
    padding-right: 18px;
    font-size: 0.92rem;
}

.held-lines li.unavailable {
    color: #b45309;
}

.badge {
    margin-right: 6px;
    background: #fef3c7;
    color: #92400e;
    border-radius: 6px;
    padding: 1px 6px;
    font-size: 0.75rem;
}

.held-notes {
    font-size: 0.88rem;
    color: #334155;
    margin: 4px 0 8px;
}

.held-actions {
    display: flex;
    gap: 8px;
}

.btn-primary,
.btn-danger,
.btn-secondary {
    border: none;
    border-radius: 8px;
    padding: 8px 14px;
    cursor: pointer;
    color: #fff;
}

.btn-primary { background: #2563eb; }
.btn-danger { background: #dc2626; }
.btn-secondary { background: #64748b; }

button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
</style>
