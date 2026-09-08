/* هدر یکسان موبایل برای همه صفحات پنل: ☰ | عنوان | ⌕ | + | ⋯ | 🔔 | آواتار */
(function () {
  if (window.__dyMobileHeader) return;
  window.__dyMobileHeader = true;

  var MQ = 900;
  var pop = null;

  function isMobile() { return window.innerWidth <= MQ; }

  function closePop() {
    if (pop) { pop.remove(); pop = null; }
    document.removeEventListener('keydown', onKey);
  }
  function onKey(e) { if (e.key === 'Escape') closePop(); }

  function iconBtn(glyph, title) {
    var b = document.createElement('button');
    b.type = 'button';
    b.textContent = glyph;
    b.title = title || '';
    b.setAttribute('data-om-mh', 'proxy');
    b.style.cssText = 'flex:0 0 42px;width:42px;height:42px;border-radius:12px;border:1px solid #EDEEF1;'
      + 'background:#fff;color:#5A6169;font-size:19px;font-weight:700;line-height:1;cursor:pointer;'
      + 'display:flex;align-items:center;justify-content:center;font-family:inherit;padding:0';
    return b;
  }

  function openMenu(anchor, items) {
    closePop();
    pop = document.createElement('div');
    pop.setAttribute('dir', 'rtl');
    pop.style.cssText = 'position:fixed;z-index:210;top:62px;inset-inline:12px;background:#fff;'
      + 'border:1px solid #E3E6EA;border-radius:16px;box-shadow:0 26px 55px -24px rgba(20,22,26,.4);'
      + 'overflow:hidden;padding:6px;display:flex;flex-direction:column;gap:4px;font-family:inherit';
    items.forEach(function (btn) {
      var row = document.createElement('div');
      row.textContent = (btn.textContent || '').trim();
      row.style.cssText = 'padding:12px 13px;border-radius:12px;font-size:13px;font-weight:700;'
        + 'color:#3A4048;cursor:pointer;background:#FBFBFC;text-align:start';
      row.addEventListener('click', function () {
        closePop();
        btn.style.display = '';
        btn.click();
        setTimeout(function () { if (isMobile()) btn.style.display = 'none'; }, 0);
      });
      pop.appendChild(row);
    });
    var back = document.createElement('div');
    back.style.cssText = 'position:fixed;inset:0;z-index:205';
    back.addEventListener('click', closePop);
    document.body.appendChild(back);
    pop.addEventListener('remove', function () { back.remove(); });
    var origRemove = pop.remove.bind(pop);
    pop.remove = function () { back.remove(); origRemove(); };
    document.body.appendChild(pop);
    document.addEventListener('keydown', onKey);
  }

  function restore(header) {
    header.querySelectorAll('[data-om-mh="proxy"]').forEach(function (e) { e.remove(); });
    header.querySelectorAll('[data-om-mh="hidden"]').forEach(function (e) {
      e.style.display = '';
      e.removeAttribute('data-om-mh');
    });
    header.removeAttribute('data-om-mh-done');
  }
  function apply(header) {
    if (!isMobile()) { if (header.getAttribute('data-om-mh-done')) restore(header); return; }
    if (header.getAttribute('data-om-mh-done')) return;

    /* گروه ابزارهای انتهای هدر (یا هر گروهی که دکمه متنی دارد) */
    var groups = Array.prototype.filter.call(header.children, function (c) {
      return c.tagName === 'DIV' && c.querySelector(':scope > button');
    });
    var loose = Array.prototype.filter.call(header.children, function (c) {
      return c.tagName === 'BUTTON' && (c.textContent || '').trim();
    });
    if (!groups.length && !loose.length) { header.setAttribute('data-om-mh-done', '1'); return; }

    var host = groups.length ? groups[groups.length - 1] : null;
    if (!host) {
      host = document.createElement('div');
      host.setAttribute('data-om-mh', 'proxy');
      host.style.cssText = 'display:flex;align-items:center;gap:7px;margin-inline-start:auto;flex:0 0 auto';
      header.appendChild(host);
    }
    var primary = null, extras = [];

    loose.forEach(function (btn) {
      var t = (btn.textContent || '').trim();
      if (!primary && t.charAt(0) === '+') primary = btn; else extras.push(btn);
    });
    groups.forEach(function (g) {
      Array.prototype.slice.call(g.children).forEach(function (btn) {
        if (btn.tagName !== 'BUTTON') return;
        var t = (btn.textContent || '').trim();
        if (!t) return;
        if (!primary && t.charAt(0) === '+') primary = btn;
        else extras.push(btn);
      });
    });

    if (primary) {
      primary.setAttribute('data-om-mh', 'hidden');
      primary.style.display = 'none';
      var add = iconBtn('+', (primary.textContent || '').trim());
      add.style.background = '#F4511E';
      add.style.color = '#fff';
      add.style.border = '0';
      add.style.fontSize = '23px';
      add.addEventListener('click', function () {
        primary.style.display = '';
        primary.click();
        setTimeout(function () { if (isMobile()) primary.style.display = 'none'; }, 0);
      });
      host.insertBefore(add, host.firstChild);
    }

    if (extras.length) {
      extras.forEach(function (b) { b.setAttribute('data-om-mh', 'hidden'); b.style.display = 'none'; });
      var more = iconBtn('⋯', 'ابزارهای این صفحه');
      more.style.fontSize = '22px';
      more.addEventListener('click', function (e) {
        e.stopPropagation();
        if (pop) closePop(); else openMenu(more, extras);
      });
      host.insertBefore(more, host.firstChild);
    }

    header.setAttribute('data-om-mh-done', '1');
  }

  function scan() {
    document.querySelectorAll('main > header').forEach(apply);
  }

  var t = null;
  function schedule() { clearTimeout(t); t = setTimeout(scan, 60); }

  if (document.readyState !== 'loading') schedule();
  document.addEventListener('DOMContentLoaded', schedule);
  window.addEventListener('resize', function () { closePop(); document.querySelectorAll('main > header').forEach(restore); schedule(); });
  new MutationObserver(schedule).observe(document.documentElement, { childList: true, subtree: true });
})();
