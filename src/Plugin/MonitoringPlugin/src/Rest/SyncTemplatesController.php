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

namespace WpPack\Plugin\MonitoringPlugin\Rest;

use Psr\Log\LoggerInterface;
use WpPack\Component\HttpFoundation\JsonResponse;
use WpPack\Component\Monitoring\MonitoringStore;
use WpPack\Component\Rest\AbstractRestController;
use WpPack\Component\Rest\Attribute\RestRoute;
use WpPack\Component\Rest\HttpMethod;
use WpPack\Component\Role\Attribute\IsGranted;
use WpPack\Plugin\MonitoringPlugin\Template\MetricTemplateRegistry;

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
            if ($provider->locked || $provider->templateId === null) {
                continue;
            }

            $template = $this->templates->get($provider->templateId);
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
}
