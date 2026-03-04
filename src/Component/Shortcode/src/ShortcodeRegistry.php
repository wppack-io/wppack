<?php

declare(strict_types=1);

namespace WpPack\Component\Shortcode;

final class ShortcodeRegistry
{
    public function register(AbstractShortcode $shortcode): void
    {
        add_shortcode($shortcode->name, static fn(array|string $atts, ?string $content): string => $shortcode->render(
            \is_array($atts) ? $atts : [],
            $content ?? '',
        ));
    }

    public function unregister(string $shortcodeName): void
    {
        remove_shortcode($shortcodeName);
    }
}
