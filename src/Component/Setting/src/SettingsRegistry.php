<?php

declare(strict_types=1);

namespace WpPack\Component\Setting;

use WpPack\Component\HttpFoundation\ArgumentResolver;
use WpPack\Component\Templating\TemplateRendererInterface;

final class SettingsRegistry
{
    public function __construct(
        private readonly ?TemplateRendererInterface $renderer = null,
        private readonly ?ArgumentResolver $argumentResolver = null,
    ) {}

    public function register(AbstractSettingsPage $page): void
    {
        if ($this->renderer !== null) {
            $page->setTemplateRenderer($this->renderer);
        }

        $resolver = $this->argumentResolver?->createResolver($page);
        if ($resolver !== null) {
            $page->setInvokeArgumentResolver($resolver);
        }

        add_action('admin_menu', $page->addMenuPage(...));
        add_action('admin_init', $page->initSettings(...));
    }
}
