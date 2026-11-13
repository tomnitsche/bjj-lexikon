/**
 * Kursliste (Amelia): Reiter nach Jahr, z. B. „Alle · 2026 · 2027“ (13.11.2026)
 * Erscheint nur, wenn Kurse aus mindestens zwei Jahren in der Liste stehen.
 */
add_action( 'wp_footer', function () {
	if ( is_admin() ) { return; } ?>
<style id="gg-yeartabs-css">
.gg-yt{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 18px}
.gg-yt button{font:inherit;font-size:15px;font-weight:700;padding:9px 18px;border:1px solid #121314;background:transparent;color:#121314;cursor:pointer;transition:background .2s,color .2s}
.gg-yt button:hover{background:rgba(18,19,20,.08)}
.gg-yt button[aria-pressed="true"]{background:#121314;color:#fff}
.gg-yt small{font-weight:400;opacity:.7;margin-left:6px}
.gg-yt-hide{display:none!important}
</style>
<script>
(function(){
  var box=document.querySelector('#amelia-container'); if(!box) return;
  var current='all', bar=null;
  function yearOf(card){ var t=(card.querySelector('.am-ec__period-date')||card).textContent; var m=t.match(/(20\d\d)/); return m?m[1]:null; }
  function cards(){ return Array.prototype.slice.call(box.querySelectorAll('.am-ec')); }
  function apply(){
    var cs=cards(), years={};
    cs.forEach(function(c){ var y=yearOf(c); if(y){ years[y]=(years[y]||0)+1; } });
    var list=Object.keys(years).sort();
    if(list.length<2){ if(bar){ bar.remove(); bar=null; } cs.forEach(function(c){ c.classList.remove('gg-yt-hide'); }); return; }
    if(!bar){ bar=document.createElement('div'); bar.className='gg-yt'; bar.setAttribute('role','group'); bar.setAttribute('aria-label','Kurse nach Jahr'); box.parentNode.insertBefore(bar, box); }
    var html='<button type="button" data-y="all" aria-pressed="'+(current==='all')+'">Alle<small>'+cs.length+'</small></button>';
    list.forEach(function(y){ html+='<button type="button" data-y="'+y+'" aria-pressed="'+(current===y)+'">'+y+'<small>'+years[y]+'</small></button>'; });
    if(bar.innerHTML!==html) bar.innerHTML=html;
    cs.forEach(function(c){ var y=yearOf(c); c.classList.toggle('gg-yt-hide', current!=='all' && y!==current); });
  }
  document.addEventListener('click',function(e){ var b=e.target.closest && e.target.closest('.gg-yt button'); if(!b) return; current=b.getAttribute('data-y'); apply(); });
  var t=0; new MutationObserver(function(){ clearTimeout(t); t=setTimeout(apply,120); }).observe(box,{childList:true,subtree:true});
  apply();
})();
</script>
<?php }, 100 );
