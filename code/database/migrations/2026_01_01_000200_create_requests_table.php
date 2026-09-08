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
            $t->foreignId('need_group_id')->constrained('need_groups');
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
        });

        // جست‌وجوی فارسی (PostgreSQL)
        if (DB_driver() === 'pgsql') {
            \DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
            \DB::statement('CREATE INDEX requests_title_trgm ON requests USING gin (title gin_trgm_ops)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('requests');
    }
};
