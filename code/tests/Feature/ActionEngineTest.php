<?php

use App\Models\{CaseRequest, Reason, User};
use App\Services\CaseEventService;

it('برای هر کلید اقدام دلیل seed شده دارد', function () {
    foreach (array_keys(config('actions')) as $key) {
        expect(Reason::where('action_key', $key)->where('active', true)->exists())
            ->toBeTrue("دلیلی برای $key تعریف نشده است");
    }
});

it('اقدام رویداد با دلیل و توضیح می‌سازد و وضعیت را تغییر می‌دهد', function () {
    $admin = User::factory()->withRole('manager')->create();
    $req   = CaseRequest::factory()->create(['status' => 'queued']);
    $reason = Reason::where('action_key', 'case.publish')->first();

    $this->actingAs($admin);
    app(CaseEventService::class)->record('case.publish', $req, $reason->id, 'مدارک بررسی و تایید شد.', ['slot' => 3]);

    expect($req->fresh()->status)->toBe('published');
    expect($req->events()->first())
        ->action_key->toBe('case.publish')
        ->reason_text->toBe($reason->text)
        ->admin_id->toBe($admin->id);
});

it('توضیح کوتاه‌تر از حد مجاز رد می‌شود', function () {
    $admin = User::factory()->withRole('manager')->create();
    $req   = CaseRequest::factory()->create(['status' => 'queued']);
    $this->actingAs($admin);

    expect(fn () => app(CaseEventService::class)->record('case.publish', $req, Reason::first()->id, 'کوتاه'))
        ->toThrow(Illuminate\Validation\ValidationException::class);
});

it('گذار غیرمجاز وضعیت خطا می‌دهد', function () {
    $admin = User::factory()->withRole('manager')->create();
    $req   = CaseRequest::factory()->create(['status' => 'draft']);
    $this->actingAs($admin);

    expect(fn () => app(CaseEventService::class)->record('case.publish', $req, Reason::first()->id, 'انتشار زودهنگام برای تست.'))
        ->toThrow(Illuminate\Validation\ValidationException::class);
});

it('نقش بدون مجوز 403 می‌گیرد', function () {
    $officer = User::factory()->withRole('case-officer')->create();
    $req     = CaseRequest::factory()->create(['status' => 'queued']);
    $this->actingAs($officer);

    expect(fn () => app(CaseEventService::class)->record('case.publish', $req, Reason::first()->id, 'تلاش بدون دسترسی کافی.'))
        ->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
});
