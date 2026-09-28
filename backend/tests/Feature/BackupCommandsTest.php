<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * محیط تست PHPUnit روی sqlite است (phpunit.xml)، پس mysqldump/mysql واقعی این‌جا اجرا نمی‌شود —
 * فقط گارد «رد بی‌خطر روی sqlite» هر دو دستور تایید می‌شود. اجرای واقعی هردو دستور روی MariaDB
 * توسعه (بکاپ واقعی + ری‌استور واقعی با مقایسهٔ ۵۳ جدول) دستی تایید شده؛ در backend/AGENTS.md مستند است.
 */
class BackupCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_database_is_noop_on_sqlite(): void
    {
        $this->artisan('backup:database')->assertSuccessful();
    }

    public function test_backup_restore_test_is_noop_on_sqlite(): void
    {
        $this->artisan('backup:restore-test')->assertSuccessful();
    }
}
