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
