<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** case_events: فقط-درج (append-only) — هیچ UPDATE و DELETE مجاز نیست، بخش ۶ پلن. */
return new class extends Migration
{
    public function up(): void
    {
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

        Schema::create('activity_log', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('role')->nullable();
            $t->string('ip', 45)->nullable();
            $t->string('category')->nullable();
            $t->string('subject')->nullable();
            $t->text('description')->nullable();
            $t->timestamp('created_at')->useCurrent();

            $t->index('category');
        });

        Schema::create('sms_log', function (Blueprint $t) {
            $t->id();
            $t->string('to_name')->nullable();
            $t->string('phone');
            $t->string('side')->nullable(); // in|out
            $t->string('kind')->nullable();
            $t->string('source')->nullable();
            $t->text('text')->nullable();
            $t->string('state')->nullable();
            $t->timestamp('sent_at')->nullable();

            $t->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_log');
        Schema::dropIfExists('activity_log');
        Schema::dropIfExists('case_events');
    }
};
