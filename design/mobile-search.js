/* جست‌وجوی موبایل: آیکون ذره‌بین در هدر → مدال تمام‌عرض */
(function () {
  if (window.__dyMobileSearch) return;
  window.__dyMobileSearch = true;

  var MQ = 900;
  var overlay = null;

  function boxOf(node) {
    var el = node;
    while (el && el !== document.body) {
      if (el.tagName === 'DIV' && el.closest('main > header')) {
        var kids = el.children, i;
        for (i = 0; i < kids.length; i++) {
          if (kids[i].tagName === 'INPUT' && (kids[i].type || 'text') === 'text') return el;
        }
      }
      el = el.parentElement;
    }
    return null;
  }

  function close() {
    if (!overlay) return;
    overlay.remove();
    overlay = null;
    document.removeEventListener('keydown', onKey);
  }

  function onKey(e) { if (e.key === 'Escape') close(); }

  function open(placeholder) {
    close();
    overlay = document.createElement('div');
    overlay.setAttribute('dir', 'rtl');
    overlay.style.cssText = 'position:fixed;inset:0;z-index:200;background:rgba(11,13,16,.55);'
      + 'display:flex;align-items:flex-start;justify-content:center;padding:14px;'
      + 'font-family:inherit;backdrop-filter:blur(3px)';

    var card = document.createElement('div');
    card.style.cssText = 'width:100%;max-width:520px;background:#fff;border-radius:20px;overflow:hidden;'
      + 'box-shadow:0 30px 70px -24px rgba(0,0,0,.5);display:flex;flex-direction:column';

    var top = document.createElement('div');
    top.style.cssText = 'display:flex;align-items:center;gap:9px;padding:12px 14px;border-bottom:1px solid #F0F1F3';

    var icon = document.createElement('span');
    icon.textContent = '⌕';
    icon.style.cssText = 'font-size:19px;color:#A9AEB6;flex:0 0 auto';

    var input = document.createElement('input');
    input.type = 'text';
    input.placeholder = placeholder || 'جست‌وجوی نیازمند، خیر، کد پرونده...';
    input.style.cssText = 'flex:1;min-width:0;border:0;outline:0;background:transparent;'
      + 'font-size:15px;font-family:inherit;height:42px';

    var x = document.createElement('span');
    x.textContent = '✕';
    x.style.cssText = 'flex:0 0 34px;width:34px;height:34px;border-radius:11px;border:1px solid #EDEEF1;'
      + 'display:flex;align-items:center;justify-content:center;font-size:12px;color:#5A6169;cursor:pointer';
    x.addEventListener('click', close);

    top.appendChild(icon); top.appendChild(input); top.appendChild(x);

    var body = document.createElement('div');
    body.style.cssText = 'padding:12px 14px 16px;display:flex;flex-direction:column;gap:8px';
    var hint = document.createElement('span');
    hint.textContent = 'جست‌وجوهای پرکاربرد';
    hint.style.cssText = 'font-size:11.5px;font-weight:800;color:#9AA0A8';
    body.appendChild(hint);

    ['پرونده‌های اولویت A', 'خیرین معوق', 'پرونده‌های بدون حامی', 'تیکت‌های بی‌پاسخ'].forEach(function (t) {
      var row = document.createElement('div');
      row.textContent = t;
      row.style.cssText = 'padding:12px 13px;border:1px solid #EFF0F2;border-radius:13px;'
        + 'font-size:13px;font-weight:700;color:#3A4048;cursor:pointer;background:#FBFBFC';
      row.addEventListener('click', function () { input.value = t; input.focus(); });
      body.appendChild(row);
    });

    card.appendChild(top); card.appendChild(body);
    overlay.appendChild(card);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
    document.body.appendChild(overlay);
    document.addEventListener('keydown', onKey);
    setTimeout(function () { input.focus(); }, 30);
  }

  document.addEventListener('click', function (e) {
    if (window.innerWidth > MQ) return;
    var box = boxOf(e.target);
    if (!box) return;
    var inp = box.querySelector('input');
    if (!inp) return;
    e.preventDefault();
    e.stopPropagation();
    open(inp.getAttribute('placeholder'));
  }, true);

  window.addEventListener('resize', function () { if (window.innerWidth > MQ) close(); });
})();
