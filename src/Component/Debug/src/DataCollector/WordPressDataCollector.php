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

namespace WpPack\Component\Debug\DataCollector;

use WpPack\Component\Debug\Attribute\AsDataCollector;

#[AsDataCollector(name: 'wordpress', priority: 40)]
final class WordPressDataCollector extends AbstractDataCollector
{
    public function getName(): string
    {
        return 'wordpress';
    }

    public function getLabel(): string
    {
        return 'WordPress';
    }

    public function collect(): void
    {
        $this->data = [
            'wp_version' => $this->getWpVersion(),
            'environment_type' => $this->getEnvironmentType(),
            'is_multisite' => $this->isMultisite(),
            'constants' => $this->getDebugConstants(),
        ];
    }

    public function getIndicatorValue(): string
    {
        return $this->data['wp_version'] ?? '';
    }

    public function getIndicatorColor(): string
    {
        return 'default';
    }

    private function getWpVersion(): string
    {
        global $wp_version;

        if (isset($wp_version) && is_string($wp_version)) {
            return $wp_version;
        }

        return '';
    }

    private function getEnvironmentType(): string
    {
        return wp_get_environment_type();
    }

    private function isMultisite(): bool
    {
        return is_multisite();
    }

    /**
     * @return array<string, bool|null>
     */
    private function getDebugConstants(): array
    {
        $constants = [
            'WP_DEBUG',
            'SAVEQUERIES',
            'SCRIPT_DEBUG',
            'WP_DEBUG_LOG',
            'WP_DEBUG_DISPLAY',
            'WP_CACHE',
        ];

        $result = [];
        foreach ($constants as $constant) {
            $result[$constant] = defined($constant) ? constant($constant) : null;
        }

        return $result;
    }
}
