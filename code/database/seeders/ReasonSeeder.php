<?php

namespace Database\Seeders;

use App\Models\Reason;
use Illuminate\Database\Seeder;

/** برای هر ۲۶ کلید اقدام حداقل ۳ دلیل — بدون این، ActionModal باز نمی‌شود. */
class ReasonSeeder extends Seeder
{
    public function run(): void
    {
        $map = [
            'case.publish'           => ['مدارک کامل و تاییدشده', 'بازدید انجام شده', 'اولویت بالا'],
            'case.halt'              => ['عدم همکاری خانواده', 'اطلاعات نادرست', 'رفع نیاز', 'انتقال به نهاد دیگر'],
            'case.resume'            => ['ارائه مدارک تکمیلی', 'رفع ابهام', 'بازگشت همکاری'],
            'case.close'             => ['نیاز کامل تامین شد', 'انصراف خانواده', 'عدم امکان پیگیری'],
            'case.reject'            => ['خارج از حوزه فعالیت', 'اطلاعات نادرست', 'تکراری'],
            'case.doc_request'       => ['نقص مدارک هویتی', 'نیاز به سند درآمد', 'نیاز به مدرک پزشکی'],
            'doc.verify'             => ['مطابق اصل', 'تایید کارشناس بازدید'],
            'doc.reject'             => ['ناخوانا', 'منقضی', 'مربوط به شخص دیگر'],
            'support.transfer_site'  => ['قطع حمایت خیر', 'درخواست خیر', 'عدم پرداخت مستمر'],
            'support.transfer_donor' => ['هماهنگی با خیر جدید', 'تناسب بیشتر با ظرفیت خیر'],
            'support.followup'       => ['قول پرداخت', 'عدم پاسخ تلفن', 'درخواست مهلت'],
            'support.stop'           => ['انصراف خیر', 'عدم پرداخت مکرر', 'تغییر شرایط خیر'],
            'campaign.extend'        => ['عدم تکمیل هدف', 'درخواست حامیان'],
            'campaign.pause'         => ['بازبینی محتوا', 'مشکل درگاه'],
            'campaign.resume'        => ['رفع مشکل', 'تایید مدیر'],
            'campaign.close'         => ['هدف محقق شد', 'پایان مهلت', 'لغو کمپین'],
            'campaign.add_case'      => ['نیاز فوری جدید', 'ظرفیت باقی‌مانده کمپین'],
            'campaign.manual_pay'    => ['واریز به حساب', 'پرداخت نقدی', 'کارت به کارت'],
            'broadcast.pause'        => ['خطای متن', 'تصمیم مدیر', 'مشکل سرویس پیامک'],
            'broadcast.delete'       => ['ارسال اشتباه', 'تکراری'],
            'payment.manual'         => ['واریز بانکی', 'نقدی', 'کارت به کارت', 'چک'],
            'payment.reject'         => ['عدم تطابق مبلغ', 'رسید نامعتبر', 'تکراری'],
            'payout.register'        => ['پرداخت ماهانه', 'پرداخت اضطراری'],
            'donor.suspend'          => ['درخواست خیر', 'عدم پاسخ‌دهی'],
            'donor.block'            => ['تخلف', 'اطلاعات جعلی'],
            'user.suspend'           => ['پایان همکاری', 'تخلف انتظامی', 'مرخصی بلندمدت'],
        ];

        foreach ($map as $key => $texts) {
            foreach (array_values($texts) as $i => $text) {
                Reason::firstOrCreate(
                    ['action_key' => $key, 'text' => $text],
                    ['order' => $i, 'active' => true],
                );
            }
        }
    }
}
