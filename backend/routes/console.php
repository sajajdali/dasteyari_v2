<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * نگهداری پارتیشن sms_log — بخش ۳.۱۱ پلن، فاز ۱۴‑الف. ماهانه کافی است (idempotent)؛ روزانه اجرا
 * می‌شود تا اگر یک اجرا به هر دلیلی جا بماند، ماه بعد خودش جبران شود.
 */
Schedule::command('partitions:extend-sms-log')->daily();

/**
 * بکاپ روزانهٔ کامل دیتابیس — بخش ۱۴‑الف پلن («بکاپ روزانه + تست restore»).
 * جزئیات و اسکریپت تست restore در backend/AGENTS.md («فاز ۱۴») و app/Console/Commands/BackupDatabase.php.
 */
Schedule::command('backup:database')->dailyAt('03:00');

/**
 * تست واقعی ری‌استور آخرین بکاپ — نیمهٔ دوم همان الزام. هفتگی کافی است (نه روزانه)؛ این دستور
 * دیتابیس موقت جدا می‌سازد و در پایان حذف می‌کند، پس روی dev واقعی هیچ اثری ندارد. جزئیات در
 * app/Console/Commands/BackupRestoreTest.php.
 */
Schedule::command('backup:restore-test')->weeklyOn(6, '04:00');
