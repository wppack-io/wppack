<?php

declare(strict_types=1);

namespace WpPack\Component\Setting;

use WpPack\Component\HttpFoundation\InvokeArgumentResolverTrait;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Security\Security;
use WpPack\Component\Templating\TemplateRendererInterface;

final class SettingsRegistry
{
    use InvokeArgumentResolverTrait;

    public function __construct(
        private readonly ?TemplateRendererInterface $renderer = null,
        private readonly ?Request $request = null,
        private readonly ?Security $security = null,
    ) {}

    public function register(AbstractSettingsPage $page): void
    {
        if ($this->renderer !== null) {
            $page->setTemplateRenderer($this->renderer);
        }

        $resolver = $this->createArgumentResolver($page, $this->request, $this->security);
        if ($resolver !== null) {
            $page->setInvokeArgumentResolver($resolver);
        }

        add_action('admin_menu', $page->addMenuPage(...));
        add_action('admin_init', $page->initSettings(...));
    }
}
