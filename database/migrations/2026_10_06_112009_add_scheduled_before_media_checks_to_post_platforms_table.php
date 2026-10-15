<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_platforms', function (Blueprint $table): void {
            $table->boolean('scheduled_before_media_checks')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('post_platforms', function (Blueprint $table): void {
            $table->dropColumn('scheduled_before_media_checks');
        });
    }
};
