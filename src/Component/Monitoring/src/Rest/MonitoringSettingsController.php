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
use WpPack\Component\HttpFoundation\Response;
use WpPack\Component\Monitoring\MetricDefinition;
use WpPack\Component\Monitoring\MonitoringProvider;
use WpPack\Component\Monitoring\MonitoringRegistry;
use WpPack\Component\Monitoring\MonitoringStore;
use WpPack\Component\Rest\AbstractRestController;
use WpPack\Component\Rest\Attribute\RestRoute;
use WpPack\Component\Rest\HttpMethod;
use WpPack\Component\Role\Attribute\IsGranted;

#[RestRoute(namespace: 'wppack/v1/monitoring')]
#[IsGranted('manage_options')]
final class MonitoringSettingsController extends AbstractRestController
{
    private const string MASKED_VALUE = '********';

    public function __construct(
        private readonly MonitoringStore $store,
        private readonly MonitoringRegistry $registry,
    ) {}

    #[RestRoute(route: '/providers', methods: HttpMethod::GET)]
    public function listProviders(): JsonResponse
    {
        return $this->json([
            'providers' => array_map(
                $this->serializeProviderWithMask(...),
                $this->registry->all(),
            ),
        ]);
    }

    #[RestRoute(route: '/providers', methods: HttpMethod::POST)]
    public function addProvider(\WP_REST_Request $request): JsonResponse
    {
        /** @var array<string, mixed> $data */
        $data = $request->get_json_params();
        $this->store->saveProvider($data);

        return $this->created(['success' => true]);
    }

    #[RestRoute(route: '/providers', methods: HttpMethod::PUT)]
    public function updateProvider(\WP_REST_Request $request): JsonResponse
    {
        /** @var array<string, mixed> $data */
        $data = $request->get_json_params();
        $this->store->saveProvider($data);

        return $this->json(['success' => true]);
    }

    #[RestRoute(route: '/providers', methods: HttpMethod::DELETE)]
    public function deleteProvider(\WP_REST_Request $request): Response
    {
        /** @var array<string, mixed> $data */
        $data = $request->get_json_params();
        $id = (string) ($data['id'] ?? '');

        if ($id === '') {
            return $this->json(['error' => 'Missing provider id'], 400);
        }

        $this->store->deleteProvider($id);

        return $this->noContent();
    }

    #[RestRoute(route: '/providers/metrics', methods: HttpMethod::POST)]
    public function addMetric(\WP_REST_Request $request): JsonResponse
    {
        /** @var array<string, mixed> $data */
        $data = $request->get_json_params();
        $providerId = (string) ($data['providerId'] ?? '');
        $metric = $data['metric'] ?? [];

        if ($providerId === '' || !\is_array($metric)) {
            return $this->json(['error' => 'Missing providerId or metric data'], 400);
        }

        $this->store->saveMetric($providerId, $metric);

        return $this->created(['success' => true]);
    }

    #[RestRoute(route: '/providers/metrics', methods: HttpMethod::PUT)]
    public function updateMetric(\WP_REST_Request $request): JsonResponse
    {
        /** @var array<string, mixed> $data */
        $data = $request->get_json_params();
        $providerId = (string) ($data['providerId'] ?? '');
        $metric = $data['metric'] ?? [];

        if ($providerId === '' || !\is_array($metric)) {
            return $this->json(['error' => 'Missing providerId or metric data'], 400);
        }

        $this->store->saveMetric($providerId, $metric);

        return $this->json(['success' => true]);
    }

    #[RestRoute(route: '/providers/metrics', methods: HttpMethod::DELETE)]
    public function deleteMetric(\WP_REST_Request $request): Response
    {
        /** @var array<string, mixed> $data */
        $data = $request->get_json_params();
        $providerId = (string) ($data['providerId'] ?? '');
        $metricId = (string) ($data['metricId'] ?? '');

        if ($providerId === '' || $metricId === '') {
            return $this->json(['error' => 'Missing providerId or metricId'], 400);
        }

        $this->store->deleteMetric($providerId, $metricId);

        return $this->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeProviderWithMask(MonitoringProvider $provider): array
    {
        return [
            'id' => $provider->id,
            'label' => $provider->label,
            'bridge' => $provider->bridge,
            'settings' => [
                'region' => $provider->settings->region,
                'accessKeyId' => $provider->settings->accessKeyId !== ''
                    ? self::MASKED_VALUE
                    : '',
                'secretAccessKey' => $provider->settings->secretAccessKey !== ''
                    ? self::MASKED_VALUE
                    : '',
            ],
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
