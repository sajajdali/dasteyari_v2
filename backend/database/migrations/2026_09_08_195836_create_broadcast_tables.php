<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcasts', function (Blueprint $t) {
            $t->id();
            $t->string('mode'); // case|campaign|general
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->string('title');
            $t->text('body');
            $t->json('channels')->nullable();
            $t->json('audience')->nullable();
            $t->unsignedInteger('total')->default(0);
            $t->unsignedInteger('sent')->default(0);
            $t->unsignedInteger('delivered')->default(0);
            $t->unsignedInteger('opened')->default(0);
            $t->string('state')->default('queued');
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();

            $t->index('state');
        });

        Schema::create('broadcast_recipients', function (Blueprint $t) {
            $t->id();
            $t->foreignId('broadcast_id')->constrained('broadcasts')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('phone');
            $t->string('state')->default('queued');
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('opened_at')->nullable();
            $t->string('error')->nullable();

            $t->index(['broadcast_id', 'state']);
        });

        Schema::create('broadcast_templates', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->text('body');
            $t->json('channels')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_templates');
        Schema::dropIfExists('broadcast_recipients');
        Schema::dropIfExists('broadcasts');
    }
};
