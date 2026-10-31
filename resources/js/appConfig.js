// نام برنامه: ابتدا از تنظیمات Laravel (APP_NAME) که در app.blade.php تزریق می‌شود،
// سپس از VITE_APP_NAME و در نهایت مقدار پیش‌فرض خوانده می‌شود.
export const appName =
    window.APP_CONFIG?.name ||
    import.meta.env.VITE_APP_NAME ||
    'ترازو';
