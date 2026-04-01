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

namespace WpPack\Component\Admin;

use WpPack\Component\Templating\TemplateRendererInterface;

final class AdminPageRegistry
{
    public function __construct(
        private readonly ?TemplateRendererInterface $renderer = null,
    ) {}

    public function register(AbstractAdminPage $page, bool $network = false): void
    {
        $page->setNetwork($network);

        if ($this->renderer !== null) {
            $page->setTemplateRenderer($this->renderer);
        }

        $menuHook = $page->isNetwork() ? 'network_admin_menu' : 'admin_menu';
        add_action($menuHook, $page->addMenuPage(...));

        if ($page->hasEnqueueOverride()) {
            add_action('admin_enqueue_scripts', $page->handleEnqueue(...));
        }
    }

    public function unregister(string $menuSlug): void
    {
        remove_menu_page($menuSlug);
    }

    public function unregisterSubmenu(string $parentSlug, string $menuSlug): void
    {
        remove_submenu_page($parentSlug, $menuSlug);
    }
}
