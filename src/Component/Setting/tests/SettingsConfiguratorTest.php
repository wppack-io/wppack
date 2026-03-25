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

namespace WpPack\Component\Setting\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Setting\FieldDefinition;
use WpPack\Component\Setting\SectionDefinition;
use WpPack\Component\Setting\SettingsConfigurator;

final class SettingsConfiguratorTest extends TestCase
{
    #[Test]
    public function sectionCreatesSectionDefinition(): void
    {
        $configurator = new SettingsConfigurator();

        $section = $configurator->section('general', 'General Settings');

        self::assertInstanceOf(SectionDefinition::class, $section);
        self::assertSame('general', $section->id);
        self::assertSame('General Settings', $section->title);
    }

    #[Test]
    public function sectionWithRenderCallback(): void
    {
        $configurator = new SettingsConfigurator();
        $callback = static fn() => null;

        $section = $configurator->section('general', 'General', $callback);

        self::assertSame($callback, $section->renderCallback);
    }

    #[Test]
    public function sectionRenderCallbackDefaultsToNull(): void
    {
        $configurator = new SettingsConfigurator();

        $section = $configurator->section('general', 'General');

        self::assertNull($section->renderCallback);
    }

    #[Test]
    public function getSectionsReturnsAllSections(): void
    {
        $configurator = new SettingsConfigurator();

        $configurator->section('general', 'General');
        $configurator->section('advanced', 'Advanced');

        $sections = $configurator->getSections();

        self::assertCount(2, $sections);
        self::assertSame('general', $sections[0]->id);
        self::assertSame('advanced', $sections[1]->id);
    }

    #[Test]
    public function getSectionsReturnsEmptyArrayByDefault(): void
    {
        $configurator = new SettingsConfigurator();

        self::assertSame([], $configurator->getSections());
    }

    #[Test]
    public function fieldAddsFieldToSection(): void
    {
        $configurator = new SettingsConfigurator();
        $callback = static fn(array $args) => null;

        $section = $configurator->section('general', 'General');
        $section->field('api_key', 'API Key', $callback);

        $fields = $section->getFields();

        self::assertCount(1, $fields);
        self::assertInstanceOf(FieldDefinition::class, $fields[0]);
        self::assertSame('api_key', $fields[0]->id);
        self::assertSame('API Key', $fields[0]->title);
        self::assertSame($callback, $fields[0]->renderCallback);
    }

    #[Test]
    public function fieldWithArgs(): void
    {
        $configurator = new SettingsConfigurator();
        $callback = static fn(array $args) => null;
        $args = ['label_for' => 'api_key', 'class' => 'regular-text'];

        $section = $configurator->section('general', 'General');
        $section->field('api_key', 'API Key', $callback, $args);

        $fields = $section->getFields();

        self::assertSame($args, $fields[0]->args);
    }

    #[Test]
    public function fieldArgsDefaultToEmptyArray(): void
    {
        $configurator = new SettingsConfigurator();
        $callback = static fn(array $args) => null;

        $section = $configurator->section('general', 'General');
        $section->field('api_key', 'API Key', $callback);

        self::assertSame([], $section->getFields()[0]->args);
    }

    #[Test]
    public function fieldReturnsSectionForChaining(): void
    {
        $configurator = new SettingsConfigurator();
        $callback = static fn(array $args) => null;

        $section = $configurator->section('general', 'General');
        $result = $section->field('api_key', 'API Key', $callback);

        self::assertSame($section, $result);
    }

    #[Test]
    public function fluentApiChaining(): void
    {
        $configurator = new SettingsConfigurator();
        $callback = static fn(array $args) => null;

        $configurator->section('general', 'General')
            ->field('api_key', 'API Key', $callback)
            ->field('debug', 'Debug Mode', $callback);

        $configurator->section('advanced', 'Advanced')
            ->field('cache_ttl', 'Cache TTL', $callback);

        $sections = $configurator->getSections();

        self::assertCount(2, $sections);
        self::assertCount(2, $sections[0]->getFields());
        self::assertCount(1, $sections[1]->getFields());

        self::assertSame('api_key', $sections[0]->getFields()[0]->id);
        self::assertSame('debug', $sections[0]->getFields()[1]->id);
        self::assertSame('cache_ttl', $sections[1]->getFields()[0]->id);
    }

    #[Test]
    public function getFieldsReturnsEmptyArrayByDefault(): void
    {
        $configurator = new SettingsConfigurator();
        $section = $configurator->section('general', 'General');

        self::assertSame([], $section->getFields());
    }
}
