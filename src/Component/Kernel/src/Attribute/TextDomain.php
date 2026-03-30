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

namespace WpPack\Component\Kernel\Attribute;

/**
 * Declares a text domain for automatic loading by the Kernel.
 *
 * Place on a plugin class extending AbstractPlugin. The Kernel reads
 * this attribute via reflection and calls load_plugin_textdomain()
 * before boot().
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class TextDomain
{
    public function __construct(
        public readonly string $domain,
        public readonly string $path = 'languages',
    ) {}
}
