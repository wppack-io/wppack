<?php

declare(strict_types=1);

namespace WpPack\Component\Setting;

final class SettingsConfigurator
{
    /** @var list<SectionDefinition> */
    private array $sections = [];

    public function section(string $id, string $title, ?\Closure $renderCallback = null): SectionDefinition
    {
        $section = new SectionDefinition($id, $title, $renderCallback);
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
