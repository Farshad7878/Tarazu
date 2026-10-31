<template>
    <div class="modal-overlay" @click.self="closeIfIdle">
        <div class="modal modal-large">
            <div class="modal-header">
                <h3>🔄 تعویض کالا — سفارش #{{ order.order_number }}</h3>
                <button type="button" class="close-btn" :disabled="submitting" @click="closeIfIdle">×</button>
            </div>

            <div class="modal-content">
                <p class="hint">
                    کالاهای انتخابی برگردانده می‌شوند و کالاهای جدید به‌عنوان سفارش تازه ثبت می‌شوند.
                    تفاوت قیمت حساب می‌شود.
                </p>

                <!-- کالاهای برگشتی -->
                <section class="block">
                    <h4>↩️ کالاهای برگشتی از سفارش</h4>
                    <p v-if="returnableItems.length === 0" class="hint">کالای قابل بازگشتی در این سفارش نیست.</p>
                    <div v-for="item in returnableItems" :key="item.id" class="line">
                        <div class="line-info">
                            <span class="name">{{ item.product_name }}</span>
                            <span class="meta">
                                قیمت واحد: {{ formatPrice(item.unit_price) }} — قابل بازگشت: {{ formatPrice(item.returnable_quantity) }} عدد
                            </span>
                        </div>
                        <label class="qty">
                            تعداد:
                            <input
                                :id="`exchange-return-${item.id}`"
                                type="number"
                                min="0"
                                :max="item.returnable_quantity"
                                v-model.number="returnQty[item.id]"
                                :disabled="submitting"
                            />
                        </label>
                    </div>
                    <p v-if="hasInvalidReturn" class="error-text">
                        تعداد هر کالا باید عدد صحیحی بین ۰ و باقی‌مانده‌ی قابل بازگشت باشد.
                    </p>
                </section>

                <!-- کالاهای جدید -->
                <section class="block">
                    <h4>🛒 کالاهای جدید</h4>
                    <input
                        v-model="productSearch"
                        type="text"
                        class="search"
                        placeholder="جستجوی نام کالا"
                        :disabled="submitting"
                    />
                    <p v-if="productsLoading" class="hint">در حال بارگذاری کالاها...</p>
                    <p v-if="productsError" class="error-text">{{ productsError }}</p>

                    <div class="product-grid">
                        <button
                            v-for="product in filteredProducts"
                            :key="product.id"
                            type="button"
                            class="product-chip"
                            :disabled="submitting"
                            @click="addProduct(product)"
                        >
                            <span>{{ product.name }}</span>
                            <small>{{ formatPrice(product.price) }} — موجودی {{ formatPrice(product.stock_quantity) }}</small>
                        </button>
                    </div>

                    <div v-if="newLines.length" class="new-lines">
                        <div v-for="line in newLines" :key="line.product_id" class="line">
                            <div class="line-info">
                                <span class="name">{{ line.name }}</span>
                                <span class="meta">{{ formatPrice(line.price) }} × {{ formatPrice(Number(line.quantity) || 0) }}</span>
                            </div>
                            <label class="qty">
                                تعداد:
                                <input
                                    type="number"
                                    min="1"
                                    :max="line.stock"
                                    v-model.number="line.quantity"
                                    :disabled="submitting"
                                />
                            </label>
                            <button type="button" class="remove" :disabled="submitting" @click="removeLine(line.product_id)">✕</button>
                        </div>
                    </div>
                    <p v-if="hasInvalidNew" class="error-text">تعداد هر کالای جدید باید بین ۱ و موجودی باشد.</p>
                </section>

                <!-- دلیل و پرداخت -->
                <section class="block">
                    <div class="form-row">
                        <label for="exchange-reason">دلیل تعویض <span class="required">*</span></label>
                        <input id="exchange-reason" v-model="reason" type="text" maxlength="255" :disabled="submitting" placeholder="مثلاً: سایز نامناسب" />
                    </div>
                    <div class="form-row">
                        <label for="exchange-payment">روش پرداخت مابه‌التفاوت</label>
                        <select id="exchange-payment" v-model="paymentMethod" :disabled="submitting">
                            <option value="cash">نقد</option>
                            <option value="card">کارت</option>
                        </select>
                    </div>
                    <label class="restock-toggle">
                        <input type="checkbox" v-model="restock" :disabled="submitting" />
                        کالاهای برگشتی به موجودی انبار برگردند
                    </label>
                </section>

                <!-- خلاصه -->
                <section class="block summary">
                    <div class="summary-row"><span>ارزش کالاهای برگشتی (تقریبی):</span><span>{{ formatPrice(Math.round(refundEstimate)) }}</span></div>
                    <div class="summary-row"><span>ارزش کالاهای جدید:</span><span>{{ formatPrice(newTotal) }}</span></div>
                    <div class="summary-row total">
                        <template v-if="difference > 0">
                            <span>مشتری باید پرداخت کند:</span>
                            <span>{{ formatPrice(Math.round(difference)) }}</span>
                        </template>
                        <template v-else-if="difference < 0">
                            <span>به مشتری بازپرداخت شود:</span>
                            <span>{{ formatPrice(Math.round(-difference)) }}</span>
                        </template>
                        <template v-else>
                            <span>پرداخت اضافی ندارد</span>
                            <span>۰</span>
                        </template>
                    </div>
                    <p class="hint">مبلغ قطعی را سرور هنگام ثبت محاسبه می‌کند. تخفیف و امتیاز روی کالاهای جدید اعمال نمی‌شود.</p>
                    <p v-if="submitError" class="error-text">{{ submitError }}</p>
                </section>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-primary" :disabled="!canSubmit" @click="submit">
                    {{ submitting ? 'در حال ثبت...' : 'ثبت تعویض کالا' }}
                </button>
                <button type="button" class="btn-secondary" :disabled="submitting" @click="closeIfIdle">انصراف</button>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, reactive, computed, onMounted } from 'vue';
import axios from 'axios';
import { formatPrice } from '../../utils/format';

function firstError(error, fallback) {
    const errors = error.response?.data?.errors;
    if (errors) {
        const first = Object.values(errors).flat()[0];
        if (first) return first;
    }
    return error.response?.data?.message || fallback;
}

export default {
    name: 'ExchangeModal',
    props: {
        order: { type: Object, required: true },
    },
    emits: ['close', 'done'],
    setup(props, { emit }) {
        const products = ref([]);
        const productsLoading = ref(false);
        const productsError = ref('');
        const productSearch = ref('');
        const returnQty = reactive({});
        const newLines = ref([]);
        const reason = ref('');
        const restock = ref(true);
        const paymentMethod = ref('cash');
        const submitting = ref(false);
        const submitError = ref('');

        const returnableItems = computed(() =>
            (props.order.items || []).filter((item) => item.returnable_quantity > 0)
        );

        const hasInvalidReturn = computed(() =>
            returnableItems.value.some((item) => {
                const qty = Number(returnQty[item.id]) || 0;
                return !Number.isInteger(qty) || qty < 0 || qty > item.returnable_quantity;
            })
        );

        const selectedReturnLines = computed(() =>
            returnableItems.value
                .map((item) => ({ order_item_id: item.id, quantity: Number(returnQty[item.id]) || 0 }))
                .filter((line) => line.quantity > 0)
        );

        // تخمین سمت کلاینت؛ مبلغ قطعی را سرور حساب می‌کند
        const refundEstimate = computed(() => {
            const order = props.order;
            const ratio = order.subtotal > 0 ? order.discount_amount / order.subtotal : 0;
            return returnableItems.value.reduce((sum, item) => {
                const qty = Math.min(Math.max(Number(returnQty[item.id]) || 0, 0), item.returnable_quantity);
                return sum + item.unit_price * qty * (1 - ratio);
            }, 0);
        });

        const isInvalidNewLine = (line) => {
            const qty = Number(line.quantity);
            return !Number.isInteger(qty) || qty < 1 || qty > line.stock;
        };

        const hasInvalidNew = computed(() => newLines.value.some(isInvalidNewLine));

        const newTotal = computed(() =>
            newLines.value.reduce((sum, line) => sum + line.price * (Number(line.quantity) || 0), 0)
        );

        const difference = computed(() => newTotal.value - refundEstimate.value);

        const canSubmit = computed(() =>
            !submitting.value
            && selectedReturnLines.value.length > 0
            && !hasInvalidReturn.value
            && newLines.value.length > 0
            && !hasInvalidNew.value
            && reason.value.trim().length > 0
        );

        const filteredProducts = computed(() => {
            const term = productSearch.value.trim().toLowerCase();
            return products.value.filter((product) =>
                product.stock_quantity > 0 && (!term || product.name.toLowerCase().includes(term))
            );
        });

        const loadProducts = async () => {
            productsLoading.value = true;
            productsError.value = '';
            try {
                const response = await axios.get('/api/reception/products');
                products.value = response.data.products || [];
            } catch (error) {
                console.error('Error loading products for exchange:', error);
                productsError.value = 'فهرست کالاها بارگذاری نشد. اتصال اینترنت را بررسی کنید.';
            } finally {
                productsLoading.value = false;
            }
        };

        const addProduct = (product) => {
            const line = newLines.value.find((item) => item.product_id === product.id);
            if (line) {
                if (Number(line.quantity) < line.stock) line.quantity = Number(line.quantity) + 1;
                return;
            }
            newLines.value.push({
                product_id: product.id,
                name: product.name,
                price: Number(product.price) || 0,
                stock: Number(product.stock_quantity) || 0,
                quantity: 1,
            });
        };

        const removeLine = (productId) => {
            newLines.value = newLines.value.filter((line) => line.product_id !== productId);
        };

        const closeIfIdle = () => {
            if (!submitting.value) emit('close');
        };

        const submit = async () => {
            if (!canSubmit.value) return;

            submitting.value = true;
            submitError.value = '';
            try {
                const response = await axios.post(`/api/reception/orders/${props.order.id}/exchanges`, {
                    return_items: selectedReturnLines.value,
                    new_items: newLines.value.map((line) => ({
                        product_id: line.product_id,
                        quantity: Number(line.quantity),
                    })),
                    reason: reason.value.trim(),
                    restock: restock.value,
                    payment_method: paymentMethod.value,
                });
                emit('done', response.data);
            } catch (error) {
                console.error('Error submitting exchange:', error);
                submitError.value = firstError(error, 'ثبت تعویض کالا انجام نشد');
            } finally {
                submitting.value = false;
            }
        };

        onMounted(() => {
            returnableItems.value.forEach((item) => {
                returnQty[item.id] = 0;
            });
            loadProducts();
        });

        return {
            products,
            productsLoading,
            productsError,
            productSearch,
            returnQty,
            newLines,
            reason,
            restock,
            paymentMethod,
            submitting,
            submitError,
            returnableItems,
            hasInvalidReturn,
            hasInvalidNew,
            refundEstimate,
            newTotal,
            difference,
            canSubmit,
            filteredProducts,
            addProduct,
            removeLine,
            closeIfIdle,
            submit,
            formatPrice,
        };
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
    padding: 12px;
}

.modal {
    background: #fff;
    border-radius: 12px;
    width: 100%;
    max-height: 92vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.modal-large { max-width: 760px; }

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
    gap: 8px;
}

.modal-header h3 { margin: 0; font-size: 1.1rem; }

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

.block {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 12px;
    margin-bottom: 12px;
}

.block h4 { margin: 0 0 8px; font-size: 0.98rem; }

.hint { color: #64748b; font-size: 0.88rem; margin: 6px 0; }

.error-text { color: #b91c1c; font-size: 0.88rem; margin: 6px 0 0; }

.line {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
    padding: 6px 0;
    border-bottom: 1px dashed #e2e8f0;
}

.line:last-child { border-bottom: none; }

.line-info { display: flex; flex-direction: column; }
.name { font-weight: 600; }
.meta { color: #64748b; font-size: 0.85rem; }

.qty input,
.search,
.form-row input,
.form-row select {
    padding: 6px 8px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
}

.qty input { width: 80px; margin-right: 6px; }
.search { width: 100%; margin-bottom: 8px; }

.product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 8px;
    max-height: 200px;
    overflow-y: auto;
    margin-bottom: 8px;
}

.product-chip {
    text-align: right;
    border: 1px solid #cbd5e1;
    background: #f8fafc;
    border-radius: 8px;
    padding: 8px;
    cursor: pointer;
    display: flex;
    flex-direction: column;
}

.product-chip small { color: #64748b; margin-top: 2px; }

.new-lines { margin-top: 8px; }

.remove {
    background: none;
    border: 1px solid #fca5a5;
    color: #b91c1c;
    border-radius: 6px;
    cursor: pointer;
    padding: 2px 8px;
}

.form-row {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-bottom: 8px;
}

.required { color: #b91c1c; }

.restock-toggle { display: flex; align-items: center; gap: 6px; font-size: 0.9rem; }

.summary-row {
    display: flex;
    justify-content: space-between;
    padding: 4px 0;
}

.summary-row.total {
    border-top: 2px solid #e2e8f0;
    margin-top: 4px;
    padding-top: 8px;
    font-weight: 700;
}

.btn-primary,
.btn-secondary {
    border: none;
    border-radius: 8px;
    padding: 9px 16px;
    cursor: pointer;
    color: #fff;
}

.btn-primary { background: #2563eb; }
.btn-secondary { background: #64748b; }

button:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
