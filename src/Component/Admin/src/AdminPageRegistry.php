<?php

declare(strict_types=1);

namespace WpPack\Component\Admin;

use WpPack\Component\Templating\TemplateRendererInterface;

final class AdminPageRegistry
{
    public function __construct(
        private readonly ?TemplateRendererInterface $renderer = null,
    ) {}

    public function register(AbstractAdminPage $page): void
    {
        if ($this->renderer !== null) {
            $page->setTemplateRenderer($this->renderer);
        }

        add_action('admin_menu', $page->addMenuPage(...));

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
