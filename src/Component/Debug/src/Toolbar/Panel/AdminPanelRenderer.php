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

namespace WPPack\Component\Debug\Toolbar\Panel;

use WPPack\Component\Debug\Attribute\AsPanelRenderer;

#[AsPanelRenderer(name: 'admin')]
final class AdminPanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'admin';
    }

    public function renderPanel(): string
    {
        $data = $this->getCollectorData();

        return $this->getPhpRenderer()->render('toolbar/panels/admin', [
            'isAdmin' => (bool) ($data['is_admin'] ?? false),
            'pageHook' => (string) ($data['page_hook'] ?? ''),
            'screen' => $data['screen'] ?? [],
            'adminMenus' => $data['admin_menus'] ?? [],
            'adminBarNodes' => $data['admin_bar_nodes'] ?? [],
            'totalMenus' => (int) ($data['total_menus'] ?? 0),
            'totalSubmenus' => (int) ($data['total_submenus'] ?? 0),
        ]);
    }
}
