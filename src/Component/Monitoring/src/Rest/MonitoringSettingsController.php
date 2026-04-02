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
    private const MASKED_VALUE = '********';

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

        $error = $this->validateProviderData($data);
        if ($error !== null) {
            return $this->json(['error' => $error], 400);
        }

        $this->store->saveProvider($data);

        return $this->created(['success' => true]);
    }

    #[RestRoute(route: '/providers', methods: HttpMethod::PUT)]
    public function updateProvider(\WP_REST_Request $request): JsonResponse
    {
        /** @var array<string, mixed> $data */
        $data = $request->get_json_params();
        $id = (string) ($data['id'] ?? '');

        if ($this->isLockedProvider($id)) {
            return $this->json(['error' => 'This provider is locked and cannot be modified.'], 403);
        }

        $error = $this->validateProviderData($data);
        if ($error !== null) {
            return $this->json(['error' => $error], 400);
        }

        $this->store->saveProvider($data);

        return $this->json(['success' => true]);
    }

    #[RestRoute(route: '/providers', methods: HttpMethod::DELETE)]
    public function deleteProvider(\WP_REST_Request $request): Response
    {
        /** @var array<string, mixed> $data */
        $data = $request->get_json_params();
        $id = (string) ($data['id'] ?? '');

        if ($id === '' || !$this->isValidId($id)) {
            return $this->json(['error' => 'Invalid provider id.'], 400);
        }

        if ($this->isLockedProvider($id)) {
            return $this->json(['error' => 'This provider is locked and cannot be deleted.'], 403);
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

        if (!$this->isValidId($providerId) || !\is_array($metric)) {
            return $this->json(['error' => 'Invalid providerId or metric data.'], 400);
        }

        if ($this->isLockedProvider($providerId)) {
            return $this->json(['error' => 'This provider is locked.'], 403);
        }

        $error = $this->validateMetricData($metric);
        if ($error !== null) {
            return $this->json(['error' => $error], 400);
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

        if (!$this->isValidId($providerId) || !\is_array($metric)) {
            return $this->json(['error' => 'Invalid providerId or metric data.'], 400);
        }

        if ($this->isLockedProvider($providerId)) {
            return $this->json(['error' => 'This provider is locked.'], 403);
        }

        $error = $this->validateMetricData($metric);
        if ($error !== null) {
            return $this->json(['error' => $error], 400);
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

        if (!$this->isValidId($providerId) || !$this->isValidId($metricId)) {
            return $this->json(['error' => 'Invalid providerId or metricId.'], 400);
        }

        if ($this->isLockedProvider($providerId)) {
            return $this->json(['error' => 'This provider is locked.'], 403);
        }

        $this->store->deleteMetric($providerId, $metricId);

        return $this->noContent();
    }

    private function isValidId(string $id): bool
    {
        return $id !== '' && preg_match('/^[a-zA-Z0-9._-]+$/', $id) === 1;
    }

    private function isLockedProvider(string $id): bool
    {
        return $this->registry->get($id)?->locked === true;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function validateProviderData(array $data): ?string
    {
        $id = (string) ($data['id'] ?? '');
        $label = (string) ($data['label'] ?? '');
        $bridge = (string) ($data['bridge'] ?? '');

        if ($id === '' || !$this->isValidId($id)) {
            return 'Provider id is required and must be alphanumeric.';
        }

        if ($label === '' || mb_strlen($label) > 200) {
            return 'Label is required and must be under 200 characters.';
        }

        if ($bridge === '') {
            return 'Bridge is required.';
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function validateMetricData(array $data): ?string
    {
        $label = (string) ($data['label'] ?? '');
        $namespace = (string) ($data['namespace'] ?? '');
        $metricName = (string) ($data['metricName'] ?? '');

        if ($label === '' || mb_strlen($label) > 200) {
            return 'Metric label is required and must be under 200 characters.';
        }

        if ($namespace === '') {
            return 'Metric namespace is required.';
        }

        if ($metricName === '') {
            return 'Metric name is required.';
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeProviderWithMask(MonitoringProvider $provider): array
    {
        $settings = $provider->settings->toArray();
        foreach ($provider->settings::sensitiveFields() as $field) {
            if (isset($settings[$field]) && $settings[$field] !== '') {
                $settings[$field] = self::MASKED_VALUE;
            }
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
