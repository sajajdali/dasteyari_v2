<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * پارتیشن‌بندی ماهانه — بخش ۳.۱۱ پلن، فاز ۱۴‑الف.
 *
 * پلن ۵ جدول را برای پارتیشن ماهانه مشخص کرده: case_events، activity_log، sms_log،
 * broadcast_recipients، transactions. اما InnoDB (چه در MySQL چه در MariaDB — نسخهٔ واقعی این
 * پروژه ۱۰.۴) **به جدول پارتیشن‌شده هیچ کلید خارجی، نه ورودی نه خروجی، اجازه نمی‌دهد** — این یک
 * محدودیت بنیادی موتور است، نه تنظیم قابل‌تغییر. بررسی FK واقعی این ۵ جدول:
 *   - `sms_log`: **بدون هیچ کلید خارجی** — تنها جدول از این ۵ که واقعاً بدون هیچ عارضه‌ای پارتیشن می‌شود.
 *   - `case_events`: خروجی `reason_id`→reasons، `admin_id`→users.
 *   - `activity_log`: خروجی `user_id`→users.
 *   - `broadcast_recipients`: خروجی `broadcast_id`→broadcasts، `user_id`→users.
 *   - `transactions`: ورودی از `allocations.transaction_id`.
 * با تایید کارفرما (فاز ۱۴)، فقط `sms_log` همین‌جا واقعاً پارتیشن شد؛ برای ۴ جدول دیگر یک دستور
 * Artisan آماده و مستند (`app/Console/Commands/PartitionLargeTables.php`) نوشته شد که **دستی و با
 * تایید صریح** اجرا می‌شود، نه در مسیر migrate عادی — چون حذف آن ۴ کلید خارجی برگشت‌پذیر نیست و با
 * حجم فعلی داده (چند ده ردیف، نه ۱۰هزار) هیچ سودی ندارد.
 *
 * `sms_log` اصلاً ستون `created_at` نداشت (فقط `sent_at` nullable — پیامک صف‌شده هنوز ارسال نشده)؛
 * چون ستون پارتیشن نباید nullable باشد، یک `created_at` واقعی (`useCurrent`) اضافه شد و پارتیشن
 * روی همان انجام می‌شود، نه `sent_at`.
 *
 * **کشف حین اجرا:** `PARTITION BY RANGE (TO_DAYS(created_at))` با خطای MariaDB #۱۴۸۶
 * («Constant, random or timezone-dependent expressions… not allowed») شکست خورد — چون ستون از نوع
 * `TIMESTAMP` است (نه `DATETIME`) و `TIMESTAMP` بر مبنای timezone سشن تبدیل می‌شود، پس هر تابعی
 * مثل `TO_DAYS()`/`YEAR()` رویش را موتور «وابسته به timezone» می‌داند و رد می‌کند. راه‌حل مستند
 * خودِ MySQL/MariaDB برای ستون‌های TIMESTAMP دقیقاً همین‌جا: تابع پارتیشن باید `UNIX_TIMESTAMP(col)`
 * باشد (که صراحتاً از این قاعده مستثناست)، با مرزهای `UNIX_TIMESTAMP('YYYY-MM-DD')` به‌جای `TO_DAYS()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return; // پارتیشن‌بندی MySQL/MariaDB‌محور است؛ در تست (sqlite) بی‌معناست.
        }

        Schema::table('sms_log', function ($table) {
            if (! Schema::hasColumn('sms_log', 'created_at')) {
                $table->timestamp('created_at')->useCurrent()->after('sent_at');
            }
        });

        // ستون auto_increment باید در کلید اصلی بماند؛ کلید پارتیشن هم باید عضو کلید اصلی باشد.
        DB::statement('ALTER TABLE sms_log DROP PRIMARY KEY, ADD PRIMARY KEY (id, created_at)');

        $partitions = $this->monthlyPartitionSql(now());

        DB::statement("ALTER TABLE sms_log PARTITION BY RANGE (UNIX_TIMESTAMP(created_at)) ($partitions)");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE sms_log REMOVE PARTITIONING');
        DB::statement('ALTER TABLE sms_log DROP PRIMARY KEY, ADD PRIMARY KEY (id)');
    }

    /** ۱۲ پارتیشن ماهانه از ماه جاری + یک پارتیشن سرریز (p_future) — بخش مشترک با دستور نگهداری. */
    private function monthlyPartitionSql(\DateTimeInterface $start): string
    {
        $parts = [];
        $cursor = \Carbon\Carbon::parse($start)->startOfMonth();

        for ($i = 0; $i < 12; $i++) {
            $boundary = $cursor->copy()->addMonthNoOverflow()->format('Y-m-d');
            $parts[] = "PARTITION p{$cursor->format('Ym')} VALUES LESS THAN (UNIX_TIMESTAMP('{$boundary}'))";
            $cursor->addMonthNoOverflow();
        }

        $parts[] = 'PARTITION p_future VALUES LESS THAN MAXVALUE';

        return implode(', ', $parts);
    }
};
