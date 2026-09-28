<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * بکاپ روزانهٔ کامل دیتابیس — بخش ۱۴‑الف پلن («بکاپ روزانه + تست restore»).
 * از خودِ `mysqldump`/`mysql` XAMPP استفاده می‌کند (بدون پکیج جانبی) — فایل خروجی gzip در
 * storage/app/backups/ که خارج از public است. نگهداری فقط ۱۴ روز اخیر (بخش ۱۴‑الف را با حجم
 * محدود دیسک dev سازگار می‌کند)؛ برای production این عدد و مقصد (مثلاً S3) باید در تنظیمات واقعی
 * بازبینی شود — این‌جا صرفاً زیرساخت کار می‌کند.
 * تست restore واقعی: `php artisan backup:restore-test` (فایل جدا، چون تست restore باید روی یک
 * دیتابیس موقت جدا انجام شود، نه دیتابیس dev واقعی — مستند در همان دستور).
 */
class BackupDatabase extends Command
{
    protected $signature = 'backup:database {--keep-days=14 : چند روز اخیر نگه داشته شود}';

    protected $description = 'بکاپ کامل mysqldump از دیتابیس + پاک‌سازی بکاپ‌های قدیمی‌تر از N روز';

    public function handle(): int
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->info('sqlite (محیط تست) — mysqldump بی‌معناست، رد شد.');

            return self::SUCCESS;
        }

        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);

        $file = $dir.'/dasteyari-'.now()->format('Y-m-d_His').'.sql.gz';

        $config = config('database.connections.mysql');

        // بدون --routines/--triggers: پروژه هیچ Stored Procedure/Trigger‌ای ندارد (کل منطق در
        // لایهٔ اپلیکیشن است)، و روی نصب فعلی XAMPP MariaDB این دو پرچم به خطای «mysql.proc نسخهٔ
        // نامنطبق» (نیازمند mysql_upgrade) برمی‌خورند. **کشف مهم:** `mysqldump | gzip` بدون
        // pipefail حتی وقتی mysqldump با کد ۲ شکست بخورد باز هم کد خروج ۰ می‌دهد (چون شل فقط کد
        // خروج آخرین دستور پایپ یعنی gzip را برمی‌گرداند) — یعنی بکاپ می‌توانست ناقص/خراب باشد و
        // این دستور بی‌خبر «موفق» گزارش دهد. برای همین با `bash -o pipefail -c` اجرا می‌شود.
        $dumpCommand = sprintf(
            'mysqldump --host=%s --port=%s --user=%s %s --single-transaction %s | gzip > %s',
            escapeshellarg($config['host']),
            escapeshellarg((string) $config['port']),
            escapeshellarg($config['username']),
            $config['password'] !== '' ? '--password='.escapeshellarg($config['password']) : '',
            escapeshellarg($config['database']),
            escapeshellarg($file)
        );

        $result = Process::timeout(300)->run(['bash', '-o', 'pipefail', '-c', $dumpCommand]);

        if (! $result->successful()) {
            $this->error('بکاپ‌گیری شکست خورد: '.$result->errorOutput());

            return self::FAILURE;
        }

        $sizeKb = round(filesize($file) / 1024, 1);
        $this->info("✓ بکاپ ذخیره شد: {$file} ({$sizeKb} کیلوبایت)");

        $this->cleanupOld((int) $this->option('keep-days'), $dir);

        return self::SUCCESS;
    }

    private function cleanupOld(int $keepDays, string $dir): void
    {
        $cutoff = now()->subDays($keepDays)->timestamp;
        $removed = 0;

        foreach (File::files($dir) as $f) {
            if (Str::endsWith($f->getFilename(), '.sql.gz') && $f->getMTime() < $cutoff) {
                File::delete($f->getPathname());
                $removed++;
            }
        }

        if ($removed > 0) {
            $this->info("{$removed} بکاپ قدیمی‌تر از {$keepDays} روز حذف شد.");
        }
    }
}
