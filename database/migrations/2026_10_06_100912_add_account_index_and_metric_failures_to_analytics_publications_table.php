<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analytics_publications', function (Blueprint $table): void {
            $table->unsignedTinyInteger('metric_failures')->default(0)->after('availability');
            $table->index(['social_account_id', 'provider_published_at'], 'analytics_publications_account_published_index');
        });
    }

    public function down(): void
    {
        Schema::table('analytics_publications', function (Blueprint $table): void {
            $table->index('social_account_id');
        });

        Schema::table('analytics_publications', function (Blueprint $table): void {
            $table->dropIndex('analytics_publications_account_published_index');
            $table->dropColumn('metric_failures');
        });
    }
};
