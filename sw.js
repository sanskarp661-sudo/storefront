/*
 * Mosaic Store service worker: makes the store installable and shows a friendly
 * offline page. Pages, the bag and checkout are always fetched from the network
 * (never cached), so prices, stock and the session stay live.
 */
const VERSION = "v1";
const STATIC_CACHE = "mosaic-static-" + VERSION;
const OFFLINE_URL = "offline.html";
const PRECACHE = [OFFLINE_URL, "assets/icons/icon-192.png", "manifest.webmanifest"];

self.addEventListener("install", (event) => {
  event.waitUntil(caches.open(STATIC_CACHE).then((c) => c.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k.startsWith("mosaic-") && k !== STATIC_CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener("fetch", (event) => {
  const req = event.request;
  if (req.method !== "GET") return;
  const url = new URL(req.url);

  // Page loads: network only, with the offline page as the fallback.
  if (req.mode === "navigate") {
    event.respondWith(fetch(req).catch(() => caches.match(OFFLINE_URL)));
    return;
  }

  // Our CSS, JS and icons: serve from cache, refresh in the background.
  if (url.origin === self.location.origin && url.pathname.includes("/assets/")) {
    event.respondWith(
      caches.open(STATIC_CACHE).then((cache) =>
        cache.match(req).then((hit) => {
          const fresh = fetch(req).then((res) => { if (res.ok) cache.put(req, res.clone()); return res; }).catch(() => hit);
          return hit || fresh;
        })
      )
    );
  }
});
