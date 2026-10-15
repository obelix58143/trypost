<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Webhook;

use App\Support\Requests\Webhook\WebhookRequestRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return WebhookRequestRules::store();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'endpoint' => __('webhooks.create.endpoint'),
            'events' => __('webhooks.create.events'),
            'events.*' => __('webhooks.create.events'),
        ];
    }
}
