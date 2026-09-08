<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('need_groups', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('icon')->nullable();
            $t->unsignedSmallInteger('order')->default(0);
            $t->boolean('active')->default(true);
            $t->json('plans')->nullable();
            $t->timestamps();
        });

        // پیش‌نیاز خیلی از جدول‌های بعدی (supports.end_reason_id, transfers.reason_id, case_events.reason_id)
        // برای همین زودتر از بقیه ساخته می‌شود — بخش ۳.۷ پلن.
        Schema::create('reasons', function (Blueprint $t) {
            $t->id();
            $t->string('action_key')->index();
            $t->string('text');
            $t->unsignedSmallInteger('order')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('needies', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('name');
            $t->string('city')->nullable();
            $t->string('province')->nullable();
            $t->unsignedTinyInteger('family_size')->nullable();
            $t->unsignedSmallInteger('birth_year')->nullable();
            $t->foreignId('need_group_id')->nullable()->constrained('need_groups')->nullOnDelete();
            $t->timestamp('joined_at')->nullable();
            $t->string('status')->default('active');
            $t->unsignedTinyInteger('priority')->default(2);
            $t->json('meta')->nullable();
            $t->timestamps();
            $t->softDeletes();

            $t->index('status');
            $t->index('city');
            $t->fullText('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('needies');
        Schema::dropIfExists('reasons');
        Schema::dropIfExists('need_groups');
    }
};
