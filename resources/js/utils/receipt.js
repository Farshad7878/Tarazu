/**
 * رسید/فاکتور فروش برای چاپ.
 *
 * رسید در یک iframe مخفی ساخته می‌شود و با پنجره‌ی چاپ مرورگر چاپ می‌شود.
 * این روش نیاز به درایور خاص ندارد و با هر چاپگری که در سیستم نصب باشد کار می‌کند
 * (از جمله چاپگرهای حرارتی ۸۰ میلی‌متری با CSS @page).
 */
import { formatPrice } from './format';

const escapeHtml = (value) =>
    String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    }[char]));

const row = (label, value, extraClass = '') =>
    `<div class="row ${extraClass}"><span>${escapeHtml(label)}</span><span>${value}</span></div>`;

const PRINT_CSS = `
    @page { size: 80mm auto; margin: 3mm; }
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; background: #fff; color: #000; }
    body { width: 72mm; font-family: Tahoma, 'Vazirmatn', 'DejaVu Sans', sans-serif; font-size: 12px; line-height: 1.6; direction: rtl; }
    h1 { font-size: 16px; text-align: center; margin: 0 0 2px; }
    .sub { text-align: center; margin-bottom: 6px; }
    .dash { border-top: 1px dashed #000; margin: 6px 0; }
    .row { display: flex; justify-content: space-between; gap: 6px; }
    .row span:last-child { white-space: nowrap; direction: ltr; unicode-bidi: isolate; }
    .item { margin: 4px 0; }
    .item .name { font-weight: bold; }
    .total { font-size: 14px; font-weight: bold; }
    .muted { color: #333; font-size: 11px; }
    .center { text-align: center; }
    .notes { margin-top: 4px; white-space: pre-wrap; }
`;

/**
 * HTML کامل رسید را از داده‌ی سفارش (خروجی GET /api/reception/orders/{id}) می‌سازد.
 */
export function buildReceiptHtml(order, storeName = window.APP_CONFIG?.name || 'فروشگاه') {
    const items = (order.items || []).map((item) => `
        <div class="item">
            <div class="name">${escapeHtml(item.product_name)}</div>
            ${row(`${formatPrice(item.quantity)} × ${formatPrice(item.unit_price)}`, formatPrice(item.total_price))}
        </div>`).join('');

    const subtotal = Number(order.subtotal) || 0;
    const discount = Number(order.discount_amount) || 0;
    const refunded = Number(order.refunded_amount) || 0;
    const total = Number(order.total_amount) || 0;
    const giftCard = Number(order.gift_card_amount) || 0;
    const tax = Number(order.tax_amount) || 0;
    const taxRate = Number(order.tax_rate) || 0;
    const paymentLabel = order.payment_method === 'card' ? 'کارت' : 'نقد';

    const lines = [
        row('جمع اقلام', formatPrice(subtotal)),
        discount > 0 ? row('تخفیف و امتیاز', `-${formatPrice(discount)}`) : '',
        discount > 0 ? (order.applied_discounts || []).map((d) =>
            `<div class="muted">• ${escapeHtml(d.name)}${d.message ? ` (${escapeHtml(d.message)})` : ''}</div>`
        ).join('') : '',
    ].join('');

    // مبلغ خالص همیشه total_amount است (بازگشت‌ها آن را کم کرده‌اند)
    const totalBlock = refunded > 0
        ? row('مبلغ اولیه', formatPrice(subtotal - discount))
            + row('بازگشت کالا (ریفاند)', `-${formatPrice(refunded)}`)
            + `<div class="row total"><span>مبلغ خالص</span><span>${formatPrice(total)}</span></div>`
        : `<div class="row total"><span>مبلغ قابل پرداخت</span><span>${formatPrice(total)}</span></div>`;

    const payments = [
        giftCard > 0 ? row('پرداخت با کارت هدیه', formatPrice(giftCard)) : '',
        row(`پرداخت ${paymentLabel}`, formatPrice(Math.max(0, total - giftCard))),
    ].join('');

    const taxLine = tax > 0
        ? `<div class="muted center">مالیات ارزش افزوده${taxRate ? ` (${formatPrice(taxRate)}٪)` : ''} شامل قیمت: ${formatPrice(tax)}</div>`
        : '';

    const returnsLine = (order.returns || []).length > 0
        ? `<div class="muted center">این سفارش دارای ${formatPrice(order.returns.length)} مورد بازگشت کالا است.</div>`
        : '';

    return `<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>فاکتور ${escapeHtml(order.order_number)}</title>
    <style>${PRINT_CSS}</style>
</head>
<body>
    <h1>${escapeHtml(storeName)}</h1>
    <div class="sub">فاکتور فروش</div>

    ${row('شماره فاکتور', escapeHtml(order.order_number))}
    ${row('تاریخ', `${escapeHtml(order.created_date || '')} ${escapeHtml(order.created_time || '')}`)}
    ${row('ثبت‌کننده', escapeHtml(order.reception_name || '—'))}
    ${order.customer_name ? row('مشتری', escapeHtml(order.customer_name)) : ''}
    ${order.customer_membership ? row('شماره اشتراک', escapeHtml(order.customer_membership)) : ''}

    <div class="dash"></div>
    ${items}
    <div class="dash"></div>

    ${lines}
    <div class="dash"></div>
    ${totalBlock}
    ${payments}
    ${taxLine}
    ${returnsLine}

    ${order.notes ? `<div class="dash"></div><div class="notes">یادداشت: ${escapeHtml(order.notes)}</div>` : ''}

    <div class="dash"></div>
    <div class="center">با تشکر از خرید شما</div>
</body>
</html>`;
}

/**
 * رسید را در iframe مخفی باز کرده و پنجره‌ی چاپ را نشان می‌دهد.
 * iframe بعد از بسته‌شدن پنجره‌ی چاپ حذف می‌شود.
 */
export function printReceipt(order, storeName) {
    if (!order) return null;

    const html = buildReceiptHtml(order, storeName);

    const frame = document.createElement('iframe');
    frame.setAttribute('aria-hidden', 'true');
    frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden;';
    document.body.appendChild(frame);

    const win = frame.contentWindow;
    const doc = win.document;
    doc.open();
    doc.write(html);
    doc.close();

    let removed = false;
    const cleanup = () => {
        if (!removed) {
            removed = true;
            frame.remove();
        }
    };
    win.addEventListener('afterprint', cleanup);
    // پشتیبان: اگر رویداد afterprint نیامد، iframe بعد از مدت طولانی حذف شود
    setTimeout(cleanup, 120000);

    setTimeout(() => {
        win.focus();
        win.print();
    }, 150);

    return html;
}
