<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;

/**
 * تست واقعی «restore» — نیمهٔ دوم الزام «بکاپ روزانه + تست restore» (پلن ۱۴‑الف).
 * فقط ساختن دامپ کافی نیست؛ این دستور آخرین بکاپ را روی یک دیتابیس موقت جدا (نه dev واقعی)
 * ری‌استور می‌کند، شمار ردیف هر جدول را با دیتابیس منبع مقایسه می‌کند، و در پایان دیتابیس موقت را
 * حذف می‌کند — پس هر بار اجرا بی‌اثر (idempotent) و بی‌خطر برای دادهٔ واقعی است.
 */
class BackupRestoreTest extends Command
{
    protected $signature = 'backup:restore-test';

    protected $description = 'ری‌استور آخرین بکاپ روی یک دیتابیس موقت + مقایسهٔ شمار ردیف هر جدول با منبع';

    public function handle(): int
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->info('sqlite (محیط تست) — تست restore بی‌معناست، رد شد.');

            return self::SUCCESS;
        }

        $config = config('database.connections.mysql');
        $sourceDb = $config['database'];
        $scratchDb = $sourceDb.'_restore_test';

        $dir = storage_path('app/backups');
        $latest = collect(File::files($dir))
            ->filter(fn ($f) => str_ends_with($f->getFilename(), '.sql.gz'))
            ->sortByDesc(fn ($f) => $f->getMTime())
            ->first();

        if (! $latest) {
            $this->error('هیچ بکاپی یافت نشد — ابتدا backup:database را اجرا کنید.');

            return self::FAILURE;
        }

        $this->info("استفاده از بکاپ: {$latest->getFilename()}");

        $mysqlBase = sprintf(
            'mysql --host=%s --port=%s --user=%s %s',
            escapeshellarg($config['host']),
            escapeshellarg((string) $config['port']),
            escapeshellarg($config['username']),
            $config['password'] !== '' ? '--password='.escapeshellarg($config['password']) : ''
        );

        $dropSql = "DROP DATABASE IF EXISTS `{$scratchDb}`";
        $createSql = "CREATE DATABASE `{$scratchDb}`";
        Process::run("{$mysqlBase} -e ".escapeshellarg("{$dropSql}; {$createSql}"));

        try {
            $restore = Process::timeout(300)->run(
                "gunzip < ".escapeshellarg($latest->getPathname())." | {$mysqlBase} ".escapeshellarg($scratchDb)
            );

            if (! $restore->successful()) {
                $this->error('ری‌استور شکست خورد: '.$restore->errorOutput());

                return self::FAILURE;
            }

            config(['database.connections.restore_test_scratch' => array_merge($config, [
                'database' => $scratchDb,
            ])]);

            $sourceTables = $this->tableRowCounts($sourceDb);
            $scratchTables = $this->tableRowCounts($scratchDb, 'restore_test_scratch');

            $mismatches = [];

            foreach ($sourceTables as $table => $count) {
                $scratchCount = $scratchTables[$table] ?? null;

                if ($scratchCount !== $count) {
                    $mismatches[] = "{$table}: منبع={$count} ری‌استورشده=".($scratchCount ?? 'وجود ندارد');
                }
            }

            if ($mismatches !== []) {
                $this->error('عدم تطابق در جدول‌های زیر یافت شد:');
                foreach ($mismatches as $line) {
                    $this->line("  - {$line}");
                }

                return self::FAILURE;
            }

            $this->info('✓ تست restore موفق: '.count($sourceTables).' جدول با شمار ردیف یکسان بازیابی شد.');

            return self::SUCCESS;
        } finally {
            DB::purge('restore_test_scratch');
            Process::run("{$mysqlBase} -e ".escapeshellarg($dropSql));
        }
    }

    /** @return array<string,int> */
    private function tableRowCounts(string $database, ?string $connection = null): array
    {
        $conn = $connection ? DB::connection($connection) : DB::connection();

        $tables = $conn->select(
            'SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?',
            [$database]
        );

        $counts = [];

        foreach ($tables as $table) {
            $counts[$table->name] = $conn->table($table->name)->count();
        }

        return $counts;
    }
}
