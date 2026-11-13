/**
 * Startseite mobil (13.11.2026): Hero flacher, Partnerlogos als Laufband,
 * Kurzfassungen mit „Mehr lesen“ für Leihwaffe und „Hinter Greyground“.
 */
add_action( 'wp_footer', function () {
	if ( is_admin() || ! is_front_page() ) { return; } ?>
<style id="gg-mobil-css">
@media (max-width:781px){
  .gg-hero .gg-hero-img img{height:250px!important;object-position:70% 85%!important}
  .gg-logos.gg-lb{display:block!important;overflow:hidden;padding-top:8px;padding-bottom:8px;-webkit-mask-image:linear-gradient(90deg,transparent,#000 12%,#000 88%,transparent);mask-image:linear-gradient(90deg,transparent,#000 12%,#000 88%,transparent)}
  .gg-logos.gg-lb > .wp-block-group__inner-container{display:none!important}
  .gg-lb-track{display:flex;align-items:center;gap:44px;width:max-content;animation:gg-lb 38s linear infinite}
  .gg-lb-track figure{margin:0!important;flex:0 0 auto;width:120px;height:56px;display:flex;align-items:center;justify-content:center}
  .gg-lb-track img{max-width:120px!important;max-height:52px!important;width:auto!important;height:auto!important;object-fit:contain;filter:grayscale(1) brightness(1.15);opacity:.75}
  @keyframes gg-lb{from{transform:translateX(0)}to{transform:translateX(-50%)}}
  .gg-mf-sum{margin:0 0 14px}
  .gg-mf .gg-mf-x{display:none!important}
  .gg-mf-btn{display:inline-flex;align-items:center;gap:8px;background:none;border:0;border-bottom:1px solid currentColor;padding:2px 0;margin:0 0 22px;font:inherit;font-size:15px;font-weight:700;color:inherit;cursor:pointer}
  .gg-mf-btn::after{content:"+";font-weight:400}
  .gg-mf-open .gg-mf-btn::after{content:"–"}
  .gg-mf-open .gg-mf-sum{display:none}
}
@media (min-width:782px){ .gg-mf-sum,.gg-mf-btn{display:none!important} }
@media (max-width:781px) and (prefers-reduced-motion:reduce){ .gg-lb-track{animation:none} .gg-logos.gg-lb{overflow-x:auto} }
</style>
<script>
(function(){
  if(!window.matchMedia('(max-width:781px)').matches) return;
  /* Logos als Laufband */
  var lg=document.querySelector('.gg-logos');
  if(lg && !lg.querySelector('.gg-lb-track')){
    var figs=lg.querySelectorAll('figure'); if(figs.length){
      var tr=document.createElement('div'); tr.className='gg-lb-track';
      for(var r=0;r<2;r++){ figs.forEach(function(f){ var c=f.cloneNode(true); c.removeAttribute('id'); c.querySelectorAll('img').forEach(function(i){ i.loading='eager'; if(r) i.alt=''; }); if(r) c.setAttribute('aria-hidden','true'); tr.appendChild(c); }); }
      lg.appendChild(tr); lg.classList.add('gg-lb');
    }
  }
  /* Kurzfassung + Mehr lesen */
  function fold(box, anchor, hideEls, summary, label){
    if(!box || !anchor || box.querySelector('.gg-mf-btn')) return;
    var p=document.createElement('p'); p.className='gg-mf-sum'; p.textContent=summary;
    var b=document.createElement('button'); b.type='button'; b.className='gg-mf-btn'; b.textContent=label; b.setAttribute('aria-expanded','false');
    anchor.parentNode.insertBefore(p, anchor.nextSibling); p.parentNode.insertBefore(b, p.nextSibling);
    hideEls.forEach(function(e){ if(e) e.classList.add('gg-mf-x'); });
    box.classList.add('gg-mf');
    b.addEventListener('click',function(){ var open=box.classList.toggle('gg-mf-open'); box.classList.toggle('gg-mf',!open); b.textContent=open?'Weniger anzeigen':label; b.setAttribute('aria-expanded',open?'true':'false'); if(!open) box.scrollIntoView({block:'start'}); });
  }
  var y=document.querySelector('.gg-yellow .gg-ytext');
  if(y){ var h=y.querySelector('h2'); var x=[]; var n=h&&h.nextElementSibling; while(n){ if(!n.classList.contains('wp-block-buttons')) x.push(n); n=n.nextElementSibling; }
    fold(y, h, x, 'An 9 von 10 Standorten leihst du dir für den Kurs kostenlos eine Walther PDP, Holster inklusive.', 'Mehr zur Leihwaffe'); }
  var f=document.querySelector('.gg-founders');
  if(f){ var h2=f.querySelector('h2'); var hide=[f.querySelector('.gg-text')];
    f.querySelectorAll('.gg-person .wp-block-group__inner-container > :not(h3)').forEach(function(e){ hide.push(e); });
    fold(f, h2, hide, 'David und Tom haben Greyground gegründet, entwickeln jeden Kurs selbst und stehen bis heute selbst am Stand.', 'Mehr über uns'); }
})();
</script>
<?php }, 30 );
