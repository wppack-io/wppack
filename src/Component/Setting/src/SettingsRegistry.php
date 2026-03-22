<?php

declare(strict_types=1);

namespace WpPack\Component\Setting;

use WpPack\Component\Templating\TemplateRendererInterface;

final class SettingsRegistry
{
    public function __construct(
        private readonly ?TemplateRendererInterface $renderer = null,
    ) {}

    public function register(AbstractSettingsPage $page): void
    {
        if ($this->renderer !== null) {
            $page->setTemplateRenderer($this->renderer);
        }

        add_action('admin_menu', $page->addMenuPage(...));
        add_action('admin_init', $page->initSettings(...));
    }
}
