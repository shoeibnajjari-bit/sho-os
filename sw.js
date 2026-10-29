const CACHE='madar-static-v26';
const STATIC_PATHS=new Set([
  '/assets/app.css',
  '/assets/app.js',
  '/assets/nav-settings-polish.css',
  '/assets/icon.svg',
  '/manifest.webmanifest'
]);

self.addEventListener('install',event=>{
  // Do not let a missing optional asset prevent the new worker from activating.
  event.waitUntil(self.skipWaiting());
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
  // Only static assets are eligible. HTML, PHP, and all user/financial data bypass SW.
  if(!STATIC_PATHS.has(url.pathname))return;

  event.respondWith((async()=>{
    const cache=await caches.open(CACHE);
    const cacheKey=new URL(url.pathname,self.location.origin).href;
    try {
      // Network-first so CSS/JS fixes become visible immediately after deployment.
      const response=await fetch(request,{cache:'no-store'});
      if(response.ok&&response.type==='basic')await cache.put(cacheKey,response.clone());
      return response;
    } catch(error) {
      const cached=await cache.match(cacheKey);
      if(cached)return cached;
      throw error;
    }
  })());
});
