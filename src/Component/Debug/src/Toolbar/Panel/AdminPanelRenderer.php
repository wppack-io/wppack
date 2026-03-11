<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;

#[AsPanelRenderer(name: 'admin')]
final class AdminPanelRenderer extends AbstractPanelRenderer implements PanelRendererInterface
{
    public function getName(): string
    {
        return 'admin';
    }

    public function render(array $data): string
    {
        $isAdmin = (bool) ($data['is_admin'] ?? false);

        if (!$isAdmin) {
            return '<div class="wpd-section"><h4 class="wpd-section-title">Admin</h4>'
                . '<p class="wpd-text-dim">Not in admin context.</p></div>';
        }

        $pageHook = (string) ($data['page_hook'] ?? '');
        /** @var array<string, string> $screen */
        $screen = $data['screen'] ?? [];
        /** @var list<array{title: string, slug: string, capability: string, submenu?: list<array{title: string, slug: string}>}> $adminMenus */
        $adminMenus = $data['admin_menus'] ?? [];
        /** @var list<array{id: string, title: string}> $adminBarNodes */
        $adminBarNodes = $data['admin_bar_nodes'] ?? [];
        $totalMenus = (int) ($data['total_menus'] ?? 0);
        $totalSubmenus = (int) ($data['total_submenus'] ?? 0);

        // Current Screen
        $html = '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Current Screen</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('Page Hook', $this->esc($pageHook ?: '-'));
        if ($screen !== []) {
            $html .= $this->renderTableRow('Screen ID', $this->esc((string) ($screen['id'] ?? '-')));
            $html .= $this->renderTableRow('Base', $this->esc((string) ($screen['base'] ?? '-')));
            if (($screen['post_type'] ?? '') !== '') {
                $html .= $this->renderTableRow('Post Type', $this->esc($screen['post_type']));
            }
            if (($screen['taxonomy'] ?? '') !== '') {
                $html .= $this->renderTableRow('Taxonomy', $this->esc($screen['taxonomy']));
            }
        }
        $html .= '</table>';
        $html .= '</div>';

        // Admin Menus
        if ($adminMenus !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Admin Menus (' . $totalMenus . ' menus, ' . $totalSubmenus . ' submenus)</h4>';
            $html .= '<table class="wpd-table wpd-table-full">';
            $html .= '<thead><tr>';
            $html .= '<th>Menu</th>';
            $html .= '<th>Slug</th>';
            $html .= '<th>Capability</th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';

            foreach ($adminMenus as $menuItem) {
                $html .= '<tr>';
                $html .= '<td><strong>' . $this->esc($menuItem['title']) . '</strong></td>';
                $html .= '<td><code>' . $this->esc($menuItem['slug']) . '</code></td>';
                $html .= '<td>' . $this->esc($menuItem['capability']) . '</td>';
                $html .= '</tr>';

                if (isset($menuItem['submenu'])) {
                    foreach ($menuItem['submenu'] as $subItem) {
                        $html .= '<tr>';
                        $html .= '<td style="padding-left:24px" class="wpd-text-dim">' . $this->esc($subItem['title']) . '</td>';
                        $html .= '<td><code class="wpd-text-dim">' . $this->esc($subItem['slug']) . '</code></td>';
                        $html .= '<td></td>';
                        $html .= '</tr>';
                    }
                }
            }

            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        // Admin Bar Nodes
        if ($adminBarNodes !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Admin Bar Nodes</h4>';
            $html .= '<table class="wpd-table wpd-table-full">';
            $html .= '<thead><tr>';
            $html .= '<th>ID</th>';
            $html .= '<th>Title</th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';

            foreach ($adminBarNodes as $node) {
                $html .= '<tr>';
                $html .= '<td><code>' . $this->esc($node['id']) . '</code></td>';
                $html .= '<td>' . $this->esc($node['title']) . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        return $html;
    }
}
