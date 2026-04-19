<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Monitoring\Rest;

use WPPack\Component\HttpFoundation\JsonResponse;
use WPPack\Component\Monitoring\MetricDefinition;
use WPPack\Component\Monitoring\MetricPoint;
use WPPack\Component\Monitoring\MetricResult;
use WPPack\Component\Monitoring\MetricTimeRange;
use WPPack\Component\Monitoring\MonitoringCollector;
use WPPack\Component\Monitoring\MonitoringProvider;
use WPPack\Component\Monitoring\MonitoringRegistry;
use WPPack\Component\Rest\AbstractRestController;
use WPPack\Component\Rest\Attribute\RestRoute;
use WPPack\Component\Rest\HttpMethod;
use WPPack\Component\Role\Attribute\IsGranted;

#[RestRoute(namespace: 'wppack/v1/monitoring')]
#[IsGranted('manage_options')]
final class MonitoringController extends AbstractRestController
{
    public function __construct(
        private readonly MonitoringCollector $collector,
        private readonly MonitoringRegistry $registry,
    ) {}

    #[RestRoute(route: '/metrics', methods: HttpMethod::GET)]
    public function getMetrics(\WP_REST_Request $request): JsonResponse
    {
        $range = $this->buildTimeRange($request);
        $forceRefresh = (bool) $request->get_param('force_refresh');
        $results = $this->collector->query($range, $forceRefresh);

        return $this->json($this->buildMetricsResponse($results));
    }

    #[RestRoute(route: '/refresh', methods: HttpMethod::POST)]
    public function refresh(\WP_REST_Request $request): JsonResponse
    {
        $range = $this->buildTimeRange($request);
        $results = $this->collector->query($range, forceRefresh: true);

        return $this->json($this->buildMetricsResponse($results));
    }

    #[RestRoute(route: '/providers', methods: HttpMethod::GET)]
    public function getProviders(): JsonResponse
    {
        return $this->json([
            'providers' => array_map($this->serializeProvider(...), $this->registry->all()),
            'bridges' => $this->registry->bridges(),
        ]);
    }

    private function buildTimeRange(\WP_REST_Request $request): MetricTimeRange
    {
        $hours = max(1, min(168, (int) ($request->get_param('period') ?? 3)));
        $end = new \DateTimeImmutable('now');
        $start = $end->modify("-{$hours} hours");

        return new MetricTimeRange($start, $end);
    }

    /**
     * @param list<MetricResult> $results
     * @return array<string, mixed>
     */
    private function buildMetricsResponse(array $results): array
    {
        return [
            'results' => array_map($this->serializeResult(...), $results),
            'providers' => array_map($this->serializeProvider(...), $this->registry->all()),
            'bridges' => $this->registry->bridges(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeResult(MetricResult $result): array
    {
        return [
            'sourceId' => $result->sourceId,
            'label' => $result->label,
            'unit' => $result->unit,
            'group' => $result->group,
            'datapoints' => array_map($this->serializePoint(...), $result->datapoints),
            'fetchedAt' => $result->fetchedAt?->format(\DateTimeInterface::ATOM),
            'error' => $result->error,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePoint(MetricPoint $point): array
    {
        return [
            'timestamp' => $point->timestamp->format(\DateTimeInterface::ATOM),
            'value' => $point->value,
            'stat' => $point->stat,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeProvider(MonitoringProvider $provider): array
    {
        $settings = $provider->settings->toArray();
        foreach ($provider->settings::sensitiveFields() as $field) {
            unset($settings[$field]);
        }

        return [
            'id' => $provider->id,
            'label' => $provider->label,
            'bridge' => $provider->bridge,
            'settings' => $settings,
            'metrics' => array_map($this->serializeMetric(...), $provider->metrics),
            'locked' => $provider->locked,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMetric(MetricDefinition $metric): array
    {
        return [
            'id' => $metric->id,
            'label' => $metric->label,
            'description' => $metric->description,
            'namespace' => $metric->namespace,
            'metricName' => $metric->metricName,
            'unit' => $metric->unit,
            'stat' => $metric->stat,
            'dimensions' => $metric->dimensions,
            'periodSeconds' => $metric->periodSeconds,
            'locked' => $metric->locked,
        ];
    }
}
