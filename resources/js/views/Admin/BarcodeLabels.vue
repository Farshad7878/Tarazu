<template>
    <div class="labels-page">
        <div class="page-header bl-no-print">
            <div>
                <h2><IconTag class="emo-icon" aria-hidden="true" /> چاپ برچسب بارکد</h2>
                <p class="muted">برای هر کالا تعداد برچسب را مشخص کنید؛ فقط کالاهایی که بارکد دارند چاپ می‌شوند.</p>
            </div>
            <button class="btn-primary" :disabled="!printable.length" @click="printLabels"><IconPrinter class="emo-icon" aria-hidden="true" /> چاپ ({{ printable.length }} برچسب)</button>
        </div>

        <div v-if="message" :class="['flash', messageType, 'bl-no-print']">{{ message }}</div>

        <div class="layout bl-no-print">
            <div class="panel">
                <h3>افزودن کالا</h3>
                <div class="search-row">
                    <input v-model="query" placeholder="نام، کد انبار یا بارکد" @keyup.enter="search">
                    <button class="btn-small" @click="search">جستجو</button>
                </div>
                <div v-if="results.length" class="results">
                    <div v-for="p in results" :key="p.id" class="result-row">
                        <span>
                            {{ p.name }}
                            <small class="muted">{{ p.barcode || 'بدون بارکد' }}</small>
                        </span>
                        <button class="btn-small" @click="add(p)">افزودن</button>
                    </div>
                </div>
            </div>

            <div class="panel">
                <h3>برچسب‌های چاپ</h3>
                <div class="table-scroll" v-if="items.length"><table class="data-table">
                    <thead>
                        <tr><th>کالا</th><th>بارکد</th><th>قیمت</th><th>تعداد</th><th></th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, index) in items" :key="item.id">
                            <td>{{ item.name }}</td>
                            <td>
                                <span v-if="item.barcode" class="code">{{ item.barcode }}</span>
                                <button v-else class="btn-small" :disabled="busyId === item.id" @click="makeBarcode(item)">ساخت بارکد</button>
                            </td>
                            <td>{{ money(item.price) }}</td>
                            <td><input v-model.number="item.qty" type="number" min="1" max="500" step="1" class="qty"></td>
                            <td><button class="btn-small btn-danger" @click="items.splice(index, 1)">×</button></td>
                        </tr>
                    </tbody>
                </table></div>
                <div v-else class="empty">کالایی انتخاب نشده است.</div>
            </div>
        </div>

        <!-- ناحیه‌ی چاپ: فقط هنگام چاپ نمایش داده می‌شود -->
        <div class="bl-print-area">
            <div class="label-sheet">
                <div v-for="label in printable" :key="label.key" class="bl-label">
                    <div class="label-name">{{ label.name }}</div>
                    <div class="label-price">{{ money(label.price) }}</div>
                    <div class="label-code" v-html="label.svg"></div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { Printer as IconPrinter, Tag as IconTag } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import axios from 'axios';
import { ean13Svg, isValidEan13 } from '../../utils/ean13';

export default {
    components: { IconPrinter, IconTag },
    name: 'BarcodeLabels',
    setup() {
        const query = ref('');
        const results = ref([]);
        const items = ref([]);
        const busyId = ref(null);
        const message = ref('');
        const messageType = ref('success');

        const money = (value) => new Intl.NumberFormat('fa-IR').format(Number(value) || 0) + ' تومان';

        const flash = (text, type = 'success') => {
            message.value = text;
            messageType.value = type;
            setTimeout(() => {
                if (message.value === text) message.value = '';
            }, 5000);
        };

        const search = async () => {
            const q = query.value.trim();
            if (!q) return;
            try {
                const response = await axios.get('/api/admin/products', { params: { search: q } });
                results.value = response.data.products;
            } catch {
                flash('جستجوی کالا انجام نشد', 'error');
            }
        };

        const add = (product) => {
            if (items.value.some((i) => i.id === product.id)) {
                flash('این کالا قبلاً در لیست است.', 'error');
                return;
            }
            items.value.push({
                id: product.id,
                name: product.name,
                price: product.price,
                barcode: product.barcode || null,
                qty: 1,
            });
        };

        const makeBarcode = async (item) => {
            busyId.value = item.id;
            try {
                const response = await axios.post(`/api/admin/products/${item.id}/barcode`);
                item.barcode = response.data.barcode;
                flash(`بارکد برای «${item.name}» ساخته شد.`);
            } catch {
                flash('ساخت بارکد انجام نشد', 'error');
            } finally {
                busyId.value = null;
            }
        };

        // هر برچسب یک آیتم جدا می‌شود (به تعداد درخواستی)
        const printable = computed(() => {
            const list = [];
            items.value.forEach((item) => {
                if (!isValidEan13(item.barcode)) return;
                const svg = ean13Svg(item.barcode, { moduleWidth: 1.3, barHeight: 42, fontSize: 9 });
                const count = Math.min(Math.max(Number(item.qty) || 0, 0), 500);
                for (let n = 0; n < count; n++) {
                    list.push({ key: `${item.id}-${n}`, name: item.name, price: item.price, svg });
                }
            });
            return list;
        });

        const printLabels = () => {
            window.print();
        };

        return { query, results, items, busyId, message, messageType, printable, money, search, add, makeBarcode, printLabels };
    },
};
</script>

<style scoped>
.labels-page { padding: 24px; }
.muted { color: #718096; font-size: 0.85rem; }
.layout { display: grid; grid-template-columns: minmax(min(260px, 100%), 1fr) minmax(320px, 1.4fr); gap: 16px; }
@media (max-width: 900px) { .layout { grid-template-columns: 1fr; } }
.panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; }
.panel h3 { margin: 0 0 10px; font-size: 1rem; }
.search-row { display: flex; gap: 6px; margin-bottom: 8px; }
.search-row input, .qty { padding: 6px; border: 1px solid #cbd5e0; border-radius: 6px; }
.search-row input { flex: 1; }
.qty { width: 70px; }
.results { display: flex; flex-direction: column; gap: 6px; max-height: 420px; overflow-y: auto; }
.result-row { display: flex; justify-content: space-between; align-items: center; gap: 8px; padding: 6px 8px; border: 1px solid #edf2f7; border-radius: 6px; }
.data-table { width: 100%; border-collapse: collapse; font-size: 0.86rem; }
.data-table th, .data-table td { padding: 7px; border-bottom: 1px solid #edf2f7; text-align: right; }
.code { font-family: monospace; direction: ltr; }
.flash { padding: 10px 14px; border-radius: 8px; margin-bottom: 12px; }
.flash.success { background: #f0fff4; color: #276749; border: 1px solid #9ae6b4; }
.flash.error { background: #fff5f5; color: #9b2c2c; border: 1px solid #feb2b2; }
.empty { padding: 20px; text-align: center; color: #718096; }
/* ناحیه‌ی چاپ روی صفحه پنهان است */
.bl-print-area { display: none; }
</style>

<style>
@media print {
    body * { visibility: hidden !important; }
    .bl-print-area, .bl-print-area * { visibility: visible !important; }
    .bl-print-area { display: block !important; position: absolute; left: 0; top: 0; width: 100%; }
    .bl-no-print { display: none !important; }
    .bl-print-area .label-sheet { display: grid; grid-template-columns: repeat(3, 1fr); gap: 3mm; }
    .bl-print-area .bl-label { border: 0.2mm dashed #999; padding: 2mm; text-align: center; page-break-inside: avoid; font-family: Tahoma, sans-serif; }
    .bl-print-area .label-name { font-size: 9pt; font-weight: 600; }
    .bl-print-area .label-price { font-size: 8pt; margin-bottom: 1mm; }
    .bl-print-area svg { max-width: 100%; height: auto; }
    @page { margin: 8mm; }
}
</style>
