<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('needy_id')->constrained('needies');
            $t->foreignId('need_group_id')->nullable()->constrained('need_groups')->nullOnDelete();
            $t->string('title');
            $t->enum('plan', ['once', 'monthly', 'period'])->default('once');
            $t->unsignedSmallInteger('period_days')->nullable();
            $t->decimal('amount', 18, 0);
            $t->decimal('amount_funded', 18, 0)->default(0);
            $t->timestamp('requested_at');
            $t->timestamp('deadline_at')->nullable();
            $t->string('status')->default('draft');
            $t->timestamp('published_at')->nullable();
            $t->unsignedTinyInteger('priority')->default(2);
            $t->unsignedSmallInteger('slot')->nullable();
            $t->json('meta')->nullable();
            $t->timestamps();
            $t->softDeletes();

            $t->index(['status', 'published_at']);
            $t->index('needy_id');
            $t->index('requested_at');
            $t->fullText('title');
        });

        Schema::create('request_docs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $t->string('type');
            $t->string('path');
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('verified_at')->nullable();
            $t->string('state')->default('pending');
            $t->string('note')->nullable();
            $t->timestamps();

            $t->index(['request_id', 'state']);
        });

        Schema::create('doc_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $t->foreignId('created_by')->constrained('users');
            $t->string('state')->default('open');
            $t->timestamp('due_at')->nullable();
            $t->string('note')->nullable();
            $t->timestamps();
        });

        Schema::create('doc_request_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('doc_request_id')->constrained('doc_requests')->cascadeOnDelete();
            $t->string('label');
            $t->string('type')->nullable();
            $t->boolean('required')->default(true);
            $t->unsignedSmallInteger('order')->default(0);
            $t->string('path')->nullable();
            $t->timestamp('filled_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doc_request_items');
        Schema::dropIfExists('doc_requests');
        Schema::dropIfExists('request_docs');
        Schema::dropIfExists('requests');
    }
};
