<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Debug\Toolbar\Panel;

use WPPack\Component\Debug\CssTheme;

final class ToolbarAssets
{
    private const ASSETS_DIR = __DIR__ . '/../assets';

    public function renderCss(): string
    {
        $css = file_get_contents(self::ASSETS_DIR . '/toolbar.css');
        if ($css === false) {
            return '';
        }

        return str_replace('{{cssVariables}}', CssTheme::cssVariables(), $css);
    }

    public function renderJs(): string
    {
        $js = file_get_contents(self::ASSETS_DIR . '/toolbar.js');

        return $js === false ? '' : $js;
    }
}
