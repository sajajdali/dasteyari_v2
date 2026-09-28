<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| سایت عمومی
|--------------------------------------------------------------------------
| بستهٔ ۱۲‑الف/۱۲‑ب/۱۲‑ج پلن: تمام صفحه‌های عمومی واقعی‌اند (فاز ۱۲ کامل).
*/
Route::name('site.')->group(function () {
    Route::get('/', fn () => view('site.home'))->name('home');

    Route::get('/cases', fn () => view('site.cases'))->name('cases');
    Route::get('/cases/{request}', fn (\App\Models\CaseRequest $request) => view('site.case', ['request' => $request]))->name('cases.show');

    Route::get('/groups', fn () => view('site.groups'))->name('groups');
    Route::get('/groups/{group}', fn (\App\Models\NeedGroup $group) => view('site.groups', ['group' => $group]))->name('groups.show');

    Route::get('/campaigns', fn () => view('site.campaigns'))->name('campaigns');
    Route::get('/campaigns/{campaign}', fn (\App\Models\Campaign $campaign) => view('site.campaign', ['campaign' => $campaign]))->name('campaigns.show');

    Route::get('/news', fn () => view('site.news'))->name('news');
    Route::get('/news/{post:slug}', fn (\App\Models\Post $post) => view('site.post', ['post' => $post]))->name('news.show');

    Route::get('/about', fn () => view('site.about'))->name('about');
    Route::get('/finance', fn () => view('site.finance'))->name('finance');
    Route::get('/terms', fn () => view('site.terms'))->name('terms');
});

/*
|--------------------------------------------------------------------------
| درگاه پرداخت — بخش ۷‑ج پلن
|--------------------------------------------------------------------------
| عمومی (بدون auth) چون خیر/بازدیدکنندهٔ سایت این مسیر را دنبال می‌کند، نه پرسنل.
| پیاده‌سازی fake است (App\Services\Payment\FakeGateway، بند ۱۸ پلن — سرویس واقعی
| هنوز انتخاب نشده). {ref} همان transactions.ref یکتاست، نه شناسهٔ عددی.
*/
Route::name('pay.')->prefix('pay')->controller(\App\Http\Controllers\PaymentController::class)->group(function () {
    Route::get('/{transaction}/start', 'start')->name('start');
    Route::get('/fake/{ref}', 'fakeShow')->name('fake');
    Route::post('/fake/{ref}', 'fakeSubmit')->name('fake.submit');
    Route::match(['get', 'post'], '/callback/{ref}', 'callback')->name('callback');
    Route::get('/result/{ref}', 'result')->name('result');
});

/*
|--------------------------------------------------------------------------
| پنل مدیریت — guard admin
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('/login', fn () => view('admin.login'))->name('login');
        Route::get('/forgot-password', fn () => view('admin.forgot-password'))->name('password.request');
        Route::get('/reset-password/{token}', fn (string $token) => view('admin.reset-password', ['token' => $token]))->name('password.reset');
    });

    Route::post('/logout', function () {
        Auth::guard('admin')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('admin.login');
    })->middleware('auth:admin')->name('logout');

    Route::middleware('auth:admin')->group(function () {
        Route::get('/', fn () => view('admin.desk'))->name('desk');

        Route::get('/needies', fn () => view('admin.needies.index'))->name('needies');
        Route::get('/needies/create', fn () => view('admin.needies.create'))->name('needies.create');
        Route::get('/needies/{needy}', fn (\App\Models\Needy $needy) => view('admin.needies.show', ['needy' => $needy]))->name('needies.show');

        Route::get('/requests', fn () => view('admin.requests.index'))->name('requests');
        Route::get('/requests/{request}', fn (\App\Models\CaseRequest $request) => view('admin.requests.show', ['request' => $request]))->name('requests.show');

        Route::get('/intake', fn () => view('admin.intake'))->name('intake');
        Route::get('/queue', fn () => view('admin.queue'))->name('queue');

        Route::get('/donors', fn () => view('admin.donors.index'))->name('donors');
        Route::get('/donors/{donor}', fn (\App\Models\Donor $donor) => view('admin.donors.show', ['donor' => $donor]))->name('donors.show');

        Route::get('/support-assign', fn () => view('admin.support-assign'))->name('support-assign');
        Route::get('/orphans', fn () => view('admin.orphans'))->name('orphans');

        Route::get('/transactions', fn () => view('admin.transactions'))->name('transactions');
        Route::get('/pledges', fn () => view('admin.pledges'))->name('pledges');
        Route::get('/expenses', fn () => view('admin.expenses'))->name('expenses');

        Route::get('/campaigns', fn () => view('admin.campaigns.index'))->name('campaigns');
        Route::get('/campaigns/create', fn () => view('admin.campaigns.create'))->name('campaigns.create');
        Route::get('/campaigns/{campaign}', fn (\App\Models\Campaign $campaign) => view('admin.campaigns.show', ['campaign' => $campaign]))->name('campaigns.show');

        Route::get('/broadcasts', fn () => view('admin.broadcasts.index'))->name('broadcasts');
        Route::get('/broadcasts/create', fn () => view('admin.broadcasts.create'))->name('broadcasts.create');
        Route::get('/broadcasts/{broadcast}', fn (\App\Models\Broadcast $broadcast) => view('admin.broadcasts.show', ['broadcast' => $broadcast]))->name('broadcasts.show');
        Route::get('/sms-log', fn () => view('admin.sms-log'))->name('sms-log');
        Route::get('/tickets', fn () => view('admin.tickets'))->name('tickets');
        Route::get('/users', fn () => view('admin.users'))->name('users');
        Route::get('/posts', fn () => view('admin.content'))->name('posts');

        Route::get('/settings', fn () => view('admin.settings'))->name('settings');
        Route::get('/notifications', fn () => view('admin.notifications'))->name('notifications');
        Route::get('/activity-log', fn () => view('admin.activity-log'))->name('activity-log');

        foreach (config('nav', []) as $item) {
            if (in_array($item['route'], ['admin.desk', 'admin.needies', 'admin.requests', 'admin.intake', 'admin.queue', 'admin.donors', 'admin.support-assign', 'admin.orphans', 'admin.transactions', 'admin.pledges', 'admin.expenses', 'admin.campaigns', 'admin.broadcasts', 'admin.sms-log', 'admin.settings', 'admin.tickets', 'admin.users', 'admin.posts'], true)) {
                continue;
            }
            $slug = str_replace('admin.', '', $item['route']);
            Route::get('/'.$slug, fn () => view('admin.soon', ['title' => $item['label']]))->name($slug);
        }
    });
});

/*
|--------------------------------------------------------------------------
| پنل خیرین — guard donor (ورود با OTP، بدون رمز — طبق قالب hi-fi)
|--------------------------------------------------------------------------
*/
Route::prefix('donor')->name('donor.')->group(function () {
    Route::middleware('guest:donor')->group(function () {
        Route::get('/login', fn () => view('donor.login'))->name('login');
    });

    Route::post('/logout', function () {
        Auth::guard('donor')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('donor.login');
    })->middleware('auth:donor')->name('logout');

    Route::middleware('auth:donor')->group(function () {
        Route::get('/', fn () => view('donor.dashboard'))->name('dashboard');

        Route::get('/my-cases', fn () => view('donor.my-cases.index'))->name('my-cases');
        Route::get('/my-cases/{support}', fn (\App\Models\Support $support) => view('donor.my-cases.show', ['support' => $support]))->name('my-cases.show');

        Route::get('/waiting-families', fn () => view('donor.waiting-families'))->name('waiting-families');
        Route::get('/pledges', fn () => view('donor.pledges'))->name('pledges');

        Route::get('/tickets', fn () => view('donor.tickets.index'))->name('tickets');
        Route::get('/tickets/{ticket}', fn (\App\Models\Ticket $ticket) => view('donor.tickets.show', ['ticket' => $ticket]))->name('tickets.show');

        Route::get('/profile', fn () => view('donor.profile'))->name('profile');
    });
});

/*
|--------------------------------------------------------------------------
| پنل نیازمند — guard needy (ورود با OTP، بدون رمز)
|--------------------------------------------------------------------------
*/
Route::prefix('needy')->name('needy.')->group(function () {
    // ثبت درخواست کمک عمومی است — پیش‌نیاز ساخت حساب needy (فاز ۱۱)، پس بدون auth.
    Route::get('/request-help', fn () => view('site.request-help'))->name('request-help');

    Route::middleware('guest:needy')->group(function () {
        Route::get('/login', fn () => view('needy.login'))->name('login');
    });

    Route::post('/logout', function () {
        Auth::guard('needy')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('needy.login');
    })->middleware('auth:needy')->name('logout');

    Route::middleware('auth:needy')->group(function () {
        Route::get('/', fn () => view('needy.home'))->name('home');
        Route::get('/requests', fn () => view('needy.requests'))->name('requests');
        Route::get('/payments', fn () => view('needy.payments'))->name('payments');
        Route::get('/tickets', fn () => view('needy.tickets'))->name('tickets');
        Route::get('/profile', fn () => view('needy.profile'))->name('profile');
    });
});
