<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * رانبوک (runbook) پارتیشن‌بندی ۴ جدول باقی‌ماندهٔ بخش ۳.۱۱ پلن — بخش ۱۴‑الف.
 *
 * پس‌زمینه کامل در database/migrations/2026_09_10_120000_partition_sms_log_table.php آمده: از ۵
 * جدولی که پلن برای پارتیشن ماهانه مشخص کرده، فقط `sms_log` بدون کلید خارجی بود و واقعاً پارتیشن
 * شد. این ۴ تای دیگر — `case_events`, `activity_log`, `broadcast_recipients`, `transactions` —
 * همه حداقل یک کلید خارجی (ورودی یا خروجی) دارند و InnoDB به جدول دارای هر نوع FK اجازهٔ پارتیشن
 * نمی‌دهد؛ تنها راه، حذف کامل آن قیدها از موتور دیتابیس است.
 *
 * **این دستور عمداً هرگز خودکار اجرا نمی‌شود** — نه در مسیر migrate، نه در routes/console.php
 * زمان‌بندی شده. طبق تایید صریح کارفرما (فاز ۱۴)، تا وقتی حجم واقعی این جدول‌ها به ده‌ها هزار ردیف
 * نرسیده هیچ سودی ندارد و حذف FK بازگشت‌پذیر نیست (فقط با migrate:rollback روی همین migration که
 * دستی نوشته می‌شود، نه با یک دستور آماده). با پرچم `--yes` صریح باید دستی اجرا شود:
 *
 *   php artisan partitions:setup-large-table case_events --yes
 *
 * بدون `--yes` فقط برنامهٔ کار (کدام FK حذف می‌شود، آیا ستون created_at باید اضافه شود) چاپ می‌شود،
 * هیچ تغییری اعمال نمی‌گردد. FKها با information_schema پویا کشف می‌شوند (نه هاردکد)، هم خروجیِ خودِ
 * جدول هم هر FK ورودی از جدول دیگر که به این جدول اشاره دارد (مثل `allocations.transaction_id` که
 * برای پارتیشن `transactions` هم باید حذف شود).
 *
 * **بعد از اجرا:** یکپارچگی ارجاعی این جدول‌ها دیگر توسط دیتابیس تضمین نمی‌شود، فقط توسط کد اپلیکیشن
 * (مثل ولیدیشن `belongsTo`/Observer). این هزینهٔ واقعی این تصمیم است، نه یک عارضهٔ جانبی قابل‌اغماض.
 *
 * **کشف حین آزمایش دستی (روی یک کلون یک‌بارمصرف، نه دیتابیس dev — بخش «تست واقعی» پایین‌تر):** دو
 * مانع دیگر هم کشف شد که مستند نبودند:
 *   ۱) `transactions.created_at` (از `$table->timestamps()` عمومی) برخلاف `case_events`/
 *      `activity_log` (که از ابتدا `useCurrent()` دارند) NULLABLE و بدون DEFAULT است — افزودنش به
 *      PRIMARY KEY با خطای MySQL #۱۰۶۷ («Invalid default value») شکست می‌خورد؛ باید قبلش با
 *      `MODIFY ... NOT NULL DEFAULT CURRENT_TIMESTAMP` اصلاح شود (بعد از چک نبودِ ردیف NULL).
 *   ۲) **مانع بازدارنده برای `transactions`:** ستون `ref` یک `UNIQUE INDEX` مستقل دارد که شامل
 *      `created_at` نیست؛ MySQL/MariaDB صریحاً اجازه نمی‌دهد هیچ UNIQUE INDEXای (نه فقط PRIMARY KEY)
 *      بدون تمام ستون‌های تابع پارتیشن باقی بماند (خطای #۱۵۰۳). این دستور چنین ایندکسی را **پیدا و
 *      گزارش می‌کند ولی خودش تغییرش نمی‌دهد** — چون گسترش `ref` به `(ref, created_at)` یکتایی‌اش را
 *      از «سراسری» به «در همان لحظه» ضعیف می‌کند (در عمل بی‌خطر چون `ref` یک ULID تصادفی است، ولی
 *      این یک تصمیم معنایی است که باید آگاهانه و دستی گرفته شود، نه خودکار). یعنی `transactions`
 *      فعلاً **حتی با این رانبوک هم قابل‌پارتیشن نیست** تا این ایندکس دستی حل شود.
 */
class PartitionLargeTables extends Command
{
    protected $signature = 'partitions:setup-large-table
        {table : یکی از case_events, activity_log, broadcast_recipients, transactions}
        {--yes : تایید صریح اجرای واقعی (حذف کلید خارجی + پارتیشن‌بندی) — بدون این پرچم فقط dry-run}
        {--connection= : نام کانکشن دیتابیس (پیش‌فرض: کانکشن پیش‌فرض اپ — برای تست روی یک دیتابیس موقت قابل‌override است)}';

    protected $description = 'رانبوک دستی پارتیشن‌بندی ماهانهٔ یکی از ۴ جدول دارای FK (اجرای دائمی حذف کلید خارجی)';

    private const ALLOWED_TABLES = ['case_events', 'activity_log', 'broadcast_recipients', 'transactions'];

    public function handle(): int
    {
        $table = $this->argument('table');

        if (! in_array($table, self::ALLOWED_TABLES, true)) {
            $this->error('جدول مجاز نیست. یکی از: '.implode(', ', self::ALLOWED_TABLES));

            return self::FAILURE;
        }

        $connection = DB::connection($this->option('connection') ?: config('database.default'));

        if ($connection->getDriverName() === 'sqlite') {
            $this->error('این دستور فقط روی MySQL/MariaDB معنا دارد.');

            return self::FAILURE;
        }

        $database = $connection->getDatabaseName();
        $needsCreatedAt = ! Schema::connection($connection->getName())->hasColumn($table, 'created_at');
        $foreignKeys = $this->discoverForeignKeys($connection, $database, $table);

        $this->info("جدول هدف: {$table}");
        $this->line($needsCreatedAt
            ? '- ستون created_at وجود ندارد، اضافه خواهد شد.'
            : '- ستون created_at از قبل موجود است.');

        if ($foreignKeys === []) {
            $this->warn('هیچ کلید خارجی‌ای پیدا نشد — این جدول احتمالاً قبلاً پارتیشن شده یا اصلاً FK نداشته (بررسی کنید).');
        } else {
            $this->line('- کلیدهای خارجی زیر برای همیشه حذف می‌شوند:');
            foreach ($foreignKeys as $fk) {
                $this->line("    {$fk['table']}.{$fk['column']} → حذف قید {$fk['name']}");
            }
        }

        $conflictingUniques = $this->discoverConflictingUniqueIndexes($connection, $database, $table);

        if ($conflictingUniques !== []) {
            $this->error('این جدول قابل‌پارتیشن نیست: UNIQUE INDEX زیر شامل ستون created_at نیست (الزام MySQL #1503):');
            foreach ($conflictingUniques as $name => $columns) {
                $this->line('    '.$name.' ('.implode(', ', $columns).')');
            }
            $this->line('باید دستی یا این ایندکس را حذف کنید یا created_at را به آن اضافه کنید (تصمیم معنایی، این دستور خودکار انجامش نمی‌دهد).');

            return self::FAILURE;
        }

        if (! $this->option('yes')) {
            $this->newLine();
            $this->comment('این فقط dry-run بود — چیزی تغییر نکرد. برای اجرای واقعی --yes را اضافه کنید.');

            return self::SUCCESS;
        }

        if (! $this->confirm("مطمئنید؟ این عملیات کلید خارجی {$table} را برای همیشه حذف می‌کند و برگشت‌ندادنی است.")) {
            $this->info('لغو شد.');

            return self::SUCCESS;
        }

        foreach ($foreignKeys as $fk) {
            $connection->statement("ALTER TABLE `{$fk['table']}` DROP FOREIGN KEY `{$fk['name']}`");
            $this->info("✓ قید {$fk['name']} حذف شد.");
        }

        if ($needsCreatedAt) {
            Schema::connection($connection->getName())->table($table, function ($t) {
                $t->timestamp('created_at')->useCurrent();
            });
            $this->info('✓ ستون created_at اضافه شد.');
        } else {
            // ستون PRIMARY KEY باید NOT NULL با DEFAULT معتبر باشد. `transactions.created_at` از
            // `$table->timestamps()` عمومی می‌آید که NULLABLE و بدون DEFAULT است (برخلاف
            // case_events/activity_log که از ابتدا با `useCurrent()` ساخته شده‌اند) — بدون این
            // MODIFY، خودِ `ADD PRIMARY KEY` با خطای MySQL #1067 («Invalid default value») شکست
            // می‌خورد. قبل از coerce کردن به NOT NULL، وجود ردیف با created_at خالی چک می‌شود تا
            // این عملیات هیچ داده‌ای را بی‌صدا عوض نکند.
            $nullCount = $connection->table($table)->whereNull('created_at')->count();

            if ($nullCount > 0) {
                $this->error("{$nullCount} ردیف {$table} مقدار created_at خالی دارند — قبل از پارتیشن‌بندی باید backfill شوند.");

                return self::FAILURE;
            }

            $connection->statement("ALTER TABLE `{$table}` MODIFY `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
        }

        $connection->statement("ALTER TABLE `{$table}` DROP PRIMARY KEY, ADD PRIMARY KEY (id, created_at)");

        $partitions = $this->monthlyPartitionSql(now());
        $connection->statement("ALTER TABLE `{$table}` PARTITION BY RANGE (UNIX_TIMESTAMP(created_at)) ({$partitions})");

        $this->info("✓ {$table} با موفقیت پارتیشن شد.");
        $this->warn('یکپارچگی ارجاعی این جدول از این پس فقط توسط کد اپلیکیشن تضمین می‌شود، نه دیتابیس.');

        return self::SUCCESS;
    }

    /** @return array<int,array{table:string,column:string,name:string}> */
    private function discoverForeignKeys($connection, string $database, string $table): array
    {
        $rows = $connection->select('
            SELECT TABLE_NAME AS `table`, COLUMN_NAME AS `column`, CONSTRAINT_NAME AS name
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ?
              AND REFERENCED_TABLE_NAME IS NOT NULL
              AND (TABLE_NAME = ? OR REFERENCED_TABLE_NAME = ?)
        ', [$database, $table, $table]);

        return array_map(fn ($r) => ['table' => $r->table, 'column' => $r->column, 'name' => $r->name], $rows);
    }

    /** @return array<string,array<int,string>> نام ایندکس => لیست ستون‌ها، فقط UNIQUEهایی که created_at ندارند (PRIMARY مستثناست، چون خودمان اصلاحش می‌کنیم). */
    private function discoverConflictingUniqueIndexes($connection, string $database, string $table): array
    {
        $rows = $connection->select('
            SELECT INDEX_NAME AS name, COLUMN_NAME AS `column`
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND NON_UNIQUE = 0 AND INDEX_NAME != \'PRIMARY\'
            ORDER BY INDEX_NAME, SEQ_IN_INDEX
        ', [$database, $table]);

        $indexes = [];

        foreach ($rows as $row) {
            $indexes[$row->name][] = $row->column;
        }

        return array_filter($indexes, fn ($columns) => ! in_array('created_at', $columns, true));
    }

    /** همان الگوی migration 2026_09_10_120000 — ۱۲ پارتیشن ماهانه + یک پارتیشن سرریز. */
    private function monthlyPartitionSql(\DateTimeInterface $start): string
    {
        $parts = [];
        $cursor = Carbon::parse($start)->startOfMonth();

        for ($i = 0; $i < 12; $i++) {
            $boundary = $cursor->copy()->addMonthNoOverflow()->format('Y-m-d');
            $parts[] = "PARTITION p{$cursor->format('Ym')} VALUES LESS THAN (UNIX_TIMESTAMP('{$boundary}'))";
            $cursor->addMonthNoOverflow();
        }

        $parts[] = 'PARTITION p_future VALUES LESS THAN MAXVALUE';

        return implode(', ', $parts);
    }
}
