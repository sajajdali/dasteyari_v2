<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تکمیل جدول users مطابق بخش ۲.۱ و ۳.۱ پلن — سه guard (staff/donor/needy) روی همین جدول.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->unique()->after('name');
            $table->string('national_id', 15)->nullable()->after('phone');
            $table->string('kind', 10)->default('staff')->after('national_id'); // staff|donor|needy
            $table->boolean('active')->default(true)->after('kind');
            $table->string('avatar')->nullable()->after('active');
            $table->timestamp('last_login_at')->nullable()->after('avatar');
            $table->json('meta')->nullable()->after('last_login_at');
            $table->softDeletes();

            $table->index('kind');
        });

        // OTP نیازمند — بخش ۲.۱: ورود با موبایل + کد پیامکی، بدون بازیابی رمز.
        Schema::create('phone_otps', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->index();
            $table->string('code', 6);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_otps');

        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['phone', 'national_id', 'kind', 'active', 'avatar', 'last_login_at', 'meta']);
        });
    }
};
