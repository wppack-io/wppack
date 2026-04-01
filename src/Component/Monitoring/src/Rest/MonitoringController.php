<?php

/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Component\Monitoring\Rest;

use WpPack\Component\HttpFoundation\JsonResponse;
use WpPack\Component\Monitoring\MetricPoint;
use WpPack\Component\Monitoring\MetricResult;
use WpPack\Component\Monitoring\MetricSource;
use WpPack\Component\Monitoring\MetricTimeRange;
use WpPack\Component\Monitoring\MonitoringCollector;
use WpPack\Component\Monitoring\MonitoringRegistry;
use WpPack\Component\Rest\AbstractRestController;
use WpPack\Component\Rest\Attribute\RestRoute;
use WpPack\Component\Rest\HttpMethod;
use WpPack\Component\Role\Attribute\IsGranted;

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

    #[RestRoute(route: '/sources', methods: HttpMethod::GET)]
    public function getSources(): JsonResponse
    {
        return $this->json([
            'sources' => array_map($this->serializeSource(...), $this->registry->all()),
            'providers' => $this->registry->providers(),
            'groups' => $this->registry->groups(),
        ]);
    }

    private function buildTimeRange(\WP_REST_Request $request): MetricTimeRange
    {
        $hours = (int) ($request->get_param('period') ?? 3);
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
            'sources' => array_map($this->serializeSource(...), $this->registry->all()),
            'providers' => $this->registry->providers(),
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
    private function serializeSource(MetricSource $source): array
    {
        return [
            'id' => $source->id,
            'label' => $source->label,
            'provider' => $source->provider,
            'namespace' => $source->namespace,
            'metricName' => $source->metricName,
            'unit' => $source->unit,
            'stat' => $source->stat,
            'dimensions' => $source->dimensions,
            'periodSeconds' => $source->periodSeconds,
            'group' => $source->group,
        ];
    }
}
