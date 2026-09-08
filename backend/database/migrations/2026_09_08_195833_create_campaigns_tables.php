<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('title');
            $t->string('slug')->unique();
            $t->string('category_id')->nullable();
            $t->string('state')->default('draft');
            $t->decimal('goal', 18, 0);
            $t->decimal('raised', 18, 0)->default(0);
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->string('cover_path')->nullable();
            $t->string('short')->nullable();
            $t->text('about')->nullable();
            $t->json('meta')->nullable();
            $t->timestamps();
            $t->softDeletes();

            $t->index('state');
        });

        Schema::create('campaign_cases', function (Blueprint $t) {
            $t->id();
            $t->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $t->foreignId('request_id')->constrained('requests');
            $t->decimal('share', 18, 0)->default(0);
            $t->foreignId('added_by')->constrained('users');
            $t->timestamp('added_at')->useCurrent();
            $t->boolean('after_start')->default(false);
            $t->string('note')->nullable();

            $t->unique(['campaign_id', 'request_id']);
        });

        Schema::create('campaign_supporters', function (Blueprint $t) {
            $t->id();
            $t->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $t->string('name');
            $t->string('role')->nullable();
            $t->unsignedInteger('followers')->nullable();
            $t->string('link')->nullable();
            $t->timestamps();
        });

        Schema::create('campaign_updates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $t->string('title');
            $t->text('text');
            $t->timestamp('published_at')->nullable();
            $t->foreignId('author_id')->constrained('users');
            $t->timestamps();
        });

        // allocations در مهاجرت finance ساخته می‌شود (چون به transactions هم وابسته است).
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_updates');
        Schema::dropIfExists('campaign_supporters');
        Schema::dropIfExists('campaign_cases');
        Schema::dropIfExists('campaigns');
    }
};
