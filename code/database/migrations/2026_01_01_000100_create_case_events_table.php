<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** append-only — هیچ UPDATE و DELETE روی این جدول مجاز نیست. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reasons', function (Blueprint $t) {
            $t->id();
            $t->string('action_key')->index();
            $t->string('text');
            $t->unsignedSmallInteger('order')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('case_events', function (Blueprint $t) {
            $t->id();
            $t->string('subject_type');
            $t->unsignedBigInteger('subject_id');
            $t->string('action_key');
            $t->foreignId('reason_id')->nullable()->constrained('reasons')->nullOnDelete();
            $t->string('reason_text')->nullable();
            $t->text('description');
            $t->foreignId('admin_id')->constrained('users');
            $t->string('admin_role')->nullable();
            $t->string('ip', 45)->nullable();
            $t->string('source')->default('panel');
            $t->json('payload')->nullable();
            $t->timestamp('created_at')->useCurrent();

            $t->index(['subject_type', 'subject_id', 'created_at']);
            $t->index('action_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_events');
        Schema::dropIfExists('reasons');
    }
};
