<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

final class DumpPanelRenderer extends AbstractPanelRenderer implements PanelRendererInterface
{
    public function getName(): string
    {
        return 'dump';
    }

    public function render(array $data): string
    {
        /** @var list<array<string, mixed>> $dumps */
        $dumps = $data['dumps'] ?? [];
        $totalCount = (int) ($data['total_count'] ?? 0);

        if ($dumps === []) {
            return '<div class="wpd-section"><p class="wpd-text-dim">No dump() calls recorded.</p></div>';
        }

        $html = '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Dumps (' . $this->esc((string) $totalCount) . ')</h4>';

        foreach ($dumps as $index => $dump) {
            $file = $dump['file'] ?? 'unknown';
            $line = $dump['line'] ?? 0;
            $dumpData = $dump['data'] ?? '';

            $html .= '<div style="margin-bottom:12px">';
            $html .= '<div class="wpd-text-dim" style="font-size:11px;margin-bottom:4px">';
            $html .= '#' . $this->esc((string) ($index + 1)) . ' ' . $this->esc($file) . ':' . $this->esc((string) $line);
            $html .= '</div>';
            $html .= '<pre style="background:#181825;padding:8px 12px;border-radius:4px;overflow-x:auto;font-size:12px;color:#cdd6f4;margin:0">';
            $html .= $this->esc($dumpData);
            $html .= '</pre>';
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }
}
