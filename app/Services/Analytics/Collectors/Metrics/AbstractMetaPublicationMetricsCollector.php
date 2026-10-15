<?php

declare(strict_types=1);

namespace App\Services\Analytics\Collectors\Metrics;

use App\Exceptions\Analytics\AnalyticsCollectionException;
use App\Models\SocialAccount;
use App\Support\Analytics\MetaAnalyticsResponse;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

abstract class AbstractMetaPublicationMetricsCollector extends AbstractPublicationMetricsCollector
{
    /** @var list<string> */
    protected array $refusals = [];

    protected function successfulResponse(Response $response): Response
    {
        return MetaAnalyticsResponse::successful($response, 'publication metrics collection');
    }

    /**
     * @param  list<string>  $metrics
     * @param  array<string, string>  $query
     * @return list<array<string, mixed>>
     */
    protected function optionalInsightItems(SocialAccount $account, string $url, array $metrics, array $query = []): array
    {
        try {
            $response = $this->get($account, $url, ['metric' => implode(',', $metrics), ...$query]);
        } catch (AnalyticsCollectionException $exception) {
            if (! in_array($exception->category, ['permission', 'malformed'], true)) {
                throw $exception;
            }

            if (count($metrics) > 1) {
                return array_merge(...array_map(
                    fn (string $metric): array => $this->optionalInsightItems($account, $url, [$metric], $query),
                    $metrics,
                ));
            }

            $this->refusals[] = $exception->category;
            Log::info('analytics.meta_metric_rejected', [
                'platform' => $account->platform->value,
                'path' => parse_url($url, PHP_URL_PATH),
                'metric' => data_get($metrics, 0),
                'category' => $exception->category,
                'reason' => $exception->getMessage(),
            ]);

            return [];
        }

        $data = $response->json('data');

        return is_array($data) ? array_values(array_filter($data, 'is_array')) : [];
    }
}
