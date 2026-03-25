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

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;

#[AsPanelRenderer(name: 'environment')]
final class EnvironmentPanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'environment';
    }

    public function renderIndicator(): string
    {
        $parts = [];
        $tooltipLines = [];

        $parts[] = 'PHP ' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        $tooltipLines[] = 'PHP ' . PHP_VERSION;

        $envData = $this->getCollectorData('environment');
        /** @var array<string, mixed> $server */
        $server = $envData['server'] ?? [];
        /** @var array<string, mixed> $runtime */
        $runtime = $envData['runtime'] ?? ['type' => '', 'details' => []];
        $runtimeType = (string) ($runtime['type'] ?? '');

        $runtimeLabels = [
            'lambda' => 'Lambda',
            'ecs' => 'ECS',
            'kubernetes' => 'K8s',
        ];

        if (isset($runtimeLabels[$runtimeType])) {
            $parts[] = $runtimeLabels[$runtimeType];
            $tooltipLines[] = $runtimeLabels[$runtimeType];
        } else {
            /** @var array<string, string> $webServer */
            $webServer = $server['web_server'] ?? ['name' => '', 'version' => '', 'raw' => ''];
            $webServerName = (string) ($webServer['name'] ?? '');
            if ($webServerName !== '') {
                $parts[] = $webServerName;
                $raw = (string) ($webServer['raw'] ?? '');
                $tooltipLines[] = $raw !== '' ? $raw : $webServerName;
            }
        }

        $wpData = $this->getCollectorData('wordpress');
        $wpVersion = (string) ($wpData['wp_version'] ?? '');
        if ($wpVersion !== '') {
            $tooltipLines[] = 'WordPress ' . $wpVersion;
        }
        $envType = (string) ($wpData['environment_type'] ?? '');
        if ($envType !== '') {
            $tooltipLines[] = 'Env: ' . $envType;
        }

        $memData = $this->getCollectorData('memory');
        $limit = (int) ($memData['limit'] ?? 0);
        if ($limit > 0) {
            $tooltipLines[] = 'Memory Limit: ' . $this->getFormatters()->bytes($limit);
        }

        $labelParts = '';
        foreach ($parts as $i => $part) {
            if ($i > 0) {
                $labelParts .= '<span class="wpd-env-sep"></span>';
            }
            $labelParts .= htmlspecialchars($part, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        $tooltipHtml = '';
        foreach ($tooltipLines as $line) {
            $tooltipHtml .= '<div>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';
        }

        return $this->getPhpRenderer()->render('toolbar/indicators/environment', [
            'labelParts' => $labelParts,
            'tooltipHtml' => $tooltipHtml,
        ]);
    }

    public function renderPanel(): string
    {
        return $this->getPhpRenderer()->render('toolbar/panels/environment', [
            'data' => $this->getCollectorData(),
            'fmt' => $this->getFormatters(),
        ]);
    }
}
