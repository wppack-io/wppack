<?php

declare(strict_types=1);

namespace WpPack\Component\Shortcode;

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
