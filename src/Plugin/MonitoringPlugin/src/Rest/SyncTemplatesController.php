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

namespace WPPack\Plugin\MonitoringPlugin\Rest;

use Psr\Log\LoggerInterface;
use WPPack\Component\HttpFoundation\JsonResponse;
use WPPack\Component\Monitoring\MonitoringStore;
use WPPack\Component\Rest\AbstractRestController;
use WPPack\Component\Rest\Attribute\RestRoute;
use WPPack\Component\Rest\HttpMethod;
use WPPack\Component\Role\Attribute\IsGranted;
use WPPack\Component\Monitoring\MonitoringProvider;
use WPPack\Plugin\MonitoringPlugin\Template\MetricTemplate;
use WPPack\Plugin\MonitoringPlugin\Template\MetricTemplateRegistry;

#[RestRoute(namespace: 'wppack/v1/monitoring')]
#[IsGranted('manage_options')]
final class SyncTemplatesController extends AbstractRestController
{
    public function __construct(
        private readonly MonitoringStore $store,
        private readonly MetricTemplateRegistry $templates,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    #[RestRoute(route: '/sync-templates', methods: HttpMethod::POST)]
    public function sync(): JsonResponse
    {
        $providers = $this->store->getProviders();
        $updated = 0;

        foreach ($providers as $provider) {
            if ($provider->locked) {
                continue;
            }

            $template = $provider->templateId !== null
                ? $this->templates->get($provider->templateId)
                : $this->findMatchingTemplate($provider);

            if ($template === null) {
                continue;
            }

            if ($this->store->syncMetrics($provider->id, $template->metrics)) {
                $updated++;
            }
        }

        $this->logger?->info('Template sync completed: {count} providers updated.', ['count' => $updated]);

        return $this->json(['updated' => $updated]);
    }

    /**
     * Find a matching template for a provider without templateId by matching
     * bridge type and metric names.
     */
    private function findMatchingTemplate(MonitoringProvider $provider): ?MetricTemplate
    {
        $providerMetricNames = array_map(
            static fn($m) => $m->metricName,
            $provider->metrics,
        );
        sort($providerMetricNames);

        foreach ($this->templates->all() as $template) {
            if ($template->bridge !== $provider->bridge) {
                continue;
            }

            $templateMetricNames = array_map(
                static fn(array $m): string => $m['metricName'],
                $template->metrics,
            );
            sort($templateMetricNames);

            if ($providerMetricNames === $templateMetricNames) {
                return $template;
            }
        }

        return null;
    }
}
