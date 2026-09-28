<?php

namespace Database\Seeders;

use App\Models\Broadcast;
use App\Models\CaseRequest;
use App\Models\Campaign;
use App\Models\CampaignCase;
use App\Models\DocRequest;
use App\Models\DocRequestItem;
use App\Models\Donor;
use App\Models\Needy;
use App\Models\Support;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * دادهٔ واقع‌نما برای فاز ۲ — بخش ۲‑ب پلن: ۲۰ نیازمند، ۱۵ خیر، ۳ کمپین، ۳۰ تراکنش،
 * ۱۰ حمایت فعال، ۳ منتقل‌شده، ۵ درخواست مدرک، ۸ وظیفه، ۲ اطلاع‌رسانی.
 * اجرای مجدد امن نیست (factory) — روی پایگاه‌دادهٔ تازه (migrate:fresh --seed) اجرا شود.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (Needy::count() > 0) {
            return; // قبلاً seed شده — از تکرار جلوگیری می‌کند
        }

        $staff = User::where('kind', 'staff')->first() ?? User::factory()->create(['kind' => 'staff']);

        // نیازمند نمونهٔ ثابت (۰۹۱۲۰۰۰۰۰۰۳) — ردیف Needy متناظرش تا فاز ۱۱ ساخته نشده بود (نکتهٔ فنی
        // UserSeeder)؛ چون این‌جا (نه UserSeeder) اجرا می‌شود، جلوتر از چک Needy::count() بالا رد
        // نمی‌شود و ۲۰ نیازمند نمایشی زیر را هم دوباره seed نمی‌کند.
        $demoNeedyUser = User::where('phone', '09120000003')->first();
        if ($demoNeedyUser) {
            Needy::updateOrCreate(
                ['user_id' => $demoNeedyUser->id],
                ['name' => $demoNeedyUser->name, 'code' => 'BN-000003', 'city' => 'تهران', 'province' => 'تهران']
            );
        }

        // ۲۰ نیازمند، هرکدام با یک پرونده (درخواست).
        // کشف فاز ۱۴‑ب: بدون `need_group_id` صریح این‌جا، هر ۲۰ پرونده با پیش‌فرض null فکتوری
        // (`CaseRequestFactory`) ساخته می‌شدند — یعنی فیلتر «گروه نیاز» در `⚡requests-table` و هر
        // شمارندهٔ گروه‌محور دیگر (مثل `⚡desk`) همیشه صفر برمی‌گرداند، با اینکه خودِ نیازمندها گروه
        // واقعی داشتند (`NeedyFactory`). این‌جا گروه پرونده از گروه نیازمندش به ارث می‌رسد — دقیقاً
        // رفتار واقعی مورد انتظار (نوع نیاز پرونده معمولاً همان گروه ثبت‌شدهٔ نیازمند است).
        $requests = Needy::factory()->count(20)->create()->map(
            fn (Needy $needy) => CaseRequest::factory()->for($needy)->create(['need_group_id' => $needy->need_group_id])
        );

        // ۱۵ خیر
        $donors = Donor::factory()->count(15)->create();

        // ۱۰ حمایت فعال — خیر و پرونده تصادفی
        Support::factory()->count(10)->create([
            'donor_id' => fn () => $donors->random()->id,
            'request_id' => fn () => $requests->random()->id,
        ]);

        // ۳ حمایت منتقل‌شده — به‌همراه رکورد transfers مطابق بخش ۸.۱ پلن
        Support::factory()->transferred()->count(3)->create([
            'donor_id' => fn () => $donors->random()->id,
            'request_id' => fn () => $requests->random()->id,
        ])->each(function (Support $support) use ($donors, $staff) {
            Transfer::create([
                'support_id' => $support->id,
                'request_id' => $support->request_id,
                'from_donor_id' => $support->donor_id,
                'to_donor_id' => null,
                'mode' => 'site',
                'reason_id' => null,
                'reason_text' => 'قطع حمایت خیر',
                'description' => 'انتقال خودکار به سایت پس از پایان حمایت — دادهٔ نمونه.',
                'admin_id' => $staff->id,
                'created_at' => now(),
                'given_snapshot' => $support->given_total,
                'months_snapshot' => $support->months_count,
            ]);
        });

        // ۳ کمپین + ۲ پرونده در هر کمپین
        $campaigns = Campaign::factory()->count(3)->create();
        foreach ($campaigns as $campaign) {
            foreach ($requests->random(2) as $request) {
                CampaignCase::create([
                    'campaign_id' => $campaign->id,
                    'request_id' => $request->id,
                    'share' => (int) round($request->amount * 0.4),
                    'added_by' => $staff->id,
                    'added_at' => now(),
                    'after_start' => false,
                ]);
            }
        }

        // ۳۰ تراکنش
        Transaction::factory()->count(30)->create([
            'donor_id' => fn () => $donors->random()->id,
            'request_id' => fn () => $requests->random()->id,
        ]);

        // ۵ درخواست مدرک، هرکدام با ۲ ردیف
        DocRequest::factory()->count(5)->create([
            'request_id' => fn () => $requests->random()->id,
            'created_by' => $staff->id,
        ])->each(function (DocRequest $docRequest) {
            DocRequestItem::factory()->count(2)->create(['doc_request_id' => $docRequest->id]);
        });

        // ۸ وظیفه
        Task::factory()->count(8)->create(['assignee_id' => $staff->id]);

        // ۲ اطلاع‌رسانی
        Broadcast::factory()->count(2)->create(['created_by' => $staff->id]);
    }
}
