<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ارجاع پرونده به یک کارمند دیگر — بخش ۹.۱ پلن، «میز کار من» + جزئیات پرونده (طرح: rdRefs).
 * فاز ۴ و فاز ۱۴‑ب (بازسازی request-detail) هردو این را عمداً نساخته بودند («هیچ جدول referrals
 * ای در بخش ۳ پلن نیست، فقط UI طرح است») — کارفرما صریحاً خواست همهٔ تب‌های طرح باشند، حتی اگر
 * دادهٔ پشتیبان نداشتند، پس این‌جا خودِ جدول واقعی ساخته شد؛ از این پس یک ویژگی کاملاً واقعی است،
 * نه جعلی. حذف فیزیکی ندارد — append-only مثل `case_events` (بخش ۱۵.۲ پلن، معیار #۳)، «انجام‌شده»
 * با پرکردن `resolved_at` مشخص می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('from_admin_id')->constrained('users');
            $table->foreignId('to_admin_id')->constrained('users');
            $table->text('note');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index('to_admin_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
