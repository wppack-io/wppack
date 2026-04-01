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

namespace WpPack\Component\Setting;

use WpPack\Component\Option\OptionManager;
use WpPack\Component\Templating\TemplateRendererInterface;

final class SettingsRegistry
{
    public function __construct(
        private readonly ?OptionManager $optionManager = null,
        private readonly ?TemplateRendererInterface $renderer = null,
    ) {}

    public function register(AbstractSettingsPage $page, bool $network = false): void
    {
        $page->setNetwork($network);

        if ($this->optionManager !== null) {
            $page->setOptionManager($this->optionManager);
        }

        if ($this->renderer !== null) {
            $page->setTemplateRenderer($this->renderer);
        }

        $menuHook = $page->isNetwork() ? 'network_admin_menu' : 'admin_menu';
        add_action($menuHook, $page->addMenuPage(...));
        add_action('admin_init', $page->initSettings(...));
    }
}
