<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ردیف‌های برآورد هزینهٔ یک پرونده (دارو، آزمایش، ایاب‌وذهاب و...) — بخش «جزئیات پرونده» طرح
 * (isBudget). قبلاً چون جدولی برای این نبود این بخش حذف شده بود؛ کارفرما صریح خواست به‌جای مبلغ
 * تجمیعی، جدول واقعی و قابل‌مدیریت در پنل ساخته شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_cost_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $t->string('title');
            $t->string('note')->nullable();
            $t->decimal('amount', 18, 0);
            $t->unsignedSmallInteger('order')->default(0);
            $t->timestamps();

            $t->index('request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_cost_items');
    }
};
