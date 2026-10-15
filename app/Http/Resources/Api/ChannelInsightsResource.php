<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChannelInsightsResource extends JsonResource
{
    /**
     * @return array{filters: array<string, mixed>, available_metrics: list<string>, summary: array<string, mixed>, metric_series: array<string, mixed>}
     */
    public function toArray(Request $request): array
    {
        return [
            'filters' => data_get($this->resource, 'filters'),
            'available_metrics' => data_get($this->resource, 'available_metrics'),
            'summary' => data_get($this->resource, 'summary'),
            'metric_series' => data_get($this->resource, 'metric_series'),
        ];
    }
}
