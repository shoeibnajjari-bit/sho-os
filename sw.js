const CACHE='madar-static-v22';
const STATIC=[
  'assets/app.css',
  'assets/app.js',
  'assets/nav-settings-polish.css',
  'assets/icon.svg',
  'manifest.webmanifest'
];
const STATIC_PATHS=new Set(STATIC.map(path=>'/'+path));

self.addEventListener('install',event=>{
  event.waitUntil(
    caches.open(CACHE)
      .then(cache=>cache.addAll(STATIC))
      .then(()=>self.skipWaiting())
  );
});

self.addEventListener('activate',event=>{
  event.waitUntil(
    caches.keys()
      .then(keys=>Promise.all(keys.filter(key=>key!==CACHE).map(key=>caches.delete(key))))
      .then(()=>self.clients.claim())
  );
});

self.addEventListener('fetch',event=>{
  const request=event.request;
  const url=new URL(request.url);

  if(request.method!=='GET'||url.origin!==self.location.origin)return;

  // Cache only an explicit allowlist of static assets. Never cache /, PHP routes,
  // authentication pages, user-specific HTML, API responses, or financial records.
  if(!STATIC_PATHS.has(url.pathname))return;

  event.respondWith((async()=>{
    const cache=await caches.open(CACHE);
    const cacheKey=new URL(url.pathname,self.location.origin).href;
    const cached=await cache.match(cacheKey);
    if(cached)return cached;

    const response=await fetch(request);
    if(response.ok&&response.type==='basic'){
      await cache.put(cacheKey,response.clone());
    }
    return response;
  })());
});
