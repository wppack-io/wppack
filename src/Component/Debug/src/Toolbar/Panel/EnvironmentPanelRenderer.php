<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;
use WpPack\Component\Debug\Profiler\Profile;

#[AsPanelRenderer(name: 'environment')]
final class EnvironmentPanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'environment';
    }

    public function renderBadge(Profile $profile): string
    {
        $parts = [];
        $tooltipLines = [];

        // PHP version
        $parts[] = 'PHP ' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        $tooltipLines[] = 'PHP ' . PHP_VERSION;

        // Runtime or server software
        $envData = $this->getCollectorData($profile, 'environment');
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

        // Additional tooltip info
        $wpData = $this->getCollectorData($profile, 'wordpress');
        $wpVersion = (string) ($wpData['wp_version'] ?? '');
        if ($wpVersion !== '') {
            $tooltipLines[] = 'WordPress ' . $wpVersion;
        }
        $envType = (string) ($wpData['environment_type'] ?? '');
        if ($envType !== '') {
            $tooltipLines[] = 'Env: ' . $envType;
        }

        $memData = $this->getCollectorData($profile, 'memory');
        $limit = (int) ($memData['limit'] ?? 0);
        if ($limit > 0) {
            $tooltipLines[] = 'Memory Limit: ' . $this->formatBytes($limit);
        }

        $labelParts = '';
        foreach ($parts as $i => $part) {
            if ($i > 0) {
                $labelParts .= '<span class="wpd-env-sep"></span>';
            }
            $labelParts .= $this->esc($part);
        }
        $tooltipHtml = '';
        foreach ($tooltipLines as $line) {
            $tooltipHtml .= '<div>' . $this->esc($line) . '</div>';
        }

        return '<div class="wpd-bar-env" data-panel="environment">'
            . '<span class="wpd-env-label">' . $labelParts . '</span>'
            . '<div class="wpd-env-tooltip">' . $tooltipHtml . '</div>'
            . '</div>';
    }

    public function renderPanel(Profile $profile): string
    {
        $data = $this->getCollectorData($profile, $this->getName());
        /** @var array<string, mixed> $php */
        $php = $data['php'] ?? [];
        /** @var list<string> $extensions */
        $extensions = $data['extensions'] ?? [];
        /** @var array<string, string> $ini */
        $ini = $data['ini'] ?? [];
        /** @var array<string, mixed> $opcache */
        $opcache = $data['opcache'] ?? [];
        /** @var array<string, string> $server */
        $server = $data['server'] ?? [];

        $html = '';

        // PHP Runtime section
        $html .= '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">PHP Runtime</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('Version', $this->esc((string) ($php['version'] ?? '')));
        $html .= $this->renderTableRow('SAPI', $this->esc((string) ($data['sapi'] ?? '')));
        $html .= $this->renderTableRow('Zend Engine', $this->esc((string) ($php['zend_version'] ?? '')));
        $html .= $this->renderTableRow('Architecture', $this->esc((string) ($data['architecture'] ?? 64)) . '-bit');
        $html .= $this->renderTableRow('Thread Safe', $this->formatValue($php['zts'] ?? false));
        $html .= $this->renderTableRow('Debug Build', $this->formatValue($php['debug'] ?? false));
        $html .= $this->renderTableRow('GC Enabled', $this->formatValue($php['gc_enabled'] ?? false));
        $html .= '</table>';
        $html .= '</div>';

        // OPcache section
        $html .= '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">OPcache</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $opcacheEnabled = (bool) ($opcache['enabled'] ?? false);
        $html .= $this->renderTableRow('Enabled', $this->formatValue($opcacheEnabled));
        if ($opcacheEnabled) {
            $html .= $this->renderTableRow('JIT', $this->formatValue($opcache['jit'] ?? false));
            $html .= $this->renderTableRow('Cached Scripts', $this->esc((string) ($opcache['cached_scripts'] ?? 0)));
            $hitRate = (float) ($opcache['hit_rate'] ?? 0);
            $hitColor = $hitRate >= 95 ? 'wpd-text-green' : ($hitRate >= 80 ? 'wpd-text-yellow' : 'wpd-text-red');
            $html .= $this->renderTableRow('Hit Rate', '<span class="' . $hitColor . '">' . $this->esc(sprintf('%.1f%%', $hitRate)) . '</span>');
            $usedMb = (int) ($opcache['used_memory'] ?? 0) / 1048576;
            $freeMb = (int) ($opcache['free_memory'] ?? 0) / 1048576;
            $html .= $this->renderTableRow('Memory', $this->esc(sprintf('%.1f MB used / %.1f MB free', $usedMb, $freeMb)));
            $wastedPct = (float) ($opcache['wasted_percentage'] ?? 0);
            if ($wastedPct > 0) {
                $html .= $this->renderTableRow('Wasted', '<span class="wpd-text-yellow">' . $this->esc(sprintf('%.1f%%', $wastedPct)) . '</span>');
            }
            $oomRestarts = (int) ($opcache['oom_restarts'] ?? 0);
            if ($oomRestarts > 0) {
                $html .= $this->renderTableRow('OOM Restarts', '<span class="wpd-text-red">' . $this->esc((string) $oomRestarts) . '</span>');
            }
        }
        $html .= '</table>';
        $html .= '</div>';

        // PHP Configuration section
        $html .= '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">PHP Configuration</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        foreach ($ini as $key => $value) {
            $displayValue = $value;
            if ($key === 'disable_functions' && $value !== '') {
                $count = count(explode(',', $value));
                $displayValue = $count . ' functions disabled';
            }
            $html .= $this->renderTableRow($key, $this->esc($displayValue !== '' ? $displayValue : '(empty)'));
        }
        $html .= '</table>';
        $html .= '</div>';

        // PHP Extensions section
        if ($extensions !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">PHP Extensions (' . $this->esc((string) count($extensions)) . ')</h4>';
            $html .= '<div class="wpd-tag-list">';
            foreach ($extensions as $ext) {
                $html .= '<span class="wpd-tag">' . $this->esc($ext) . '</span>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }

        // Web Server section
        $html .= '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Web Server</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        /** @var array<string, string> $webServer */
        $webServer = $server['web_server'] ?? ['name' => '', 'version' => '', 'raw' => ''];
        $webServerName = (string) ($webServer['name'] ?? '');
        $webServerVersion = (string) ($webServer['version'] ?? '');
        if ($webServerName !== '') {
            $softwareDisplay = $webServerVersion !== '' ? $webServerName . ' ' . $webServerVersion : $webServerName;
            $html .= $this->renderTableRow('Software', $this->esc($softwareDisplay));
        } else {
            $html .= $this->renderTableRow('Software', '<span class="wpd-text-muted">(not available)</span>');
        }
        $protocol = (string) ($server['protocol'] ?? '');
        if ($protocol !== '') {
            $html .= $this->renderTableRow('Protocol', $this->esc($protocol));
        }
        $documentRoot = (string) ($server['document_root'] ?? '');
        if ($documentRoot !== '') {
            $html .= $this->renderTableRow('Document Root', $this->esc($documentRoot));
        }
        $port = (string) ($server['port'] ?? '');
        if ($port !== '') {
            $html .= $this->renderTableRow('Port', $this->esc($port));
        }
        $html .= '</table>';
        $html .= '</div>';

        // Infrastructure section
        /** @var array<string, mixed> $runtime */
        $runtime = $data['runtime'] ?? ['type' => '', 'details' => []];
        $runtimeType = (string) ($runtime['type'] ?? '');
        /** @var array<string, string> $runtimeDetails */
        $runtimeDetails = $runtime['details'] ?? [];

        $runtimeLabels = [
            'lambda' => 'Lambda',
            'ecs' => 'ECS',
            'kubernetes' => 'Kubernetes',
            'docker' => 'Docker',
            'ec2' => 'EC2',
        ];

        $html .= '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Infrastructure</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        if ($runtimeType !== '' && isset($runtimeLabels[$runtimeType])) {
            $html .= $this->renderTableRow('Runtime', '<span class="wpd-tag">' . $this->esc($runtimeLabels[$runtimeType]) . '</span>');
        }
        $html .= $this->renderTableRow('OS', $this->esc((string) ($data['os'] ?? '')));
        $hostname = (string) ($data['hostname'] ?? '');
        if ($hostname !== '') {
            $html .= $this->renderTableRow('Hostname', $this->esc($hostname));
        }
        foreach ($runtimeDetails as $detailKey => $detailValue) {
            if ($detailKey === 'Hostname' && $detailValue === $hostname) {
                continue;
            }
            $html .= $this->renderTableRow($detailKey, $this->esc($detailValue));
        }
        $html .= '</table>';
        $html .= '</div>';

        return $html;
    }
}
