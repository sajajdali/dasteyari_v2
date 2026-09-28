<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * فاز ۱۴‑ب (تکمیل تعیین تکلیف و جایگزینی خیر، بخش ۹.۱ پلن) — کشف حین بازسازی کامل «میز کار من»
 * مطابق طرح: طرح یک وضعیت سوم برای هر حمایتِ پایان‌یافته دارد که با هیچ‌کدام از ستون‌های موجود
 * (`supports.status`) قابل‌بیان نیست — «تصمیم گرفته شده که این پرونده از طریق سایت خیر جدید بگیرد»
 * یا «این تعیین‌تکلیف بدون یافتن خیر جدید بسته شد». این‌ها **گذار وضعیت رسمی حمایت نیستند** (بخش ۴.۲
 * پلن صراحتاً می‌گوید Ended/Transferred پایانی‌اند، بدون گذار مجاز — `SupportStatus::allowed()`) بلکه
 * فقط یک برچسب مدیریتی روی همان رکورد پایان‌یافته‌اند؛ به همین دلیل ستون جدا و ungoverned است، نه
 * مقداری روی enum رسمی `status`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supports', function (Blueprint $table) {
            $table->string('reassignment_status')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('supports', function (Blueprint $table) {
            $table->dropColumn('reassignment_status');
        });
    }
};
