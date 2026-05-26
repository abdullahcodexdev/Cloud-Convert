const CACHE_NAME = "fluxconvert-static-v1";
const STATIC_PREFIXES = ["/vendor/", "/css/", "/js/", "/img/"];

self.addEventListener("install", (event) => {
    event.waitUntil(self.skipWaiting());
});

self.addEventListener("activate", (event) => {
    event.waitUntil((async () => {
        const keys = await caches.keys();
        await Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key)));
        await self.clients.claim();
    })());
});

self.addEventListener("fetch", (event) => {
    const request = event.request;
    if (request.method !== "GET") {
        return;
    }

    const url = new URL(request.url);
    if (url.origin !== self.location.origin || !STATIC_PREFIXES.some((prefix) => url.pathname.startsWith(prefix))) {
        return;
    }

    event.respondWith((async () => {
        const cache = await caches.open(CACHE_NAME);
        const cached = await cache.match(request, { ignoreSearch: true });
        if (cached) {
            return cached;
        }

        const response = await fetch(request);
        if (response.ok) {
            await cache.put(request, response.clone());
        }

        return response;
    })());
});
