<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;

#[AsPanelRenderer(name: 'user')]
final class UserPanelRenderer extends AbstractPanelRenderer implements PanelRendererInterface
{
    public function getName(): string
    {
        return 'user';
    }

    public function render(array $data): string
    {
        $isLoggedIn = (bool) ($data['is_logged_in'] ?? false);
        $username = (string) ($data['username'] ?? '');
        $displayName = (string) ($data['display_name'] ?? '');
        $email = (string) ($data['email'] ?? '');
        /** @var list<string> $roles */
        $roles = $data['roles'] ?? [];
        $isSuperAdmin = (bool) ($data['is_super_admin'] ?? false);
        $auth = (string) ($data['authentication'] ?? 'none');

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

        return $html;
    }
}
