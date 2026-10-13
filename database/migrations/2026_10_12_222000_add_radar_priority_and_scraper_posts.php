<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('source_account')->nullable()->index();
            $table->unsignedSmallInteger('priority_score')->default(0)->index();
            $table->boolean('is_priority')->default(false)->index();
        });

        Schema::create('scraper_posts', function (Blueprint $table) {
            $table->id();
            $table->string('account_name', 100);
            $table->string('shortcode', 100);
            $table->dateTime('posted_at')->nullable();
            $table->timestamps();
            $table->unique(['account_name', 'shortcode']);
            $table->index(['account_name', 'posted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scraper_posts');

        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['source_account']);
            $table->dropIndex(['priority_score']);
            $table->dropIndex(['is_priority']);
            $table->dropColumn(['source_account', 'priority_score', 'is_priority']);
        });
    }
};
