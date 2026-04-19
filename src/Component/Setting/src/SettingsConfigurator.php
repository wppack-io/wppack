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

namespace WPPack\Component\Setting;

final class SettingsConfigurator
{
    /** @var list<SectionDefinition> */
    private array $sections = [];

    public function __construct(
        private readonly ?AbstractSettingsPage $page = null,
    ) {}

    public function section(string $id, string $title, ?\Closure $renderCallback = null): SectionDefinition
    {
        $section = new SectionDefinition($id, $title, $renderCallback, $this->page);
        $this->sections[] = $section;

        return $section;
    }

    /**
     * @return list<SectionDefinition>
     */
    public function getSections(): array
    {
        return $this->sections;
    }
}
