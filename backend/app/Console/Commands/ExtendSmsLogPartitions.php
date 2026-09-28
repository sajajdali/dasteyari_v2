<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * نگهداری پارتیشن‌های ماهانهٔ sms_log — بخش ۳.۱۱ پلن، فاز ۱۴‑الف.
 * پارتیشن‌بندی اولیه (migration 2026_09_10_120000) فقط ۱۲ ماه جلوتر + یک پارتیشن سرریز (p_future)
 * ساخت؛ بدون این دستور، بعد از آن ۱۲ ماه همهٔ ردیف‌های جدید در همان p_future تلنبار می‌شوند و
 * فایدهٔ پارتیشن (حذف/آرشیو سریع یک ماه) از بین می‌رود. باید ماهانه اجرا شود (`schedule:run` روزانه
 * کافی است، چون این دستور خودش idempotent است و اگر پارتیشن ماه بعد از قبل ساخته شده باشد کاری
 * نمی‌کند) — برای زمان‌بندی واقعی، `routes/console.php` را ببین.
 */
class ExtendSmsLogPartitions extends Command
{
    protected $signature = 'partitions:extend-sms-log {--months=3 : چند ماه جلوتر از آخرین پارتیشن موجود اضافه شود}';

    protected $description = 'افزودن پارتیشن‌های ماهانهٔ آیندهٔ sms_log (reorganize پارتیشن سرریز p_future)';

    public function handle(): int
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->info('sqlite — پارتیشن‌بندی بی‌معناست، رد شد.');

            return self::SUCCESS;
        }

        $existing = collect(DB::select("
            SELECT PARTITION_NAME FROM information_schema.PARTITIONS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sms_log' AND PARTITION_NAME != 'p_future'
        "))->pluck('PARTITION_NAME')->sort()->values();

        if ($existing->isEmpty()) {
            $this->error('جدول sms_log پارتیشن‌بندی نشده — ابتدا migration مربوطه را اجرا کنید.');

            return self::FAILURE;
        }

        $lastMonth = Carbon::createFromFormat('Ym', substr($existing->last(), 1))->startOfMonth();
        $monthsToAdd = (int) $this->option('months');

        $newPartitions = [];
        $cursor = $lastMonth->copy();

        for ($i = 0; $i < $monthsToAdd; $i++) {
            $cursor->addMonthNoOverflow();
            $boundary = $cursor->copy()->addMonthNoOverflow()->format('Y-m-d');
            $newPartitions[] = "PARTITION p{$cursor->format('Ym')} VALUES LESS THAN (UNIX_TIMESTAMP('{$boundary}'))";
        }

        $newPartitions[] = 'PARTITION p_future VALUES LESS THAN MAXVALUE';
        $sql = 'ALTER TABLE sms_log REORGANIZE PARTITION p_future INTO ('.implode(', ', $newPartitions).')';

        DB::statement($sql);

        $this->info("✓ {$monthsToAdd} پارتیشن جدید تا ".$cursor->format('Y-m')." اضافه شد.");

        return self::SUCCESS;
    }
}
