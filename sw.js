const CACHE_NAME = "wtc-cache-v11";
const BASE_PATH = self.location.pathname.replace(/\/sw\.js$/, "") || "/";
const BASE_PREFIX = BASE_PATH === "/" ? "" : BASE_PATH;
const PRECACHE_URLS = [
  `${BASE_PREFIX}/css/bootstrap.min.css`,
  `${BASE_PREFIX}/js/bootstrap.bundle.min.js`,
  `${BASE_PREFIX}/css/bootstrap-icons.min.css`,
  `${BASE_PREFIX}/css/fonts/bootstrap-icons.woff2`,
  `${BASE_PREFIX}/css/fonts/anton.ttf`,
  `${BASE_PREFIX}/css/fonts/oswald-400.ttf`,
  `${BASE_PREFIX}/css/fonts/oswald-500.ttf`,
  `${BASE_PREFIX}/css/fonts/oswald-600.ttf`,
  `${BASE_PREFIX}/css/fonts/inter-400.ttf`,
  `${BASE_PREFIX}/css/fonts/inter-500.ttf`,
  `${BASE_PREFIX}/css/fonts/inter-600.ttf`,
  `${BASE_PREFIX}/css/fonts/inter-700.ttf`,
  `${BASE_PREFIX}/css/style.css`,
  `${BASE_PREFIX}/js/seances.js?v=202607102200`,
  `${BASE_PREFIX}/img/wtc.png`,
  `${BASE_PREFIX}/manifest.json`,
  `${BASE_PREFIX}/offline.html`,
];

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches
      .open(CACHE_NAME)
      .then((cache) => cache.addAll(PRECACHE_URLS))
      .then(() => self.skipWaiting()),
  );
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) =>
        Promise.all(
          keys
            .filter((key) => key !== CACHE_NAME)
            .map((key) => caches.delete(key)),
        ),
      )
      .then(() => self.clients.claim()),
  );
});

self.addEventListener("fetch", (event) => {
  if (event.request.method !== "GET") {
    return;
  }

  // Do not try to cache or handle Server-Sent Events (streaming endpoints)
  try {
    const urlCheck = new URL(event.request.url);
    if (urlCheck.pathname.endsWith('/api/commits-events.php') || urlCheck.pathname.includes('/commits-events.php')) {
      // let the browser handle the SSE connection directly
      return;
    }
  } catch (e) {
    // ignore URL parsing errors
  }

  const requestURL = new URL(event.request.url);
  const isSameOrigin = requestURL.origin === self.location.origin;
  const isNavigation = event.request.mode === "navigate";
  const isHtmlRequest = event.request.headers
    .get("accept")
    ?.includes("text/html");

  if (isNavigation || isHtmlRequest) {
    event.respondWith(
      fetch(event.request, { cache: "no-store" }).catch(() => {
        return caches.match(`${BASE_PREFIX}/offline.html`);
      }),
    );
    return;
  }

  if (
    isSameOrigin &&
    requestURL.pathname.includes("/img/pdps/")
  ) {
    event.respondWith(fetch(event.request, { cache: "no-store" }));
    return;
  }

  if (
    isSameOrigin &&
    (event.request.destination === "style" ||
      event.request.destination === "script" ||
      event.request.destination === "image" ||
      event.request.destination === "font" ||
      requestURL.pathname.endsWith(".json"))
  ) {
    event.respondWith(cacheFirst(event.request));
    return;
  }

  event.respondWith(networkFirst(event.request));
});

function cacheFirst(request) {
  return caches.match(request, { ignoreSearch: true }).then((cached) => {
    return (
      cached ||
      fetch(request, { cache: "no-store" }).then((response) => {
        return caches.open(CACHE_NAME).then((cache) => {
          cache.put(request, response.clone());
          return response;
        });
      })
    );
  });
}

function networkFirst(request) {
  return fetch(request, { cache: "no-store" })
    .then((response) => {
      if (
        request.url.endsWith(`${BASE_PREFIX}/`) ||
        response.type === "basic"
      ) {
        const responseClone = response.clone();
        caches
          .open(CACHE_NAME)
          .then((cache) => cache.put(request, responseClone));
      }
      return response;
    })
    .catch(() => {
      return caches.match(request).then((cached) => {
        if (cached) return cached;
        return caches.match(`${BASE_PREFIX}/offline.html`);
      });
    });
}
