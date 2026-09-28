/**
 * تقویم شمسی سراسری — قاعدهٔ پروژه: هر انتخاب‌گر تاریخ در کل سایت باید تقویم جلالی (شمسی) نشان دهد
 * و مقداری که به سرور/Livewire می‌رود همیشه میلادی (فرمت Y-m-d) باشد، دقیقاً هم‌خانواده با
 * پکیج morilog/jalali سمت سرور (همان الگوریتم تبدیل، نه تخمینی) تا دو طرف هرگز رقم به رقم فرق نکنند.
 *
 * استفاده: `<x-partials.jalali-date field="startsAt" label="تاریخ شروع" />` (نگاه کن به همان فایل)
 * — این اسکریپت فقط تابع سازندهٔ Alpine (`window.jalaliPicker`) و توابع تبدیل خام (`window.JalaliDate`)
 * را تعریف می‌کند؛ خودِ Alpine در تگ Blade نمونه‌سازی می‌شود.
 */
(function () {
    var div = function (a, b) { return Math.trunc(a / b); };
    var mod = function (a, b) { return a - Math.trunc(a / b) * b; };

    var breaks = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];

    function jalCal(jy) {
        var bl = breaks.length, gy = jy + 621, leapJ = -14, jp = breaks[0], jm, jump, leap, n, i;
        if (jy < jp || jy >= breaks[bl - 1]) throw new Error('سال جلالی نامعتبر: ' + jy);
        for (i = 1; i < bl; i += 1) {
            jm = breaks[i];
            jump = jm - jp;
            if (jy < jm) break;
            leapJ = leapJ + div(jump, 33) * 8 + div(mod(jump, 33), 4);
            jp = jm;
        }
        n = jy - jp;
        leapJ = leapJ + div(n, 33) * 8 + div(mod(n, 33) + 3, 4);
        if (mod(jump, 33) === 4 && jump - n === 4) leapJ += 1;
        var leapG = div(gy, 4) - div((div(gy, 100) + 1) * 3, 4) - 150;
        var march = 20 + leapJ - leapG;
        if (jump - n < 6) n = n - jump + div(jump, 33) * 33;
        leap = mod(mod(n + 1, 33) - 1, 4);
        if (leap === -1) leap = 4;
        return { leap: leap, gy: gy, march: march };
    }

    function g2d(gy, gm, gd) {
        var d = div((gy + div(gm - 8, 6) + 100100) * 1461, 4)
            + div(153 * mod(gm + 9, 12) + 2, 5)
            + gd - 34840408;
        d = d - div(div(gy + 100100 + div(gm - 8, 6), 100) * 3, 4) + 752;
        return d;
    }

    function d2g(jdn) {
        var j, i, gd, gm, gy;
        j = 4 * jdn + 139361631;
        j = j + div(div(4 * jdn + 183187720, 146097) * 3, 4) * 4 - 3908;
        i = div(mod(j, 1461), 4) * 5 + 308;
        gd = div(mod(i, 153), 5) + 1;
        gm = mod(div(i, 153), 12) + 1;
        gy = div(j, 1461) - 100100 + div(8 - gm, 6);
        return { gy: gy, gm: gm, gd: gd };
    }

    function j2d(jy, jm, jd) {
        var r = jalCal(jy);
        return g2d(r.gy, 3, r.march) + (jm - 1) * 31 - div(jm, 7) * (jm - 7) + jd - 1;
    }

    function d2j(jdn) {
        var gy = d2g(jdn).gy, jy = gy - 621, r, jdn1f, jd, jm, k;
        r = jalCal(jy);
        jdn1f = g2d(gy, 3, r.march);
        k = jdn - jdn1f;
        if (k >= 0) {
            if (k <= 185) {
                jm = 1 + div(k, 31);
                jd = mod(k, 31) + 1;
                return { jy: jy, jm: jm, jd: jd };
            }
            k -= 186;
        } else {
            jy -= 1;
            k += 179;
            if (r.leap === 1) k += 1;
        }
        jm = 7 + div(k, 30);
        jd = mod(k, 30) + 1;
        return { jy: jy, jm: jm, jd: jd };
    }

    function toJalali(gy, gm, gd) { return d2j(g2d(gy, gm, gd)); }
    function toGregorian(jy, jm, jd) { return d2g(j2d(jy, jm, jd)); }

    function isLeapJalaliYear(jy) { return jalCal(jy).leap === 0; }

    function jalaliMonthLength(jy, jm) {
        if (jm <= 6) return 31;
        if (jm <= 11) return 30;
        return isLeapJalaliYear(jy) ? 30 : 29;
    }

    var faDigitsMap = { '0': '۰', '1': '۱', '2': '۲', '3': '۳', '4': '۴', '5': '۵', '6': '۶', '7': '۷', '8': '۸', '9': '۹' };
    function faDigits(v) { return String(v).replace(/[0-9]/g, function (d) { return faDigitsMap[d]; }); }
    function pad2(n) { return (n < 10 ? '0' : '') + n; }

    var MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
    var WEEKDAYS = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];

    window.JalaliDate = {
        toJalali: toJalali,
        toGregorian: toGregorian,
        isLeapJalaliYear: isLeapJalaliYear,
        jalaliMonthLength: jalaliMonthLength,
        MONTHS: MONTHS,
        faDigits: faDigits,
        gregorianStrToJalali: function (str) {
            if (!str) return null;
            var p = str.split('-');
            if (p.length !== 3) return null;
            return toJalali(parseInt(p[0], 10), parseInt(p[1], 10), parseInt(p[2], 10));
        },
        jalaliToGregorianStr: function (jy, jm, jd) {
            var g = toGregorian(jy, jm, jd);
            return g.gy + '-' + pad2(g.gm) + '-' + pad2(g.gd);
        },
    };

    /**
     * فکتوری Alpine — روی یک مقدار میلادی ISO (Y-m-d یا رشتهٔ خالی) کار می‌کند.
     * `get`/`set` برای اتصال دوطرفه به `@entangle` والد استفاده می‌شود.
     */
    window.jalaliPicker = function (config) {
        var today = new Date();
        var todayJ = toJalali(today.getFullYear(), today.getMonth() + 1, today.getDate());

        return {
            wire: null,
            field: null,
            value: '',
            open: false,
            viewYear: todayJ.jy,
            viewMonth: todayJ.jm,
            weekdays: WEEKDAYS,
            months: MONTHS,

            /**
             * عمداً به‌جای `@entangle` با `$wire[field]`/`$wire.set()` مستقیم کار می‌کند —
             * چون entangle وقتی مقدارش از داخل یک تابع فکتوری Alpine (نه مستقیم در خودِ x-data)
             * پاس داده شود اتصال دوطرفه واقعی برقرار نمی‌کند (فقط مقدار لحظهٔ فراخوانی را کپی
             * می‌کند)؛ `$wire` یک مجیک Alpine است و در هر عبارت Directive (نه فقط x-data) در
             * دسترس است، پس همیشه به نمونهٔ زندهٔ کامپوننت اشاره می‌کند.
             */
            init: function (wireObj, fieldName) {
                this.wire = wireObj;
                this.field = fieldName;
                this.value = this.wire[this.field] || '';
                this.syncViewFromValue();
            },

            syncViewFromValue: function () {
                var j = window.JalaliDate.gregorianStrToJalali(this.value);
                if (j) { this.viewYear = j.jy; this.viewMonth = j.jm; }
            },

            get display() {
                var j = window.JalaliDate.gregorianStrToJalali(this.value);
                if (!j) return '';
                return faDigits(j.jy) + '/' + faDigits(pad2(j.jm)) + '/' + faDigits(pad2(j.jd));
            },

            get monthLabel() {
                return this.months[this.viewMonth - 1] + ' ' + faDigits(this.viewYear);
            },

            get weeks() {
                var len = window.JalaliDate.jalaliMonthLength(this.viewYear, this.viewMonth);
                var firstGregorian = window.JalaliDate.toGregorian(this.viewYear, this.viewMonth, 1);
                var jsDow = new Date(firstGregorian.gy, firstGregorian.gm - 1, firstGregorian.gd).getDay();
                var leadBlanks = (jsDow + 1) % 7; // هفته از شنبه شروع می‌شود
                var cells = [];
                for (var i = 0; i < leadBlanks; i++) cells.push(null);
                for (var d = 1; d <= len; d++) cells.push(d);
                while (cells.length % 7 !== 0) cells.push(null);
                var rows = [];
                for (var r = 0; r < cells.length; r += 7) rows.push(cells.slice(r, r + 7));
                return rows;
            },

            isSelected: function (day) {
                if (!day) return false;
                var j = window.JalaliDate.gregorianStrToJalali(this.value);
                return !!j && j.jy === this.viewYear && j.jm === this.viewMonth && j.jd === day;
            },

            isToday: function (day) {
                return day === todayJ.jd && this.viewMonth === todayJ.jm && this.viewYear === todayJ.jy;
            },

            dayLabel: function (day) { return day ? faDigits(day) : ''; },

            goToday: function () {
                this.viewYear = todayJ.jy;
                this.viewMonth = todayJ.jm;
                this.pick(todayJ.jd);
            },

            toggle: function () { this.open = !this.open; },

            pick: function (day) {
                if (!day) return;
                this.value = window.JalaliDate.jalaliToGregorianStr(this.viewYear, this.viewMonth, day);
                this.wire.set(this.field, this.value);
                this.open = false;
            },

            clear: function () {
                this.value = '';
                this.wire.set(this.field, '');
                this.open = false;
            },

            prevMonth: function () {
                this.viewMonth -= 1;
                if (this.viewMonth < 1) { this.viewMonth = 12; this.viewYear -= 1; }
            },

            nextMonth: function () {
                this.viewMonth += 1;
                if (this.viewMonth > 12) { this.viewMonth = 1; this.viewYear += 1; }
            },
        };
    };
})();
