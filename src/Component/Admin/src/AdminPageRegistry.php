<?php

declare(strict_types=1);

namespace WpPack\Component\Admin;

final class AdminPageRegistry
{
    public function register(AbstractAdminPage $page): void
    {
        add_action('admin_menu', $page->addMenuPage(...));

        if ($page->hasEnqueueScriptsOverride() || $page->hasEnqueueStylesOverride()) {
            add_action('admin_enqueue_scripts', $page->handleEnqueue(...));
        }
    }

    public function remove(string $menuSlug): void
    {
        remove_menu_page($menuSlug);
    }

    public function removeSubmenu(string $parentSlug, string $menuSlug): void
    {
        remove_submenu_page($parentSlug, $menuSlug);
    }
}
