<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;

#[AsPanelRenderer(name: 'ajax')]
final class AjaxPanelRenderer extends AbstractPanelRenderer implements PanelRendererInterface
{
    public function getName(): string
    {
        return 'ajax';
    }

    public function render(array $data): string
    {
        $totalActions = (int) ($data['total_actions'] ?? 0);
        $noprivCount = (int) ($data['nopriv_count'] ?? 0);
        /** @var array<string, array{callback: string, nopriv: bool}> $actions */
        $actions = $data['registered_actions'] ?? [];

        // Registered Actions
        $html = '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Registered Actions (' . $totalActions . ')</h4>';

        if ($actions !== []) {
            $html .= '<table class="wpd-table wpd-table-full">';
            $html .= '<thead><tr>';
            $html .= '<th>Action</th>';
            $html .= '<th>Callback</th>';
            $html .= '<th>NoPriv</th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';

            foreach ($actions as $action => $info) {
                $noprivHtml = $info['nopriv']
                    ? '<span class="wpd-text-yellow">Yes</span>'
                    : '<span class="wpd-text-dim">No</span>';

                $html .= '<tr>';
                $html .= '<td><code>' . $this->esc($action) . '</code></td>';
                $html .= '<td class="wpd-text-dim">' . $this->esc($info['callback']) . '</td>';
                $html .= '<td>' . $noprivHtml . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
        } else {
            $html .= '<p class="wpd-text-dim">No registered ajax actions.</p>';
        }

        $html .= '</div>';

        // Client-Side Requests (populated by JS)
        $html .= '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Client-Side Requests</h4>';
        $html .= '<table class="wpd-table wpd-table-full">';
        $html .= '<thead><tr>';
        $html .= '<th>Action</th>';
        $html .= '<th>Method</th>';
        $html .= '<th>Status</th>';
        $html .= '<th class="wpd-col-right">Duration</th>';
        $html .= '<th class="wpd-col-right">Size</th>';
        $html .= '</tr></thead>';
        $html .= '<tbody id="wpd-ajax-tbody">';
        $html .= '</tbody></table>';
        $html .= '<p class="wpd-text-dim" id="wpd-ajax-empty" style="margin-top:4px">No requests captured yet.</p>';
        $html .= '</div>';

        return $html;
    }
}
