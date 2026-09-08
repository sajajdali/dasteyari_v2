<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('needy_id')->constrained('needies');
            $t->foreignId('request_id')->nullable()->constrained('requests')->nullOnDelete();
            $t->foreignId('officer_id')->constrained('users');
            $t->timestamp('visited_at');
            $t->decimal('amount_suggested', 18, 0)->nullable();
            $t->text('report')->nullable();
            $t->json('files')->nullable();
            $t->timestamps();

            $t->index('needy_id');
        });

        // پیگیر پرونده/حمایت/... — چندریختی (polymorphic).
        Schema::create('keepers', function (Blueprint $t) {
            $t->id();
            $t->string('subject_type');
            $t->unsignedBigInteger('subject_id');
            $t->foreignId('user_id')->constrained('users');
            $t->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('assigned_at')->useCurrent();

            $t->index(['subject_type', 'subject_id']);
            $t->index('user_id');
        });

        Schema::create('notes', function (Blueprint $t) {
            $t->id();
            $t->string('subject_type');
            $t->unsignedBigInteger('subject_id');
            $t->foreignId('author_id')->constrained('users');
            $t->text('body');
            $t->boolean('private')->default(false);
            $t->timestamp('created_at')->useCurrent();

            $t->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
        Schema::dropIfExists('keepers');
        Schema::dropIfExists('visits');
    }
};
