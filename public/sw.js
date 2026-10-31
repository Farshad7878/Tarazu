/*
 * Service worker سیستم فروشگاه (حالت آفلاین محدود)
 *
 * - پوسته‌ی برنامه (HTML) با شبکه‌ی اول ذخیره می‌شود؛ وقتی اینترنت نیست همان پوسته باز می‌شود.
 * - فایل‌های نسخه‌دار /build و /icons با کش اول (cache-first) ذخیره می‌شوند.
 * - داده‌ها (منو، موجودی، صف سفارش) در localStorage صفحه نگهداری می‌شوند؛ این فایل به API دست نمی‌زند.
 * - درخواست‌های POST هرگز کش یا بازپخش نمی‌شوند؛ ثبت سفارش آفلاین در صف محلی انجام می‌شود.
 */
const SHELL_CACHE = 'bms-shell-v1';
const ASSET_CACHE = 'bms-assets-v1';
const KEEP = [SHELL_CACHE, ASSET_CACHE];
const SHELL_URL = '/reception/new-order';

self.addEventListener('install', (event) => {
    event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k.startsWith('bms-') && !KEEP.includes(k)).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(networkFirstShell(request));
        return;
    }

    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/') || url.pathname === '/manifest.webmanifest') {
        event.respondWith(cacheFirst(request));
    }
});

async function networkFirstShell(request) {
    const cache = await caches.open(SHELL_CACHE);
    try {
        const response = await fetch(request);
        if (response.ok) {
            cache.put(SHELL_URL, response.clone());
        }
        return response;
    } catch (error) {
        const cached = (await cache.match(SHELL_URL)) || (await cache.match('/login'));
        if (cached) {
            return cached;
        }
        throw error;
    }
}

async function cacheFirst(request) {
    const cache = await caches.open(ASSET_CACHE);
    const cached = await cache.match(request);
    if (cached) {
        return cached;
    }
    const response = await fetch(request);
    if (response.ok) {
        cache.put(request, response.clone());
    }
    return response;
}
