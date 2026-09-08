<?php
/**
 * کد یک‌بارمصرف اصلی برای تست محلی — با آن هر شماره‌ای همیشه تایید می‌شود،
 * بدون نیاز به چک‌کردن storage/logs/laravel.log. فقط در local/staging فعال است
 * (بخش OtpService::verify()) و هرگز در production کار نمی‌کند.
 */
return [
    'master_code' => env('OTP_MASTER_CODE', '9990'),
];
