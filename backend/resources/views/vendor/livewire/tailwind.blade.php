{{--
    نمای صفحه‌بندی سراسری — جایگزین نمای پیش‌فرض تیلویندی لایوایر (که چون این پروژه اصلاً Tailwind
    ندارد بی‌استایل/شکسته رندر می‌شد، بخش «قیافش خرابه» گزارش‌شده روی /cases). استایل و متن دقیقاً از
    design/پروندهها.dc.html (prevStyle/nextStyle/pageBtns) کپی شده. با Paginator::defaultView() در
    AppServiceProvider::boot() سراسری ثبت شده، پس همهٔ ۱۲ فراخوانی ->links() در کل پروژه (سایت عمومی
    و پنل مدیریت) یک‌جا همین ظاهر را می‌گیرند، نه نیازی به ویرایش تک‌تک فایل‌ها.

    اسکرول نرم به ابتدای لیست — درخواست کارفرما بعد از رفع ظاهر: چون این ویو مشترک پشت ۱۲ کامپوننت
    کاملاً متفاوت است (بدون تگ/id مشترکی که به هرکدام اضافه کنیم)، هدف اسکرول با یک الگوی ساختاری
    مشترک واقعی پیدا می‌شود: در همهٔ ۱۲ فایل، بلوکی که `{{ $rows->links() }}` را صدا می‌زند بلافاصله
    بعد از خودِ گرید/جدول نتایج می‌آید — یعنی «همسایهٔ قبلی» ظرف صفحه‌بندی همیشه همان چیزی است که باید
    اسکرول شود. Alpine روی خودِ `<nav>` (نه هر دکمه جدا) با event delegation گوش می‌دهد، چون Livewire v3
    هر ریشهٔ کامپوننت را ضمنی در یک کامپوننت Alpine می‌پیچد و `x-on` بدون `x-data` صریح هم کار می‌کند
    (نکتهٔ Alpine بخش «سه باگ» AGENTS.md).
--}}
@if ($paginator->hasPages())
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        $window = collect(range(max(1, $current - 2), min($last, $current + 2)));
        if (! $window->contains(1)) {
            $window->prepend(1);
        }
        if (! $window->contains($last)) {
            $window->push($last);
        }
    @endphp
    <nav role="navigation" aria-label="Pagination Navigation" x-on:click="const wrap = $el.parentElement; const target = (wrap && wrap.previousElementSibling) || $el; target.scrollIntoView({behavior:'smooth', block:'start'})" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;justify-content:center;padding-top:8px">
        @if ($paginator->onFirstPage())
            <span style="height:40px;padding:0 14px;border-radius:11px;font-size:13px;font-weight:700;border:1.5px solid #E3E6EA;background:#fff;color:#C9CDD3;display:flex;align-items:center">→ قبلی</span>
        @else
            <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" style="height:40px;padding:0 14px;border-radius:11px;font-size:13px;font-weight:700;cursor:pointer;border:1.5px solid #E3E6EA;background:#fff;color:#23262B">→ قبلی</button>
        @endif

        <div style="display:flex;gap:6px;flex-wrap:wrap">
            @php $prev = null; @endphp
            @foreach ($window as $page)
                @if ($prev !== null && $page - $prev > 1)
                    <span style="min-width:40px;height:40px;display:flex;align-items:center;justify-content:center;font-size:13.5px;color:#9AA0A8">…</span>
                @endif
                @if ($page == $current)
                    <span wire:key="paginator-{{ $paginator->getPageName() }}-page{{ $page }}" style="min-width:40px;height:40px;padding:0 12px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:13.5px;font-weight:800;border:1.5px solid #F4511E;background:#F4511E;color:#fff">{{ faDigits($page) }}</span>
                @else
                    <button type="button" wire:key="paginator-{{ $paginator->getPageName() }}-page{{ $page }}" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" style="min-width:40px;height:40px;padding:0 12px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:13.5px;font-weight:800;cursor:pointer;border:1.5px solid #E3E6EA;background:#fff;color:#5A6169">{{ faDigits($page) }}</button>
                @endif
                @php $prev = $page; @endphp
            @endforeach
        </div>

        @if ($paginator->hasMorePages())
            <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" style="height:40px;padding:0 14px;border-radius:11px;font-size:13px;font-weight:700;cursor:pointer;border:1.5px solid #E3E6EA;background:#fff;color:#23262B">بعدی ←</button>
        @else
            <span style="height:40px;padding:0 14px;border-radius:11px;font-size:13px;font-weight:700;border:1.5px solid #E3E6EA;background:#fff;color:#C9CDD3;display:flex;align-items:center">بعدی ←</span>
        @endif

        <span style="font-size:12.5px;color:#9AA0A8;width:100%;text-align:center">صفحه {{ faDigits($current) }} از {{ faDigits($last) }}</span>
    </nav>
@endif
