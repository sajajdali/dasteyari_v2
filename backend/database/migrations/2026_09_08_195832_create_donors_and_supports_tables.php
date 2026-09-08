<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donors', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users');
            $t->string('kind')->default('person'); // person|group|org
            $t->string('city')->nullable();
            $t->unsignedTinyInteger('capacity_cases')->nullable();
            $t->unsignedTinyInteger('monthly_day')->nullable();
            $t->boolean('anon_default')->default(false);
            $t->string('status')->default('active');
            $t->timestamp('joined_at')->nullable();
            $t->json('meta')->nullable();
            $t->timestamps();

            $t->index('status');
        });

        Schema::create('supports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('donor_id')->constrained('donors');
            $t->foreignId('request_id')->constrained('requests');
            $t->enum('plan', ['once', 'monthly', 'period'])->default('monthly');
            $t->decimal('amount', 18, 0);
            $t->timestamp('started_at');
            $t->timestamp('ended_at')->nullable();
            $t->foreignId('end_reason_id')->nullable()->constrained('reasons')->nullOnDelete();
            $t->string('status')->default('active');
            $t->decimal('given_total', 18, 0)->default(0);
            $t->unsignedSmallInteger('months_count')->default(0);
            $t->timestamps();

            $t->index(['request_id', 'status']);
            $t->index(['donor_id', 'status']);
        });

        Schema::create('support_followups', function (Blueprint $t) {
            $t->id();
            $t->foreignId('support_id')->constrained('supports')->cascadeOnDelete();
            $t->foreignId('admin_id')->constrained('users');
            $t->string('result')->nullable();
            $t->unsignedSmallInteger('grace_days')->default(3);
            $t->timestamp('due_at');
            $t->string('note')->nullable();
            $t->timestamp('created_at')->useCurrent();

            $t->index(['support_id', 'due_at']);
        });

        Schema::create('transfers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('support_id')->constrained('supports');
            $t->foreignId('request_id')->constrained('requests');
            $t->foreignId('from_donor_id')->constrained('donors');
            $t->foreignId('to_donor_id')->nullable()->constrained('donors')->nullOnDelete();
            $t->string('mode'); // site|donor
            $t->unsignedSmallInteger('slot')->nullable();
            $t->foreignId('reason_id')->nullable()->constrained('reasons')->nullOnDelete();
            $t->string('reason_text')->nullable();
            $t->text('description')->nullable();
            $t->foreignId('admin_id')->constrained('users');
            $t->timestamp('created_at')->useCurrent();
            $t->decimal('given_snapshot', 18, 0)->default(0);
            $t->unsignedSmallInteger('months_snapshot')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
        Schema::dropIfExists('support_followups');
        Schema::dropIfExists('supports');
        Schema::dropIfExists('donors');
    }
};
