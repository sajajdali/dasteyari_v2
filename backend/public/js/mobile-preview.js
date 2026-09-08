(() => {
  const P = new URLSearchParams(location.search);
  const EMBEDDED = P.get('om-mobile') === '1';

  const src = () => {
    const u = new URL(location.href);
    u.searchParams.set('om-mobile', '1');
    return u.toString();
  };

  class MobilePreview extends HTMLElement {
    connectedCallback() {
      if (EMBEDDED || this.dataset.mounted) return;
      this.dataset.mounted = '1';
      const r = this.attachShadow({ mode: 'open' });
      r.innerHTML = `
<style>
  :host{all:initial}
  *{box-sizing:border-box;font-family:Vazirmatn,system-ui,sans-serif}
  .fab{position:fixed;inset-inline-start:18px;bottom:18px;z-index:99998;display:flex;align-items:center;gap:8px;
    height:44px;padding:0 16px;border:0;border-radius:999px;cursor:pointer;background:#23262B;color:#fff;
    font-size:13px;font-weight:600;box-shadow:0 10px 30px -8px rgba(0,0,0,.5);direction:rtl}
  .fab:hover{background:#F4511E}
  .fab svg{width:15px;height:15px}
  .wrap{position:fixed;inset:0;z-index:99999;display:none;align-items:center;justify-content:center;
    background:rgba(18,20,23,.72);backdrop-filter:blur(6px);direction:rtl;overflow:auto;padding:14px 0}
  .wrap[data-open]{display:flex}
  .col{display:flex;flex-direction:column;align-items:center;gap:12px;margin:auto;flex:0 0 auto}
  .bar{display:flex;align-items:center;gap:8px}
  .chip{height:32px;padding:0 14px;border:1px solid rgba(255,255,255,.22);border-radius:999px;background:transparent;
    color:rgba(255,255,255,.72);font-size:12px;font-weight:600;cursor:pointer}
  .chip[data-on]{background:#fff;color:#23262B;border-color:#fff}
  .chip.x{background:#F4511E;border-color:#F4511E;color:#fff}
  .phone{position:relative;border-radius:44px;background:#101215;padding:11px;
    box-shadow:0 40px 90px -20px rgba(0,0,0,.75),0 0 0 1px rgba(255,255,255,.09) inset;flex:0 0 auto}
  .notch{position:absolute;top:17px;left:50%;transform:translateX(-50%);width:96px;height:22px;border-radius:999px;
    background:#101215;z-index:2}
  iframe{display:block;border:0;border-radius:0 0 34px 34px;background:#fff}
  .statusbar{height:26px;border-radius:34px 34px 0 0;background:#fff}
  .hint{color:rgba(255,255,255,.5);font-size:11.5px}
</style>
<button class="fab" part="fab">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
    <rect x="6" y="2.5" width="12" height="19" rx="3"/><path d="M11 19h2"/>
  </svg>
  نمایش موبایل
</button>
<div class="wrap">
  <div class="col">
    <div class="bar">
      <button class="chip" data-w="360">360</button>
      <button class="chip" data-w="390" data-on>390</button>
      <button class="chip" data-w="430">430</button>
      <button class="chip x" data-close>بستن</button>
    </div>
    <div class="phone"><div class="notch"></div><div class="statusbar"></div><iframe title="mobile"></iframe></div>
    <div class="hint">Esc برای بستن</div>
  </div>
</div>`;
      const wrap = r.querySelector('.wrap');
      const frame = r.querySelector('iframe');
      let w = 390;

      const fit = () => {
        const h = Math.max(320, Math.min(844, window.innerHeight - 130));
        frame.style.width = w + 'px';
        frame.style.height = (h - 26) + 'px';
        r.querySelector('.statusbar').style.width = w + 'px';
      };
      const open = () => {
        if (!frame.src) frame.src = src();
        fit(); wrap.setAttribute('data-open', '');
      };
      const close = () => wrap.removeAttribute('data-open');

      r.querySelector('.fab').onclick = open;
      r.querySelector('[data-close]').onclick = close;
      wrap.onclick = e => { if (e.target === wrap) close(); };
      r.querySelectorAll('[data-w]').forEach(b => b.onclick = () => {
        w = +b.dataset.w;
        r.querySelectorAll('[data-w]').forEach(o => o.removeAttribute('data-on'));
        b.setAttribute('data-on', '');
        fit();
      });
      window.addEventListener('resize', () => { if (wrap.hasAttribute('data-open')) fit(); });
      window.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });
    }
  }
  if (!customElements.get('mobile-preview')) customElements.define('mobile-preview', MobilePreview);
})();
