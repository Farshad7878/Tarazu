/**
 * قالب‌بندی مشترک اعداد و تاریخ برای نمایش فارسی.
 */

/** عدد را با جداکننده‌ی فارسی نمایش می‌دهد؛ مقدار خالی به «۰» تبدیل می‌شود. */
export const formatPrice = (price) => new Intl.NumberFormat('fa-IR').format(price || 0);

/**
 * مالیات موجود در مبلغ «شامل مالیات» (همان فرمول TaxService در بک‌اند):
 * مبلغ × نرخ ÷ (۱۰۰ + نرخ)، گرد شده به دو رقم اعشار. نرخ ۰ یعنی بدون مالیات.
 */
export const vatIncluded = (amount, rate) => {
    const value = Math.max(0, Number(amount) || 0);
    const percent = Number(rate) || 0;
    if (percent <= 0 || percent >= 100) return 0;
    return Math.round((value * percent / (100 + percent)) * 100) / 100;
};

/** تاریخ شمسی/فارسی؛ مقدار خالی به «--» تبدیل می‌شود. */
export const formatDate = (dateString) => {
    if (!dateString) return '--';
    return new Date(dateString).toLocaleDateString('fa-IR');
};
