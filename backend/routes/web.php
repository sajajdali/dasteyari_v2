<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| سایت عمومی
|--------------------------------------------------------------------------
| صفحات واقعی در فاز ۱۲ ساخته می‌شوند؛ فعلاً «به‌زودی» — بستهٔ ۱‑الف پلن.
*/
Route::name('site.')->group(function () {
    Route::get('/', fn () => view('site.home'))->name('home');

    foreach ([
        'cases'     => 'پرونده‌ها',
        'groups'    => 'گروه‌های کمک',
        'campaigns' => 'کمپین‌ها',
        'about'     => 'درباره ما',
        'finance'   => 'شفافیت مالی',
        'terms'     => 'قوانین و حریم خصوصی',
    ] as $slug => $label) {
        Route::get('/'.$slug, fn () => view('site.soon', ['title' => $label, 'active' => $slug]))->name($slug);
    }
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

        foreach (config('nav', []) as $item) {
            if ($item['route'] === 'admin.desk') {
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

        foreach ([
            'my-cases'         => 'پرونده‌های من',
            'waiting-families' => 'خانواده‌های منتظر',
            'pledges'          => 'تعهدها',
            'tickets'          => 'پیام‌ها',
            'profile'          => 'ویرایش اطلاعات من',
        ] as $slug => $label) {
            Route::get('/'.$slug, fn () => view('donor.soon', ['title' => $label, 'active' => $slug]))->name($slug);
        }
    });
});

/*
|--------------------------------------------------------------------------
| پنل نیازمند — guard needy (ورود با OTP، بدون رمز)
|--------------------------------------------------------------------------
*/
Route::prefix('needy')->name('needy.')->group(function () {
    // ثبت درخواست کمک عمومی است — پیش‌نیاز ساخت حساب needy (فاز ۱۱)، پس بدون auth.
    Route::get('/request-help', fn () => view('needy.soon', ['title' => 'ثبت درخواست کمک', 'active' => null]))->name('request-help');

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

        foreach ([
            'requests' => 'درخواست‌های من',
            'payments' => 'تامین و پرداخت‌ها',
            'tickets'  => 'پیام‌ها',
            'profile'  => 'پروفایل من',
        ] as $slug => $label) {
            Route::get('/'.$slug, fn () => view('needy.soon', ['title' => $label, 'active' => $slug]))->name($slug);
        }
    });
});
