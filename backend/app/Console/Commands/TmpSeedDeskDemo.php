<?php

namespace App\Console\Commands;

use App\Models\CampaignSupporter;
use App\Models\CaseEvent;
use App\Models\CaseRequest;
use App\Models\Donor;
use App\Models\Needy;
use App\Models\NeedGroup;
use App\Models\Pledge;
use App\Models\Referral;
use App\Models\Support;
use App\Models\SupportFollowup;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\CaseEventNotification;
use Illuminate\Console\Command;

/**
 * دستور موقت — فقط برای پر کردن میز کار مدیر با دادهٔ واقعی و متنوع تا کارفرما همهٔ تب‌ها را در
 * مرورگر ببیند. بعد از تایید کارفرما باید حذف شود (همان قرارداد `tmp:load-test-requests` فاز ۱۴‑الف).
 */
class TmpSeedDeskDemo extends Command
{
    protected $signature = 'tmp:seed-desk-demo';

    protected $description = 'پر کردن موقت میز کار مدیر با دادهٔ واقعی برای هر ۴ تب + کارت‌های آماری';

    public function handle(): void
    {
        $admin = User::where('phone', '09122978167')->first();
        if (! $admin) {
            $this->error('ادمین نمونه یافت نشد.');

            return;
        }

        $other = User::firstOrCreate(
            ['phone' => '09121230001'],
            ['kind' => 'staff', 'name' => 'ارمغان خدایی', 'active' => true, 'password' => bcrypt('1234')]
        );
        if (! $other->hasAnyRole(['super-admin', 'case-officer'])) {
            $other->assignRole('case-officer');
        }

        // ۱) ارجاع‌شده به من
        $requests = CaseRequest::inRandomOrder()->limit(2)->get();
        foreach ($requests as $i => $r) {
            Referral::create([
                'subject_type' => 'request', 'subject_id' => $r->id,
                'from_admin_id' => $other->id, 'to_admin_id' => $admin->id,
                'note' => $i === 0 ? 'لطفاً مدارک این پرونده را بررسی و تایید کنید.' : 'خانواده تماس گرفته و درخواست پیگیری فوری دارد.',
                'due_at' => now()->addDays($i + 1),
            ]);
        }
        $this->info('۲ ارجاع ساخته شد.');

        // ۲) پیام‌های مدیریت (اعلان‌های واقعی)
        foreach ($requests as $i => $r) {
            $event = CaseEvent::create([
                'subject_type' => 'request', 'subject_id' => $r->id, 'action_key' => 'case.publish',
                'description' => $i === 0 ? 'پروندهٔ ' . ($r->needy->name ?? '') . ' منتشر شد.' : 'یادداشت جدیدی برای این پرونده ثبت شد.',
                'admin_id' => $other->id, 'created_at' => now()->subHours($i + 1),
            ]);
            $admin->notify(new CaseEventNotification($event));
        }
        $this->info('۲ اعلان ساخته شد.');

        // ۳) تعیین تکلیف و جایگزینی — سه حالت واقعی (isOpen / isFollowup / isPublished)
        $group1 = NeedGroup::firstOrCreate(['title' => 'کمک‌هزینه معیشت'], ['active' => true, 'order' => 1]);
        $group2 = NeedGroup::firstOrCreate(['title' => 'مسکن و اجاره'], ['active' => true, 'order' => 2]);
        $group3 = NeedGroup::firstOrCreate(['title' => 'هزینه درمان'], ['active' => true, 'order' => 3]);

        $needy1 = Needy::factory()->create(['name' => 'فاطمه احمدی', 'city' => 'تهران', 'code' => 'BN-' . random_int(800000, 899999)]);
        $req1 = CaseRequest::factory()->for($needy1)->create(['priority' => 2, 'need_group_id' => $group1->id]);
        $donor1 = Donor::factory()->create();
        $donor1->user->update(['name' => 'علیرضا مهدوی']);
        $support1 = Support::create([
            'donor_id' => $donor1->id, 'request_id' => $req1->id, 'plan' => 'monthly', 'amount' => 7_000_000,
            'started_at' => now()->subMonths(7), 'ended_at' => now()->subDays(2), 'status' => 'ended',
            'given_total' => 18_000_000, 'months_count' => 7,
        ]);
        SupportFollowup::create(['support_id' => $support1->id, 'admin_id' => $admin->id, 'grace_days' => 5, 'due_at' => now()->addDays(3), 'created_at' => now(), 'note' => 'خیر قول داد تا سه روز دیگر تماس بگیرد و تصمیم نهایی را اعلام کند.']);
        CaseEvent::create(['subject_type' => 'support', 'subject_id' => $support1->id, 'action_key' => 'support.followup', 'description' => 'با خیر تماس گرفتم، قول پیگیری داد.', 'admin_id' => $admin->id, 'created_at' => now()]);

        $needy2 = Needy::factory()->create(['name' => 'سکینه مرادی', 'city' => 'اهواز', 'code' => 'BN-' . random_int(800000, 899999)]);
        $req2 = CaseRequest::factory()->for($needy2)->create(['priority' => 2, 'need_group_id' => $group2->id]);
        $donor2 = Donor::factory()->create();
        $donor2->user->update(['name' => 'شرکت پایا صنعت']);
        Support::create([
            'donor_id' => $donor2->id, 'request_id' => $req2->id, 'plan' => 'monthly', 'amount' => 6_000_000,
            'started_at' => now()->subMonths(11), 'ended_at' => now()->subDays(5), 'status' => 'ended',
            'given_total' => 66_000_000, 'months_count' => 11,
        ]);

        $needy3 = Needy::factory()->create(['name' => 'رضا نیک‌پور', 'city' => 'کرج', 'code' => 'BN-' . random_int(800000, 899999)]);
        $req3 = CaseRequest::factory()->for($needy3)->create(['priority' => 3, 'need_group_id' => $group3->id]);
        $donor3 = Donor::factory()->create();
        $donor3->user->update(['name' => 'سمیرا فتحی']);
        $support3 = Support::create([
            'donor_id' => $donor3->id, 'request_id' => $req3->id, 'plan' => 'monthly', 'amount' => 3_200_000,
            'started_at' => now()->subMonths(5), 'ended_at' => now()->subDays(1), 'status' => 'ended',
            'given_total' => 16_000_000, 'months_count' => 5, 'reassignment_status' => 'public',
        ]);
        $this->info('۳ پروندهٔ صف تعیین تکلیف ساخته شد (open/followup/published).');

        // ۴) پشتیبانان برتر ماه — تراکنش واقعی این ماه از لینک اختصاصی
        $campaign = \App\Models\Campaign::inRandomOrder()->first();
        if ($campaign) {
            $supporter = CampaignSupporter::create(['campaign_id' => $campaign->id, 'name' => 'سارا رستمی', 'role' => '@sara.life', 'followers' => 124_000]);
            $donorS = Donor::factory()->create();
            Transaction::create([
                'kind' => 'in', 'donor_id' => $donorS->id, 'campaign_id' => $campaign->id, 'campaign_supporter_id' => $supporter->id,
                'amount' => 15_000_000, 'status' => 'ok', 'way' => 'gateway', 'paid_at' => now(),
            ]);
            $this->info('۱ پشتیبان با تراکنش واقعی این ماه ساخته شد.');
        }

        // ۵) در انتظار تایید
        for ($i = 0; $i < 3; $i++) {
            $n = Needy::factory()->create(['code' => 'BN-' . random_int(700000, 799999)]);
            CaseRequest::factory()->for($n)->create(['status' => 'pending_review', 'amount' => random_int(5, 50) * 1_000_000]);
        }
        $this->info('۳ پروندهٔ در انتظار تایید ساخته شد.');

        // ۶) کار امروز — موعد پرداخت‌ها (امروز/این‌هفته/معوق) + تقویم خیرین
        $dueOffsets = [-2, 0, 0, 1, 3, 7, -5];
        foreach ($dueOffsets as $offset) {
            $d = Donor::factory()->create();
            $n = Needy::factory()->create(['code' => 'BN-' . random_int(600000, 699999)]);
            $r = CaseRequest::factory()->for($n)->create(['status' => 'closed']);
            Pledge::create([
                'donor_id' => $d->id, 'request_id' => $r->id, 'status' => 'pending',
                'due_at' => now()->addDays($offset), 'amount' => random_int(1, 10) * 1_000_000,
            ]);
        }
        $this->info('۷ تعهد پرداخت با موعدهای مختلف ساخته شد.');

        $this->newLine();
        $this->info('همه چیز آماده است. برای بازگشت به حالت اولیهٔ نمایشی بعداً: php artisan migrate:fresh --seed');
    }
}
