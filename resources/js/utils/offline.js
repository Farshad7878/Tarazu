// ذخیره‌ی محلی برای حالت آفلاین: صف سفارش‌ها، کش منو/موجودی و کش مشتری‌های اخیر.
// همه‌چیز در localStorage است و با کلیدهای پیشوندی نگهداری می‌شود.

const QUEUE_KEY = 'bms_offline_orders';
const PRODUCTS_KEY = 'bms_cached_products';
const CUSTOMERS_KEY = 'bms_cached_customers';
const MAX_CUSTOMERS = 200;

function read(key, fallback) {
    try {
        const raw = localStorage.getItem(key);
        return raw ? JSON.parse(raw) : fallback;
    } catch {
        return fallback;
    }
}

function write(key, value) {
    try {
        localStorage.setItem(key, JSON.stringify(value));
        return true;
    } catch {
        // حافظه‌ی مرورگر پر است
        return false;
    }
}

export function newClientUuid() {
    if (globalThis.crypto?.randomUUID) {
        return globalThis.crypto.randomUUID();
    }
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
    });
}

// ---- صف سفارش‌ها ----

export function getQueue() {
    return read(QUEUE_KEY, []);
}

export function enqueueOrder(payload, meta = {}) {
    const queue = getQueue();
    const entry = {
        client_uuid: payload.client_uuid,
        payload,
        queued_at: new Date().toISOString(),
        attempts: 0,
        last_error: null,
        // هشدار موجودی بر اساس کش؛ سرور هنگام همگام‌سازی دوباره بررسی می‌کند
        stock_warnings: meta.stock_warnings || [],
    };
    queue.push(entry);
    write(QUEUE_KEY, queue);
    return entry;
}

export function updateQueueEntry(clientUuid, patch) {
    const queue = getQueue().map((entry) => (entry.client_uuid === clientUuid ? { ...entry, ...patch } : entry));
    write(QUEUE_KEY, queue);
}

export function removeQueueEntry(clientUuid) {
    write(QUEUE_KEY, getQueue().filter((entry) => entry.client_uuid !== clientUuid));
}

// ---- کش منو و موجودی ----

export function cacheProducts(products, categories, vatRate = 0) {
    write(PRODUCTS_KEY, { products, categories, vat_rate: Number(vatRate) || 0, cached_at: new Date().toISOString() });
}

export function readCachedProducts() {
    return read(PRODUCTS_KEY, null);
}

// ---- کش مشتری‌های اخیر (برای زمانی که اینترنت قطع است) ----

export function cacheCustomer(customer) {
    if (!customer?.membership_number) return;
    const all = read(CUSTOMERS_KEY, {});
    all[customer.membership_number] = { customer, cached_at: new Date().toISOString() };

    const keys = Object.keys(all);
    if (keys.length > MAX_CUSTOMERS) {
        keys
            .sort((a, b) => all[a].cached_at.localeCompare(all[b].cached_at))
            .slice(0, keys.length - MAX_CUSTOMERS)
            .forEach((key) => delete all[key]);
    }
    write(CUSTOMERS_KEY, all);
}

export function readCachedCustomer(membershipNumber) {
    return read(CUSTOMERS_KEY, {})[membershipNumber]?.customer ?? null;
}

// کش‌های سمت مرورگر (Cache Storage) که service worker ساخته است
export async function clearOfflineCache() {
    try {
        localStorage.removeItem(PRODUCTS_KEY);
        localStorage.removeItem(CUSTOMERS_KEY);
        if ('caches' in globalThis) {
            const keys = await caches.keys();
            await Promise.all(keys.filter((k) => k.startsWith('bms-')).map((k) => caches.delete(k)));
        }
    } catch (error) {
        console.error('Error clearing offline cache:', error);
    }
}

// آیا خطای axios به‌خاطر نبود اتصال است؟ (پاسخی از سرور نیامده)
export function isNetworkError(error) {
    return !error?.response && (error?.code === 'ERR_NETWORK' || error?.message === 'Network Error' || error instanceof TypeError || globalThis.navigator?.onLine === false);
}
