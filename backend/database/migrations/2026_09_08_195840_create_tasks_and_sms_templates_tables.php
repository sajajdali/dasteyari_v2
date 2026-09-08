<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('assignee_id')->constrained('users');
            $t->string('subject_type')->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->string('title');
            $t->string('priority')->default('medium');
            $t->timestamp('due_at')->nullable();
            $t->timestamp('done_at')->nullable();
            $t->timestamps();

            $t->index(['assignee_id', 'done_at', 'due_at']);
        });

        Schema::create('sms_templates', function (Blueprint $t) {
            $t->id();
            $t->string('group_key'); // intake|request|needyProfile|case|donorProfile|donorPledge|overdue|campaign
            $t->text('text');
            $t->unsignedSmallInteger('order')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();

            $t->index('group_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_templates');
        Schema::dropIfExists('tasks');
    }
};
