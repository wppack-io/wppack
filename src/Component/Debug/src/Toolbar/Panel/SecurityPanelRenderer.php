<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;

#[AsPanelRenderer(name: 'security')]
final class SecurityPanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'security';
    }

    public function renderPanel(): string
    {
        $data = $this->getCollectorData();
        $isLoggedIn = (bool) ($data['is_logged_in'] ?? false);
        $username = (string) ($data['username'] ?? '');
        $displayName = (string) ($data['display_name'] ?? '');
        $email = (string) ($data['email'] ?? '');
        /** @var list<string> $roles */
        $roles = $data['roles'] ?? [];
        $isSuperAdmin = (bool) ($data['is_super_admin'] ?? false);
        $auth = (string) ($data['authentication'] ?? 'none');

        // User section
        $html = '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">User</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('Logged In', $this->formatValue($isLoggedIn));
        $html .= $this->renderTableRow('Username', $this->esc($username ?: '-'));
        $html .= $this->renderTableRow('Display Name', $this->esc($displayName ?: '-'));
        $html .= $this->renderTableRow('Email', $this->esc($email ?: '-'));
        $html .= $this->renderTableRow('Authentication', $this->esc($auth));
        if ($isSuperAdmin) {
            $html .= $this->renderTableRow('Super Admin', '<span class="wpd-text-yellow">Yes</span>');
        }
        $html .= '</table>';
        $html .= '</div>';

        // Roles section
        if ($roles !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Roles</h4>';
            $html .= '<div class="wpd-tag-list">';
            foreach ($roles as $role) {
                $html .= '<span class="wpd-tag">' . $this->esc($role) . '</span>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }

        // Capabilities section
        /** @var array<string, bool> $capabilities */
        $capabilities = $data['capabilities'] ?? [];
        if ($capabilities !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Capabilities (' . $this->esc((string) count($capabilities)) . ')</h4>';
            $html .= '<div class="wpd-tag-list">';
            foreach ($capabilities as $cap => $granted) {
                $color = $granted ? 'wpd-text-green' : 'wpd-text-red';
                $html .= '<span class="wpd-tag ' . $color . '">' . $this->esc($cap) . '</span>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }

        // Nonce Operations section
        /** @var list<array{action: string, operation: string, result: bool, timestamp: float}> $nonceOps */
        $nonceOps = $data['nonce_operations'] ?? [];
        $nonceVerifyCount = (int) ($data['nonce_verify_count'] ?? 0);
        $nonceFailures = (int) ($data['nonce_verify_failures'] ?? 0);

        $html .= '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Nonce Operations</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('Total Verifications', (string) $nonceVerifyCount);
        $html .= $this->renderTableRow('Failures', (string) $nonceFailures, $nonceFailures > 0 ? 'wpd-text-red' : '');
        $html .= '</table>';

        if ($nonceOps !== []) {
            $html .= '<table class="wpd-table wpd-table-full" style="margin-top:8px">';
            $html .= '<thead><tr>';
            $html .= '<th class="wpd-col-reltime">Time</th>';
            $html .= '<th>Action</th>';
            $html .= '<th>Operation</th>';
            $html .= '<th>Result</th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';

            foreach ($nonceOps as $op) {
                $resultHtml = $op['result']
                    ? '<span class="wpd-text-green">pass</span>'
                    : '<span class="wpd-text-red">fail</span>';

                $html .= '<tr>';
                $html .= '<td class="wpd-col-reltime wpd-text-dim">' . $this->formatRelativeTime($op['timestamp']) . '</td>';
                $html .= '<td><code>' . $this->esc($op['action']) . '</code></td>';
                $html .= '<td>' . $this->esc($op['operation']) . '</td>';
                $html .= '<td>' . $resultHtml . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
        }

        $html .= '</div>';

        return $html;
    }
}
