/* ردیف‌های چیپ فیلتر در موبایل → یک کلید «فیلترها» با شیت انتخاب */
(function () {
  if (window.__dyMobileFilters) return;
  window.__dyMobileFilters = true;

  var MQ = 900;
  var sheet = null, backdrop = null;

  function isMobile() { return window.innerWidth <= MQ; }

  function close() {
    if (sheet) { sheet.remove(); sheet = null; }
    if (backdrop) { backdrop.remove(); backdrop = null; }
    document.removeEventListener('keydown', onKey);
  }
  function onKey(e) { if (e.key === 'Escape') close(); }

  function activeOf(btns) {
    var best = null, bestScore = -1;
    btns.forEach(function (b) {
      var cs = getComputedStyle(b);
      var bg = cs.backgroundColor.replace(/[^\d,]/g, '').split(',').map(Number);
      var bc = cs.borderTopColor;
      var score = 0;
      if (bg.length >= 3 && !(bg[0] > 245 && bg[1] > 245 && bg[2] > 245)) score += 2;
      if (/rgb\(2[0-5][0-9], (6|7|8)[0-9]/.test(bc) || /244, 81, 30/.test(bc)) score += 1;
      if (cs.fontWeight >= 800 && score > 0) score += 1;
      if (score > bestScore) { bestScore = score; best = b; }
    });
    return bestScore > 0 ? best : btns[0];
  }

  function labelOf(btn) {
    return (btn.textContent || '').replace(/\s+/g, ' ').trim();
  }

  function open(btns, title) {
    close();
    backdrop = document.createElement('div');
    backdrop.style.cssText = 'position:fixed;inset:0;z-index:215;background:rgba(11,13,16,.5)';
    backdrop.addEventListener('click', close);

    sheet = document.createElement('div');
    sheet.setAttribute('dir', 'rtl');
    sheet.style.cssText = 'position:fixed;z-index:216;inset-inline:10px;bottom:10px;max-height:76vh;overflow-y:auto;'
      + 'background:#fff;border-radius:22px;box-shadow:0 30px 70px -22px rgba(0,0,0,.5);'
      + 'padding:14px;display:flex;flex-direction:column;gap:8px;font-family:inherit';

    var head = document.createElement('div');
    head.style.cssText = 'display:flex;align-items:center;gap:10px;padding:0 2px 4px';
    var h = document.createElement('span');
    h.textContent = title || 'فیلترها';
    h.style.cssText = 'font-size:14.5px;font-weight:800;color:#23262B';
    var x = document.createElement('span');
    x.textContent = '✕';
    x.style.cssText = 'margin-inline-start:auto;width:32px;height:32px;border-radius:10px;border:1px solid #EDEEF1;'
      + 'display:flex;align-items:center;justify-content:center;font-size:12px;color:#5A6169;cursor:pointer';
    x.addEventListener('click', close);
    head.appendChild(h); head.appendChild(x);
    sheet.appendChild(head);

    var act = activeOf(btns);
    btns.forEach(function (b) {
      var on = b === act;
      var row = document.createElement('div');
      row.textContent = labelOf(b);
      row.style.cssText = 'padding:13px 14px;border-radius:14px;font-size:13.5px;font-weight:800;cursor:pointer;'
        + 'text-align:start;border:1.5px solid ' + (on ? '#F4511E;background:#FFF6F2;color:#D8420F' : '#EFF0F2;background:#FBFBFC;color:#3A4048');
      row.addEventListener('click', function () {
        close();
        b.click();
      });
      sheet.appendChild(row);
    });

    document.body.appendChild(backdrop);
    document.body.appendChild(sheet);
    document.addEventListener('keydown', onKey);
  }

  function restore(box) {
    box.querySelectorAll('[data-om-mf="proxy"]').forEach(function (e) { e.remove(); });
    Array.prototype.forEach.call(box.children, function (c) {
      if (c.getAttribute && c.getAttribute('data-om-mf') === 'hidden') {
        c.style.display = '';
        c.removeAttribute('data-om-mf');
      }
    });
    box.removeAttribute('data-om-mf-done');
  }

  function candidate(box) {
    if (box.closest('header')) return null;
    if (box.getAttribute('data-om-mf-done')) return null;
    var cs = getComputedStyle(box);
    if (cs.display !== 'flex') return null;
    /* فقط ردیف‌های سطح صفحه یا تب‌های سر کارت — نه دکمه‌های اقدام داخل کارت‌ها */
    var host = box.parentElement;
    if (!host) return null;
    var pageLevel = host.parentElement && host.parentElement.tagName === 'MAIN';
    var cardHeader = /border-bottom/.test(host.getAttribute('style') || '');
    if (!pageLevel && !cardHeader) return null;
    var kids = Array.prototype.filter.call(box.children, function (c) { return !c.getAttribute || c.getAttribute('data-om-mf') !== 'proxy'; });
    if (kids.length < 4) return null;
    var btns = [], bgs = {}, nLight = 0, nDark = 0;
    for (var i = 0; i < kids.length; i++) {
      if (kids[i].tagName !== 'BUTTON') return null;
      var t = labelOf(kids[i]);
      if (!t || t.length > 26) return null;
      if (t.length < 2 || /^[→←‹›↑↓⟵⟶+]/.test(t)) return null;
      var cs = getComputedStyle(kids[i]);
      var m = cs.backgroundColor.match(/\d+/g) || ['255', '255', '255'];
      bgs[cs.backgroundColor] = 1;
      if (Number(m[0]) > 244 && Number(m[1]) > 244 && Number(m[2]) > 244) nLight++; else nDark++;
      btns.push(kids[i]);
    }
    /* گروه چیپ واقعی: حداکثر دو رنگ پس‌زمینه و دقیقاً یک گزینه فعال */
    if (Object.keys(bgs).length > 2) return null;
    if (nDark !== 1 || nLight < 2) return null;
    return btns;
  }

  function apply(box) {
    if (!isMobile()) { if (box.getAttribute('data-om-mf-done')) restore(box); return; }
    var btns = candidate(box);
    if (!btns) return;

    var inCardHeader = !!box.parentElement && /border-bottom/.test(box.parentElement.getAttribute('style') || '');
    var act = activeOf(btns);
    var label = (inCardHeader ? '' : 'فیلترها · ') + labelOf(act);
    if (label.length > 30) label = label.slice(0, 29) + '…';

    btns.forEach(function (b) { b.setAttribute('data-om-mf', 'hidden'); b.style.display = 'none'; });

    var proxy = document.createElement('button');
    proxy.type = 'button';
    proxy.setAttribute('data-om-mf', 'proxy');
    proxy.innerHTML = '<span style="font-size:15px;line-height:1">☰</span><span></span><span style="font-size:11px;opacity:.6">▾</span>';
    proxy.children[1].textContent = label;
    proxy.style.cssText = 'display:flex;align-items:center;gap:8px;height:44px;padding:0 14px;'
      + 'border:1.5px solid #E3E6EA;border-radius:14px;background:#fff;color:#23262B;'
      + 'font-size:13px;font-weight:800;font-family:inherit;cursor:pointer;max-width:100%;'
      + 'overflow:hidden;white-space:nowrap;text-overflow:ellipsis';
    proxy.addEventListener('click', function (e) {
      e.stopPropagation();
      open(btns, inCardHeader ? 'انتخاب بخش' : 'فیلترها');
    });
    box.appendChild(proxy);
    box.setAttribute('data-om-mf-done', '1');
  }

  function refresh(box) {
    var proxy = box.querySelector(':scope > [data-om-mf="proxy"]');
    if (!proxy) return;
    var btns = Array.prototype.filter.call(box.children, function (c) {
      return c.tagName === 'BUTTON' && c.getAttribute('data-om-mf') === 'hidden';
    });
    if (!btns.length) return;
    var inCardHeader = /border-bottom/.test((box.parentElement.getAttribute('style') || ''));
    var label = (inCardHeader ? '' : 'فیلترها · ') + labelOf(activeOf(btns));
    if (label.length > 30) label = label.slice(0, 29) + '…';
    if (proxy.children[1].textContent !== label) proxy.children[1].textContent = label;
  }

  /* ---- کارت فیلتر کامل (چند ردیف چیپ + جست‌وجو) → یک کلید «فیلترها» ---- */

  function chipRows(card) {
    return Array.prototype.filter.call(card.children, function (row) {
      if (row.tagName !== 'DIV') return false;
      var f = row.firstElementChild;
      if (!f || f.tagName !== 'SPAN') return false;
      if (!/[:：]\s*$/.test((f.textContent || '').trim())) return false;
      return row.children.length >= 3;
    });
  }

  function isChipRow(row) {
    if (!row || row.tagName !== 'DIV') return false;
    var kids = Array.prototype.slice.call(row.children).filter(function (c) {
      return !c.getAttribute || c.getAttribute('data-om-mf') !== 'proxy';
    });
    if (kids.length < 3) return false;
    return kids.every(function (c) {
      if (c.tagName === 'BUTTON') return true;
      if (c.tagName !== 'DIV') return false;
      if (getComputedStyle(c).cursor !== 'pointer') return false;
      return (c.textContent || '').trim().length <= 26;
    });
  }

  function chipGroups(card) {
    var out = [];
    Array.prototype.forEach.call(card.children, function (row) {
      if (isChipRow(row)) { out.push(row); return; }
      /* گروه چیپ داخل یک بلوک برچسب‌دار (برچسب + ردیف چیپ) */
      if (row.tagName === 'DIV' && row.children.length <= 3) {
        Array.prototype.forEach.call(row.children, function (sub) {
          if (isChipRow(sub)) out.push(sub);
        });
      }
    });
    return out;
  }

  function activeChips(rows) {
    var out = [];
    rows.forEach(function (row) {
      var labeled = row.firstElementChild && row.firstElementChild.tagName === 'SPAN'
        && /[:：]\s*$/.test((row.firstElementChild.textContent || '').trim());
      var kids = Array.prototype.slice.call(row.children, labeled ? 1 : 0);
      var pick = null;
      kids.forEach(function (k) {
        var cs = getComputedStyle(k);
        var m = cs.backgroundColor.match(/\d+/g);
        if (!m) return;
        var light = Number(m[0]) > 244 && Number(m[1]) > 244 && Number(m[2]) > 244;
        if (!light && !pick) pick = k;
      });
      if (pick) out.push((pick.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 18));
    });
    return out;
  }

  function closeCard() {
    var card = document.querySelector('[data-om-fc-open]');
    if (card) {
      card.querySelectorAll('[data-om-fc-kid]').forEach(function (c) {
        c.setAttribute('style', c.getAttribute('data-om-fc-kid') || '');
        c.removeAttribute('data-om-fc-kid');
      });
      card.setAttribute('style', card.getAttribute('data-om-fc-style') || '');
      card.style.display = 'none';
      card.removeAttribute('data-om-fc-open');
      var bar = card.querySelector('[data-om-fc="bar"]');
      if (bar) bar.remove();
    }
    if (backdrop) { backdrop.remove(); backdrop = null; }
    document.removeEventListener('keydown', onKeyCard);
  }
  function onKeyCard(e) { if (e.key === 'Escape') closeCard(); }

  function openCard(card) {
    close();
    backdrop = document.createElement('div');
    backdrop.style.cssText = 'position:fixed;inset:0;z-index:215;background:rgba(11,13,16,.5)';
    backdrop.addEventListener('click', closeCard);
    document.body.appendChild(backdrop);

    card.setAttribute('data-om-fc-open', '1');
    card.style.cssText = (card.getAttribute('data-om-fc-style') || '')
      + ';display:flex !important;flex-direction:column;gap:14px;position:fixed;z-index:216;'
      + 'inset-inline:10px;bottom:10px;top:auto;max-height:78vh;overflow-y:auto;border-radius:22px;'
      + 'background:#fff;border:0;box-shadow:0 30px 70px -22px rgba(0,0,0,.5);padding:16px;align-items:stretch';
    Array.prototype.forEach.call(card.children, function (c) {
      if (!c.style) return;
      c.setAttribute('data-om-fc-kid', c.getAttribute('style') || '');
      c.style.flex = '0 0 auto';
      c.style.width = '100%';
      c.style.minWidth = '0';
      c.style.maxWidth = '100%';
    });

    var bar = document.createElement('div');
    bar.setAttribute('data-om-fc', 'bar');
    bar.style.cssText = 'display:flex;align-items:center;gap:10px;order:-1;flex:0 0 auto';
    var t = document.createElement('span');
    t.textContent = 'فیلترها و جست‌وجو';
    t.style.cssText = 'font-size:14.5px;font-weight:800;color:#23262B';
    var done = document.createElement('button');
    done.type = 'button';
    done.textContent = 'نمایش نتایج';
    done.style.cssText = 'margin-inline-start:auto;height:38px;padding:0 14px;border:0;border-radius:12px;'
      + 'background:#15181D;color:#fff;font-size:12.5px;font-weight:800;font-family:inherit;cursor:pointer';
    done.addEventListener('click', closeCard);
    bar.appendChild(t); bar.appendChild(done);
    card.insertBefore(bar, card.firstChild);
    document.addEventListener('keydown', onKeyCard);
  }

  function restoreCard(card) {
    var proxy = card.previousElementSibling;
    if (proxy && proxy.getAttribute && proxy.getAttribute('data-om-fc') === 'proxy') proxy.remove();
    card.setAttribute('style', card.getAttribute('data-om-fc-style') || '');
    card.removeAttribute('data-om-fc-style');
    card.removeAttribute('data-om-fc-done');
  }

  function applyFilterCard(card) {
    if (!isMobile()) { if (card.getAttribute('data-om-fc-done')) restoreCard(card); return; }
    if (card.getAttribute('data-om-fc-done')) { refreshCard(card); return; }
    if (card.closest('header')) return;
    if (card.closest('[data-no-filter-collapse]')) return;
    var host = card.parentElement;
    if (!host) return;
    /* کارت فیلتر باید کوچک و محدود باشد — نه ظرف محتوای صفحه */
    if (card.children.length > 7) return;
    if ((card.textContent || '').replace(/\s+/g, ' ').length > 520) return;
    if (card.querySelectorAll('div').length > 70) return;
    if (card.querySelector('[data-om-fc-done], [data-om-mf-done], table, textarea, image-slot, main')) return;
    var rows = chipRows(card);
    var groups = chipGroups(card);
    var hasInput = !!card.querySelector('input[type="text"], input:not([type])');
    var total = rows.length + groups.length;
    var pageLevel = host.parentElement && host.parentElement.tagName === 'MAIN';
    if (!pageLevel && !hasInput) return;
    if (total < 2 && !(total === 1 && hasInput)) return;

    card.setAttribute('data-om-fc-style', card.getAttribute('style') || '');
    card.setAttribute('data-om-fc-done', '1');
    card.style.display = 'none';

    var proxy = document.createElement('button');
    proxy.type = 'button';
    proxy.setAttribute('data-om-fc', 'proxy');
    proxy.innerHTML = '<span style="font-size:15px;line-height:1">⚟</span><span style="min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"></span><span style="font-size:11px;opacity:.55">▾</span>';
    proxy.style.cssText = 'display:flex;align-items:center;gap:9px;width:100%;height:48px;padding:0 15px;'
      + 'border:1.5px solid #E3E6EA;border-radius:16px;background:#fff;color:#23262B;'
      + 'font-size:13px;font-weight:800;font-family:inherit;cursor:pointer;text-align:start';
    proxy.addEventListener('click', function (e) { e.stopPropagation(); openCard(card); });
    host.insertBefore(proxy, card);
    refreshCard(card);
  }

  function refreshCard(card) {
    var proxy = card.previousElementSibling;
    if (!proxy || proxy.getAttribute('data-om-fc') !== 'proxy') return;
    var act = activeChips(chipRows(card).concat(chipGroups(card)));
    var txt = 'فیلترها و جست‌وجو' + (act.length ? ' · ' + act.join(' · ') : '');
    if (txt.length > 44) txt = txt.slice(0, 43) + '…';
    if (proxy.children[1].textContent !== txt) proxy.children[1].textContent = txt;
  }

  function scan() {
    document.querySelectorAll('main div').forEach(function (b) {
      try {
        var rows = b.getAttribute('data-om-fc-done') ? 2 : (chipRows(b).length + chipGroups(b).length);
        var okCard = rows >= 2 || (rows === 1 && b.querySelector('input[type="text"], input:not([type])'));
        if (okCard) applyFilterCard(b);
        else if (b.getAttribute('data-om-mf-done')) refresh(b);
        else apply(b);
      } catch (e) {}
    });
  }

  var t = null;
  function schedule() { clearTimeout(t); t = setTimeout(scan, 90); }

  if (document.readyState !== 'loading') schedule();
  document.addEventListener('DOMContentLoaded', schedule);
  window.addEventListener('resize', function () {
    close();
    closeCard();
    document.querySelectorAll('main div[data-om-mf-done]').forEach(restore);
    document.querySelectorAll('main div[data-om-fc-done]').forEach(restoreCard);
    schedule();
  });
  new MutationObserver(schedule).observe(document.documentElement, { childList: true, subtree: true });
})();
