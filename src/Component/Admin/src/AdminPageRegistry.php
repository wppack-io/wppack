<?php

declare(strict_types=1);

namespace WpPack\Component\Admin;

use WpPack\Component\HttpFoundation\InvokeArgumentResolverTrait;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Security\Security;
use WpPack\Component\Templating\TemplateRendererInterface;

final class AdminPageRegistry
{
    use InvokeArgumentResolverTrait;

    public function __construct(
        private readonly ?TemplateRendererInterface $renderer = null,
        private readonly ?Request $request = null,
        private readonly ?Security $security = null,
    ) {}

    public function register(AbstractAdminPage $page): void
    {
        if ($this->renderer !== null) {
            $page->setTemplateRenderer($this->renderer);
        }

        $resolver = $this->createArgumentResolver($page, $this->request, $this->security);
        if ($resolver !== null) {
            $page->setInvokeArgumentResolver($resolver);
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
