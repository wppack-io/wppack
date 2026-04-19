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

namespace WPPack\Component\Shortcode;

final class ShortcodeRegistry
{
    public function register(AbstractShortcode $shortcode): void
    {
        add_shortcode($shortcode->name, static function (array|string $atts, ?string $content) use ($shortcode): string {
            $rawAtts = \is_array($atts) ? $atts : [];

            return $shortcode->render(
                $shortcode->resolveAttributes($rawAtts),
                $content ?? '',
            );
        });
    }

    public function unregister(string $shortcodeName): void
    {
        remove_shortcode($shortcodeName);
    }
}
