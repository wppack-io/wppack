<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;
use WpPack\Component\Debug\Profiler\Profile;

#[AsPanelRenderer(name: 'environment')]
final class EnvironmentPanelRenderer extends AbstractPanelRenderer implements PanelRendererInterface
{
    public function getName(): string
    {
        return 'environment';
    }

    public function render(Profile $profile): string
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

        $html = '';

        // PHP section
        $html .= '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">PHP</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('Version', $this->esc((string) ($php['version'] ?? '')));
        $html .= $this->renderTableRow('SAPI', $this->esc((string) ($data['sapi'] ?? '')));
        $html .= $this->renderTableRow('Zend Engine', $this->esc((string) ($php['zend_version'] ?? '')));
        $html .= $this->renderTableRow('Architecture', $this->esc((string) ($data['architecture'] ?? 64)) . '-bit');
        $html .= $this->renderTableRow('Thread Safe', $this->formatValue($php['zts'] ?? false));
        $html .= $this->renderTableRow('Debug Build', $this->formatValue($php['debug'] ?? false));
        $html .= $this->renderTableRow('GC Enabled', $this->formatValue($php['gc_enabled'] ?? false));
        $html .= $this->renderTableRow('OS', $this->esc((string) ($data['os'] ?? '')));
        $hostname = (string) ($data['hostname'] ?? '');
        if ($hostname !== '') {
            $html .= $this->renderTableRow('Hostname', $this->esc($hostname));
        }
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

        // Configuration section
        $html .= '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Configuration</h4>';
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

        // Extensions section
        if ($extensions !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Extensions (' . $this->esc((string) count($extensions)) . ')</h4>';
            $html .= '<div class="wpd-tag-list">';
            foreach ($extensions as $ext) {
                $html .= '<span class="wpd-tag">' . $this->esc($ext) . '</span>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }

        return $html;
    }
}
