<?php

declare(strict_types=1);

namespace App\Support\Requests\Webhook;

use App\Enums\Webhook\EventType;
use App\Enums\Webhook\Status;
use Illuminate\Validation\Rule;

/**
 * Single source of the webhook rules shared by the web and API FormRequests
 * and the MCP create and update tools.
 */
class WebhookRequestRules
{
    /**
     * @return array<string, mixed>
     */
    public static function store(): array
    {
        return [
            'endpoint' => ['required', 'url', 'max:255'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', Rule::enum(EventType::class)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function update(): array
    {
        return [
            'endpoint' => ['sometimes', 'url', 'max:255'],
            'events' => ['sometimes', 'array', 'min:1'],
            'events.*' => ['string', Rule::enum(EventType::class)],
            'status' => ['sometimes', 'string', Rule::enum(Status::class)->only([Status::Enabled, Status::Disabled])],
        ];
    }
}
