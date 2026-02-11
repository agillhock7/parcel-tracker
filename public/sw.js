/* Parcel Tracker Service Worker */
const SW_VERSION = "2026-02-11-v1";
const STATIC_CACHE = `pt-static-${SW_VERSION}`;
const RUNTIME_CACHE = `pt-runtime-${SW_VERSION}`;
const PAGE_CACHE = `pt-pages-${SW_VERSION}`;

const CORE_ASSETS = [
  "/",
  "/offline.html",
  "/site.webmanifest",
  "/assets/app.css",
  "/assets/app.js",
  "/assets/branding/parceltracker-logo-1024.png",
  "/assets/graphics/truck.svg",
  "/assets/graphics/route-map.svg",
  "/assets/graphics/package-box.svg",
];

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE).then((cache) => cache.addAll(CORE_ASSETS)).then(() => self.skipWaiting())
  );
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) =>
        Promise.all(
          keys
            .filter((k) => ![STATIC_CACHE, RUNTIME_CACHE, PAGE_CACHE].includes(k))
            .map((k) => caches.delete(k))
        )
      )
      .then(() => self.clients.claim())
  );
});

self.addEventListener("message", (event) => {
  if (event.data && event.data.type === "SKIP_WAITING") {
    self.skipWaiting();
  }
});

self.addEventListener("fetch", (event) => {
  const req = event.request;
  if (req.method !== "GET") return;

  const url = new URL(req.url);
  const sameOrigin = url.origin === self.location.origin;
  if (!sameOrigin) return;

  if (req.mode === "navigate") {
    event.respondWith(networkFirstPage(req));
    return;
  }

  if (["script", "style", "image", "font"].includes(req.destination)) {
    event.respondWith(staleWhileRevalidate(req, RUNTIME_CACHE));
    return;
  }

  event.respondWith(cacheFirst(req, RUNTIME_CACHE));
});

async function networkFirstPage(request) {
  const cache = await caches.open(PAGE_CACHE);
  try {
    const network = await fetch(request);
    cache.put(request, network.clone());
    return network;
  } catch (_) {
    const cached = await cache.match(request);
    if (cached) return cached;
    const offline = await caches.match("/offline.html");
    if (offline) return offline;
    return new Response("Offline", { status: 503, statusText: "Offline" });
  }
}

async function cacheFirst(request, cacheName) {
  const cache = await caches.open(cacheName);
  const cached = await cache.match(request);
  if (cached) return cached;
  const network = await fetch(request);
  cache.put(request, network.clone());
  return network;
}

async function staleWhileRevalidate(request, cacheName) {
  const cache = await caches.open(cacheName);
  const cached = await cache.match(request);
  const networkPromise = fetch(request)
    .then((response) => {
      cache.put(request, response.clone());
      return response;
    })
    .catch(() => null);

  if (cached) {
    return cached;
  }

  const network = await networkPromise;
  if (network) return network;

  return new Response("", { status: 504, statusText: "Gateway Timeout" });
}
