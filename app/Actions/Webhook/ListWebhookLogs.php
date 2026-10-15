<?php

declare(strict_types=1);

namespace App\Actions\Webhook;

use App\Models\Webhook;
use App\Models\WebhookLog;
use Illuminate\Database\Eloquent\Builder;

class ListWebhookLogs
{
    /**
     * @return Builder<WebhookLog>
     */
    public static function execute(Webhook $webhook): Builder
    {
        return WebhookLog::query()
            ->where('webhook_id', $webhook->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }
}
