</div>
</div>
<script>
(function(){
  function start(){
    if (document.getElementById('ky-pbar')) return;
    var b = document.createElement('div');
    b.id = 'ky-pbar'; b.className = 'start';
    (document.body || document.documentElement).appendChild(b);
    setTimeout(function(){ if (b.parentNode) b.style.width = '88%'; }, 90);
    setTimeout(function(){ if (b.parentNode) b.style.opacity = '0'; }, 8000);
  }
  document.addEventListener('click', function(e){
    var a = e.target.closest ? e.target.closest('a') : null;
    if (!a) return;
    var href = a.getAttribute('href') || '';
    if (!href || href.charAt(0) === '#' || a.target === '_blank' || a.hasAttribute('download')) return;
    if (/^(javascript:|mailto:|tel:)/i.test(href)) return;
    if (a.host && a.host !== location.host) return;
    start();
  }, true);
  document.addEventListener('submit', function(e){
    var m = (e.target.getAttribute('method') || 'get').toLowerCase();
    if (e.defaultPrevented) return;
    if (m === 'get' && !e.target.getAttribute('target')) start();
  });
  window.addEventListener('pageshow', function(){
    var b = document.getElementById('ky-pbar');
    if (b && b.parentNode) b.parentNode.removeChild(b);
  });
})();
</script>
</body>
</html>
