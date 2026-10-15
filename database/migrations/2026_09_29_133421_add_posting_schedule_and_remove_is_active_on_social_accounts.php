<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_accounts', function (Blueprint $table): void {
            $table->string('timezone')->default('UTC');
            $table->unsignedTinyInteger('posting_goal')->nullable();
            $table->json('posting_schedule')->nullable();
        });

        $enabledScheduledTargets = fn (bool $isActive) => DB::table('post_platforms')
            ->join('posts', 'posts.id', '=', 'post_platforms.post_id')
            ->join('social_accounts', 'social_accounts.id', '=', 'post_platforms.social_account_id')
            ->where('posts.status', 'scheduled')
            ->where('post_platforms.enabled', true)
            ->where('social_accounts.is_active', $isActive);

        $pausedPostIds = $enabledScheduledTargets(false)->distinct()->pluck('post_platforms.post_id');
        $activePostIds = $enabledScheduledTargets(true)->distinct()->pluck('post_platforms.post_id')->flip();

        [$mixedPostIds, $onlyPausedPostIds] = $pausedPostIds->partition(fn (string $postId): bool => $activePostIds->has($postId));

        foreach ($mixedPostIds->chunk(500) as $chunk) {
            DB::table('post_platforms')
                ->whereIn('post_id', $chunk->all())
                ->whereIn('social_account_id', fn ($pausedAccounts) => $pausedAccounts->select('id')->from('social_accounts')->where('is_active', false))
                ->where('enabled', true)
                ->update(['enabled' => false, 'updated_at' => now()]);
        }

        foreach ($onlyPausedPostIds->chunk(500) as $chunk) {
            DB::table('posts')
                ->whereIn('id', $chunk->all())
                ->where('status', 'scheduled')
                ->update(['status' => 'draft', 'updated_at' => now()]);
        }

        Schema::table('social_accounts', function (Blueprint $table): void {
            $table->dropColumn('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('social_accounts', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('status');
            $table->dropColumn(['timezone', 'posting_goal', 'posting_schedule']);
        });
    }
};
