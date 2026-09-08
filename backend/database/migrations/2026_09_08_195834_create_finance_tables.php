<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $t) {
            $t->id();
            $t->enum('kind', ['in', 'out']);
            $t->foreignId('donor_id')->nullable()->constrained('donors')->nullOnDelete();
            $t->foreignId('request_id')->nullable()->constrained('requests')->nullOnDelete();
            $t->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $t->decimal('amount', 18, 0);
            $t->string('way'); // gateway|card|cash|deposit
            $t->string('ref')->nullable()->unique();
            $t->string('gateway_id')->nullable();
            $t->string('status')->default('pending');
            $t->timestamp('paid_at')->nullable();
            $t->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $t->boolean('manual')->default(false);
            $t->text('description')->nullable();
            $t->json('meta')->nullable();
            $t->timestamps();

            $t->index(['request_id', 'paid_at']);
            $t->index(['campaign_id', 'paid_at']);
        });

        // بخش ۸.۳ پلن: هر تراکنش ورودی باید مقصد (request یا campaign) داشته باشد.
        // این قید در Observer مدل هم تکرار می‌شود (بخش ۳.۱۲) — این‌جا فقط شبکهٔ ایمنی دیتابیس است.
        DB::statement("ALTER TABLE transactions ADD CONSTRAINT chk_transactions_destination CHECK (kind != 'in' OR request_id IS NOT NULL OR campaign_id IS NOT NULL)");

        Schema::create('allocations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('transaction_id')->constrained('transactions');
            $t->foreignId('request_id')->constrained('requests');
            $t->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $t->decimal('amount', 18, 0);
            $t->timestamp('created_at')->useCurrent();

            $t->index('request_id');
            $t->index('campaign_id');
        });

        Schema::create('pledges', function (Blueprint $t) {
            $t->id();
            $t->foreignId('donor_id')->constrained('donors');
            $t->foreignId('request_id')->constrained('requests');
            $t->decimal('amount', 18, 0);
            $t->timestamp('due_at');
            $t->string('status')->default('pending');
            $t->timestamps();

            $t->index(['status', 'due_at']);
        });

        Schema::create('fund_expenses', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('category')->nullable();
            $t->decimal('amount', 18, 0);
            $t->timestamp('spent_at');
            $t->string('doc_path')->nullable();
            $t->foreignId('by_id')->constrained('users');
            $t->text('description')->nullable();
            $t->timestamps();
        });

        Schema::create('payouts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('request_id')->constrained('requests');
            $t->foreignId('needy_id')->constrained('needies');
            $t->decimal('amount', 18, 0);
            $t->timestamp('paid_at');
            $t->string('way');
            $t->string('ref')->nullable();
            $t->foreignId('by_id')->constrained('users');
            $t->string('doc_path')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
        Schema::dropIfExists('fund_expenses');
        Schema::dropIfExists('pledges');
        Schema::dropIfExists('allocations');
        Schema::dropIfExists('transactions');
    }
};
