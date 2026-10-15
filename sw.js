// BJJ Lexikon – Offline-Cache. Bei jeder Änderung an der App VERSION erhöhen.
const VERSION="bjj-v21";
const SHELL=["./","./index.html","./manifest.webmanifest","./icon-192.png","./icon-512.png","./apple-touch-icon.png"];
self.addEventListener("install",e=>{e.waitUntil(caches.open(VERSION).then(c=>c.addAll(SHELL)).then(()=>self.skipWaiting()))});
self.addEventListener("activate",e=>{e.waitUntil(caches.keys().then(ks=>Promise.all(ks.filter(k=>k!==VERSION).map(k=>caches.delete(k)))).then(()=>self.clients.claim()))});
self.addEventListener("fetch",e=>{
  const req=e.request;if(req.method!=="GET")return;
  const url=new URL(req.url);
  const fonts=url.hostname==="fonts.googleapis.com"||url.hostname==="fonts.gstatic.com";
  if(url.origin!==location.origin&&!fonts)return;
  e.respondWith(caches.open(VERSION).then(async c=>{
    const hit=await c.match(req,{ignoreSearch:url.origin===location.origin});
    const net=fetch(req).then(r=>{if(r&&(r.ok||r.type==="opaque"))c.put(req,r.clone());return r}).catch(()=>hit);
    return hit||net;
  }));
});
