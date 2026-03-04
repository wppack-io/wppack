<?php

declare(strict_types=1);

namespace WpPack\Component\Setting\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Setting\AbstractSettingsPage;
use WpPack\Component\Setting\Attribute\AsSettingsPage;
use WpPack\Component\Setting\SettingsConfigurator;
use WpPack\Component\Setting\ValidationContext;

final class AbstractSettingsPageTest extends TestCase
{
    #[Test]
    public function resolvesSlugFromAttribute(): void
    {
        $page = new ConcreteTestSettingsPage();

        self::assertSame('my-plugin', $page->slug);
    }

    #[Test]
    public function resolvesTitleFromAttribute(): void
    {
        $page = new ConcreteTestSettingsPage();

        self::assertSame('My Plugin Settings', $page->title);
    }

    #[Test]
    public function resolvesMenuTitleFromAttribute(): void
    {
        $page = new ConcreteTestSettingsPage();

        self::assertSame('My Plugin', $page->menuTitle);
    }

    #[Test]
    public function menuTitleDefaultsToTitle(): void
    {
        $page = new MinimalTestSettingsPage();

        self::assertSame('Minimal Settings', $page->menuTitle);
    }

    #[Test]
    public function resolvesCapabilityFromAttribute(): void
    {
        $page = new ConcreteTestSettingsPage();

        self::assertSame('manage_options', $page->capability);
    }

    #[Test]
    public function resolvesOptionNameFromAttribute(): void
    {
        $page = new ConcreteTestSettingsPage();

        self::assertSame('my_plugin_options', $page->optionName);
    }

    #[Test]
    public function optionNameDefaultsToSlugConverted(): void
    {
        $page = new MinimalTestSettingsPage();

        self::assertSame('minimal_settings', $page->optionName);
    }

    #[Test]
    public function resolvesOptionGroupFromAttribute(): void
    {
        $page = new ConcreteTestSettingsPage();

        self::assertSame('my_plugin_group', $page->optionGroup);
    }

    #[Test]
    public function optionGroupDefaultsToOptionName(): void
    {
        $page = new MinimalTestSettingsPage();

        self::assertSame('minimal_settings', $page->optionGroup);
    }

    #[Test]
    public function resolvesParentFromAttribute(): void
    {
        $page = new ConcreteTestSettingsPage();

        self::assertSame('options-general.php', $page->parent);
    }

    #[Test]
    public function parentCanBeNull(): void
    {
        $page = new TopLevelTestSettingsPage();

        self::assertNull($page->parent);
    }

    #[Test]
    public function resolvesIconFromAttribute(): void
    {
        $page = new TopLevelTestSettingsPage();

        self::assertSame('dashicons-admin-generic', $page->icon);
    }

    #[Test]
    public function resolvesPositionFromAttribute(): void
    {
        $page = new TopLevelTestSettingsPage();

        self::assertSame(80, $page->position);
    }

    #[Test]
    public function throwsLogicExceptionWithoutAttribute(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must have the #[AsSettingsPage] attribute');

        new NoAttributeTestSettingsPage();
    }

    #[Test]
    public function hasValidateOverrideReturnsFalseByDefault(): void
    {
        $page = new MinimalTestSettingsPage();

        self::assertFalse($page->hasValidateOverride());
    }

    #[Test]
    public function hasValidateOverrideReturnsTrueWhenOverridden(): void
    {
        $page = new ValidateTestSettingsPage();

        self::assertTrue($page->hasValidateOverride());
    }

    #[Test]
    public function hasSanitizeOverrideReturnsFalseByDefault(): void
    {
        $page = new MinimalTestSettingsPage();

        self::assertFalse($page->hasSanitizeOverride());
    }

    #[Test]
    public function hasSanitizeOverrideReturnsTrueWhenOverridden(): void
    {
        $page = new SanitizeTestSettingsPage();

        self::assertTrue($page->hasSanitizeOverride());
    }

    #[Test]
    public function configureReceivesSettingsConfigurator(): void
    {
        $page = new ConcreteTestSettingsPage();

        // Access configure via reflection to verify it works
        $method = new \ReflectionMethod($page, 'configure');
        $params = $method->getParameters();

        self::assertCount(1, $params);
        self::assertSame(SettingsConfigurator::class, $params[0]->getType()->getName());
    }

    #[Test]
    public function resolvesAllAttributeParameters(): void
    {
        $page = new FullAttributeTestSettingsPage();

        self::assertSame('full-plugin', $page->slug);
        self::assertSame('Full Plugin Settings', $page->title);
        self::assertSame('Full Plugin', $page->menuTitle);
        self::assertSame('edit_posts', $page->capability);
        self::assertSame('full_plugin_opts', $page->optionName);
        self::assertSame('full_plugin_grp', $page->optionGroup);
        self::assertSame('tools.php', $page->parent);
        self::assertNull($page->icon);
        self::assertNull($page->position);
    }
}

#[AsSettingsPage(
    slug: 'my-plugin',
    title: 'My Plugin Settings',
    menuTitle: 'My Plugin',
    optionName: 'my_plugin_options',
    optionGroup: 'my_plugin_group',
)]
class ConcreteTestSettingsPage extends AbstractSettingsPage
{
    protected function configure(SettingsConfigurator $settings): void
    {
        $settings->section('general', 'General')
            ->field('api_key', 'API Key', fn(array $args) => null);
    }
}

#[AsSettingsPage(slug: 'minimal-settings', title: 'Minimal Settings')]
class MinimalTestSettingsPage extends AbstractSettingsPage
{
    protected function configure(SettingsConfigurator $settings): void {}
}

#[AsSettingsPage(
    slug: 'top-level',
    title: 'Top Level Settings',
    parent: null,
    icon: 'dashicons-admin-generic',
    position: 80,
)]
class TopLevelTestSettingsPage extends AbstractSettingsPage
{
    protected function configure(SettingsConfigurator $settings): void {}
}

class NoAttributeTestSettingsPage extends AbstractSettingsPage
{
    protected function configure(SettingsConfigurator $settings): void {}
}

#[AsSettingsPage(slug: 'validate-test', title: 'Validate Test')]
class ValidateTestSettingsPage extends AbstractSettingsPage
{
    protected function configure(SettingsConfigurator $settings): void {}

    protected function validate(array $input, ValidationContext $context): array
    {
        if ($input['api_key'] === '') {
            $context->error('api_key_required', 'API Key is required.');
        }

        return $input;
    }
}

#[AsSettingsPage(slug: 'sanitize-test', title: 'Sanitize Test')]
class SanitizeTestSettingsPage extends AbstractSettingsPage
{
    protected function configure(SettingsConfigurator $settings): void {}

    protected function sanitize(array $input): array
    {
        return array_map('trim', $input);
    }
}

#[AsSettingsPage(
    slug: 'full-plugin',
    title: 'Full Plugin Settings',
    menuTitle: 'Full Plugin',
    capability: 'edit_posts',
    optionName: 'full_plugin_opts',
    optionGroup: 'full_plugin_grp',
    parent: 'tools.php',
)]
class FullAttributeTestSettingsPage extends AbstractSettingsPage
{
    protected function configure(SettingsConfigurator $settings): void {}
}
