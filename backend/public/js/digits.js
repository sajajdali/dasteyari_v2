/**
 * تبدیل خودکار ارقام فارسی/عربی به لاتین در تمام input و textarea های سایت.
 * بدون این تبدیل، اعداد فارسی (۰-۹) یا عربی (٠-٩) که کاربر تایپ می‌کند با اعتبارسنجی
 * سرور (digits, numeric, regex شماره موبایل و ...) که لاتین انتظار دارد جور در نمی‌آید.
 * سراسری و مستقل از فریم‌ورک — روی هر صفحه (سایت/ادمین/خیرین/نیازمند/ورود) بارگذاری می‌شود.
 */
(function () {
  'use strict';

  function toLatinDigit(ch) {
    var code = ch.charCodeAt(0);
    if (code >= 0x06f0 && code <= 0x06f9) return String(code - 0x06f0); // فارسی ۰-۹
    if (code >= 0x0660 && code <= 0x0669) return String(code - 0x0660); // عربی ٠-٩
    return ch;
  }

  function normalize(event) {
    var el = event.target;
    if (!el || (el.tagName !== 'INPUT' && el.tagName !== 'TEXTAREA')) return;
    if (el.type === 'password' || el.type === 'file') return;

    var value = el.value;
    var converted = value.replace(/[۰-۹٠-٩]/g, toLatinDigit);
    if (converted === value) return;

    var start = el.selectionStart;
    var end = el.selectionEnd;
    el.value = converted;
    if (start !== null && end !== null) {
      try {
        el.setSelectionRange(start, end);
      } catch (e) {
        /* برخی type ها (مثل number) از setSelectionRange پشتیبانی نمی‌کنند */
      }
    }
  }

  // capture:true تا قبل از رسیدن رویداد به wire:model/x-model مقدار اصلاح شود.
  document.addEventListener('input', normalize, true);
})();
