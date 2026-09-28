{{--
    انتخاب‌گر تاریخ شمسی سراسری — قاعدهٔ پروژه: هر انتخاب تاریخ در کل سایت باید تقویم جلالی نشان دهد
    و مقداری که به Livewire (`$field`) می‌رود همیشه میلادی ISO (Y-m-d) باشد. الگوریتم تبدیل در
    public/js/jalali-date.js عیناً هم‌خانواده با پکیج سرور morilog/jalali است (تست‌شده، بدون اختلاف).

    props:
      $field       نام پراپرتی Livewire میزبان (رشتهٔ Y-m-d یا خالی) — الزامی
      $label       برچسب بالای فیلد (اختیاری)
      $placeholder متن جای‌خالی وقتی مقداری انتخاب نشده (پیش‌فرض «انتخاب تاریخ…»)

    نمونه: <x-partials.jalali-date field="startsAt" label="تاریخ شروع" />
--}}
@props(['field', 'label' => null, 'placeholder' => 'انتخاب تاریخ…'])
<div wire:ignore>
    <div x-data="jalaliPicker()" x-init="init($wire, @js($field))" style="position:relative;display:flex;flex-direction:column;gap:7px">
        @if ($label)
            <span style="font-size:12.5px;font-weight:700;color:#4B5158">{{ $label }}</span>
        @endif

        <div @click="toggle()" style="height:46px;border:1.5px solid #E7E9EC;border-radius:12px;padding:0 13px;font-size:13px;font-family:inherit;display:flex;align-items:center;gap:8px;cursor:pointer;background:#fff">
            <span style="color:#A9AEB6;font-size:14px">📅</span>
            <span x-text="display || '{{ $placeholder }}'" :style="display ? 'color:#23262B;font-weight:700;flex:1' : 'color:#A9AEB6;flex:1'"></span>
            <span x-show="display" x-cloak @click.stop="clear()" style="color:#9AA0A8;font-size:13px;cursor:pointer">✕</span>
        </div>

        <div x-show="open" x-cloak @click.outside="open = false" style="position:absolute;top:100%;margin-top:6px;z-index:40;width:min(300px,100%);background:#fff;border:1px solid #EAECEF;border-radius:16px;box-shadow:0 20px 45px -20px rgba(0,0,0,.35);padding:14px">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
                <span @click="nextMonth()" style="width:30px;height:30px;border-radius:9px;border:1px solid #EDEEF1;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:13px">←</span>
                <span x-text="monthLabel" style="flex:1;text-align:center;font-size:13px;font-weight:800"></span>
                <span @click="prevMonth()" style="width:30px;height:30px;border-radius:9px;border:1px solid #EDEEF1;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:13px">→</span>
            </div>
            <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;margin-bottom:6px">
                <template x-for="w in weekdays" :key="w">
                    <span x-text="w" style="text-align:center;font-size:11px;font-weight:800;color:#9AA0A8;padding:4px 0"></span>
                </template>
            </div>
            <template x-for="(row, ri) in weeks" :key="ri">
                <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;margin-bottom:4px">
                    <template x-for="(day, ci) in row" :key="ci">
                        <span
                            x-text="dayLabel(day)"
                            @click="pick(day)"
                            :style="'height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;' + (!day ? 'visibility:hidden' : 'cursor:pointer;') + (isSelected(day) ? 'background:#F4511E;color:#fff' : (isToday(day) ? 'background:#FEF1EC;color:#D8420F' : 'color:#3A4048'))"
                        ></span>
                    </template>
                </div>
            </template>
            <div @click="goToday()" style="margin-top:8px;text-align:center;font-size:12px;font-weight:700;color:#F4511E;cursor:pointer;padding-top:8px;border-top:1px solid #F4F5F7">امروز</div>
        </div>
    </div>
</div>
