<?php

namespace App\Providers;

use App\Models\Transaction;
use App\Observers\TransactionObserver;
use App\Services\Payment\FakeGateway;
use App\Services\Payment\GatewayContract;
use App\Services\Sms\FakeSmsGateway;
use App\Services\Sms\SmsGatewayContract;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // بخش ۷‑ج/۱۸ پلن: سرویس درگاه واقعی هنوز انتخاب نشده — همیشه از پشت این قرارداد صدا زده شود
        // (نگاه کن به App\Services\Payment\GatewayContract) تا جایگزینی بعداً فقط همین یک خط باشد.
        $this->app->bind(GatewayContract::class, FakeGateway::class);

        // بخش ۷.۲/۱۸ پلن: سرویس واقعی پیامک هم مثل درگاه پرداخت هنوز انتخاب نشده — همان قرارداد.
        $this->app->bind(SmsGatewayContract::class, FakeSmsGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // لینک بازیابی رمز روی روت admin.password.reset (نه پیش‌فرض password.reset) — بخش ۲.۱ پلن.
        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            return route('admin.password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()]);
        });

        // نام کوتاه هر «موضوع» در case_events/keepers/notes به‌جای کلاس کامل — منبع: config/subjects.php.
        Relation::morphMap(collect(config('subjects'))->map(fn ($s) => $s['model'])->all());

        // بخش ۸.۳/۴.۱ پلن: تنها جایی که amount_funded/raised را می‌نویسد و گذار خودکار وضعیت را اعمال می‌کند.
        Transaction::observe(TransactionObserver::class);
    }
}
