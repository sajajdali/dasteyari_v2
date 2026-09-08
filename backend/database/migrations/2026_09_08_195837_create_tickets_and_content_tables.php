<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $t) {
            $t->id();
            $t->string('subject');
            $t->string('from_type'); // donor|needy|admin
            $t->unsignedBigInteger('from_id');
            $t->string('category')->nullable();
            $t->string('priority')->default('medium');
            $t->string('state')->default('open');
            $t->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('related_type')->nullable();
            $t->unsignedBigInteger('related_id')->nullable();
            $t->timestamp('sla_due_at')->nullable();
            $t->timestamps();

            $t->index('state');
            $t->index(['from_type', 'from_id']);
        });

        Schema::create('ticket_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $t->string('author_type')->nullable();
            $t->unsignedBigInteger('author_id')->nullable();
            $t->text('body');
            $t->string('file_path')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('posts', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('slug')->unique();
            $t->string('cover_path')->nullable();
            $t->string('excerpt')->nullable();
            $t->longText('body');
            $t->string('category')->nullable();
            $t->string('state')->default('draft');
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
            $t->softDeletes();

            $t->index('state');
        });

        Schema::create('pages', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->string('title');
            $t->longText('body')->nullable();
            $t->json('seo')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('ticket_messages');
        Schema::dropIfExists('tickets');
    }
};
