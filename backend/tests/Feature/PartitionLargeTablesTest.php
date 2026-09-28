<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * این دستور واقعاً روی MySQL/MariaDB تست شده (نه این‌جا): هر ۴ جدول با یک کلون یک‌بارمصرف از
 * دیتابیس dev واقعی امتحان شدند — `case_events`/`activity_log`/`broadcast_recipients` هر سه با
 * موفقیت پارتیشن شدند (۱۳ پارتیشن، تایید شده با information_schema.PARTITIONS)، و `transactions`
 * به‌درستی به‌خاطر UNIQUE INDEX روی `ref` (که created_at ندارد) مسدود شد — مستند در
 * app/Console/Commands/PartitionLargeTables.php. این‌جا فقط گاردهای ورودی (جدول نامعتبر، sqlite)
 * که مستقل از موتور واقعی دیتابیس‌اند تست می‌شوند.
 */
class PartitionLargeTablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejects_table_not_in_allowlist(): void
    {
        $this->artisan('partitions:setup-large-table', ['table' => 'users'])
            ->assertFailed();
    }

    public function test_refuses_to_run_on_sqlite(): void
    {
        $this->artisan('partitions:setup-large-table', ['table' => 'case_events'])
            ->assertFailed();
    }
}
