<?php

declare(strict_types=1);

namespace WpPack\Component\Setting;

final class SettingsRegistry
{
    public function register(AbstractSettingsPage $page): void
    {
        add_action('admin_menu', $page->addMenuPage(...));
        add_action('admin_init', $page->initSettings(...));
    }
}
