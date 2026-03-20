<?php

declare(strict_types=1);

namespace WpPack\Component\Setting\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Role\Attribute\IsGranted;
use WpPack\Component\Setting\AbstractSettingsPage;
use WpPack\Component\Setting\Attribute\AsSettingsPage;
use WpPack\Component\Setting\SettingsConfigurator;
use WpPack\Component\Setting\SettingsRenderer;
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
    public function resolvesLabelFromAttribute(): void
    {
        $page = new ConcreteTestSettingsPage();

        self::assertSame('My Plugin Settings', $page->label);
    }

    #[Test]
    public function resolvesMenuLabelFromAttribute(): void
    {
        $page = new ConcreteTestSettingsPage();

        self::assertSame('My Plugin', $page->menuLabel);
    }

    #[Test]
    public function menuLabelDefaultsToLabel(): void
    {
        $page = new MinimalTestSettingsPage();

        self::assertSame('Minimal Settings', $page->menuLabel);
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
        self::assertSame('Full Plugin Settings', $page->label);
        self::assertSame('Full Plugin', $page->menuLabel);
        self::assertSame('edit_posts', $page->capability);
        self::assertSame('full_plugin_opts', $page->optionName);
        self::assertSame('full_plugin_grp', $page->optionGroup);
        self::assertSame('tools.php', $page->parent);
        self::assertNull($page->icon);
        self::assertNull($page->position);
    }

    #[Test]
    public function capabilityDefaultsToManageOptions(): void
    {
        $page = new MinimalTestSettingsPage();

        self::assertSame('manage_options', $page->capability);
    }

    #[Test]
    public function getRendererReturnsSettingsRendererByDefault(): void
    {
        $page = new MinimalTestSettingsPage();

        self::assertInstanceOf(SettingsRenderer::class, $page->getRenderer());
    }

    #[Test]
    public function getRendererReturnsSameInstance(): void
    {
        $page = new MinimalTestSettingsPage();

        $renderer1 = $page->getRenderer();
        $renderer2 = $page->getRenderer();

        self::assertSame($renderer1, $renderer2);
    }

    #[Test]
    public function createRendererCanBeOverridden(): void
    {
        $page = new RendererOverrideTestSettingsPage();

        self::assertInstanceOf(OverrideTestRenderer::class, $page->getRenderer());
    }

    #[Test]
    public function renderDelegatesToRenderer(): void
    {
        // Set $title global so get_admin_page_title() returns early
        // without calling get_plugin_page_hookname() with null $plugin_page
        global $title;
        $title = 'Test Page';

        $page = new MinimalTestSettingsPage();

        ob_start();
        $page->render();
        $output = ob_get_clean();

        self::assertStringContainsString('<div class="wrap">', $output);
        self::assertStringContainsString('<form', $output);
        self::assertStringContainsString('</div>', $output);
    }

    #[Test]
    public function getOptionReturnsStoredValue(): void
    {
        $page = new MinimalTestSettingsPage();

        update_option($page->optionName, ['api_key' => 'test-key']);

        self::assertSame('test-key', $page->getOption('api_key'));

        delete_option($page->optionName);
    }

    #[Test]
    public function getOptionReturnsDefaultWhenKeyMissing(): void
    {
        $page = new MinimalTestSettingsPage();

        update_option($page->optionName, ['other_key' => 'value']);

        self::assertSame('default-value', $page->getOption('nonexistent', 'default-value'));

        delete_option($page->optionName);
    }

    #[Test]
    public function getOptionReturnsDefaultWhenOptionNotArray(): void
    {
        $page = new MinimalTestSettingsPage();

        update_option($page->optionName, 'not-an-array');

        self::assertSame('fallback', $page->getOption('any_key', 'fallback'));

        delete_option($page->optionName);
    }

    #[Test]
    public function addMenuPageRegistersSubmenuPage(): void
    {
        wp_set_current_user(1);

        global $submenu;

        $page = new ConcreteTestSettingsPage();
        $page->addMenuPage();

        self::assertArrayHasKey($page->parent, $submenu);

        $found = false;
        foreach ($submenu[$page->parent] as $item) {
            if ($item[2] === $page->slug) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'Submenu page should be registered in $submenu global');
    }

    #[Test]
    public function addMenuPageRegistersTopLevelPage(): void
    {
        global $menu;

        $page = new TopLevelTestSettingsPage();
        $page->addMenuPage();

        $found = false;
        foreach ($menu as $item) {
            if ($item[2] === $page->slug) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'Top-level menu page should be registered in $menu global');
    }

    #[Test]
    public function initSettingsRegistersSetting(): void
    {
        $page = new ConcreteTestSettingsPage();
        $page->initSettings();

        $settings = get_registered_settings();
        self::assertArrayHasKey($page->optionName, $settings);
    }

    #[Test]
    public function initSettingsRegistersSection(): void
    {
        global $wp_settings_sections;

        $page = new ConcreteTestSettingsPage();
        $page->initSettings();

        self::assertArrayHasKey($page->slug, $wp_settings_sections);
        self::assertArrayHasKey('general', $wp_settings_sections[$page->slug]);
    }

    #[Test]
    public function initSettingsRegistersFields(): void
    {
        global $wp_settings_fields;

        $page = new ConcreteTestSettingsPage();
        $page->initSettings();

        self::assertArrayHasKey($page->slug, $wp_settings_fields);
        self::assertArrayHasKey('general', $wp_settings_fields[$page->slug]);
        self::assertArrayHasKey('api_key', $wp_settings_fields[$page->slug]['general']);
    }

    #[Test]
    public function initSettingsWithSanitizeCallback(): void
    {
        $page = new SanitizeTestSettingsPage();
        $page->initSettings();

        $settings = get_registered_settings();
        self::assertArrayHasKey($page->optionName, $settings);
        self::assertIsCallable($settings[$page->optionName]['sanitize_callback']);
    }

    #[Test]
    public function initSettingsWithSanitizeOverrideExecutesSanitizeCallback(): void
    {
        $page = new SanitizeTestSettingsPage();
        $page->initSettings();

        $settings = get_registered_settings();
        $callback = $settings[$page->optionName]['sanitize_callback'];

        $result = $callback(['name' => '  trimmed  ', 'value' => '  data  ']);

        self::assertSame('trimmed', $result['name']);
        self::assertSame('data', $result['value']);
    }

    #[Test]
    public function initSettingsWithValidateOverrideExecutesValidateCallback(): void
    {
        $page = new ValidateTestSettingsPage();
        $page->initSettings();

        $settings = get_registered_settings();
        $callback = $settings[$page->optionName]['sanitize_callback'];

        $result = $callback(['api_key' => '']);

        self::assertSame(['api_key' => ''], $result);

        $errors = get_settings_errors($page->optionGroup);
        $found = false;
        foreach ($errors as $error) {
            if ($error['code'] === 'api_key_required') {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'Validation error should be registered');
    }

    #[Test]
    public function initSettingsWithBothOverridesExecutesBoth(): void
    {
        $page = new SanitizeAndValidateTestSettingsPage();
        $page->initSettings();

        $settings = get_registered_settings();
        $callback = $settings[$page->optionName]['sanitize_callback'];

        $result = $callback(['api_key' => '  ', 'name' => '  test  ']);

        // sanitize trims first
        self::assertSame('', $result['api_key']);
        self::assertSame('test', $result['name']);

        // validate catches empty api_key
        $errors = get_settings_errors($page->optionGroup);
        $found = false;
        foreach ($errors as $error) {
            if ($error['code'] === 'api_key_empty') {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'Validation error should be registered after sanitize');
    }

    #[Test]
    public function initSettingsWithoutOverridesHasNoSanitizeCallback(): void
    {
        $page = new MinimalTestSettingsPage();
        $page->initSettings();

        $settings = get_registered_settings();
        self::assertArrayHasKey($page->optionName, $settings);
        self::assertEmpty($settings[$page->optionName]['sanitize_callback']);
    }
}

#[AsSettingsPage(
    slug: 'my-plugin',
    label: 'My Plugin Settings',
    menuLabel: 'My Plugin',
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

#[AsSettingsPage(slug: 'minimal-settings', label: 'Minimal Settings')]
class MinimalTestSettingsPage extends AbstractSettingsPage
{
    protected function configure(SettingsConfigurator $settings): void {}
}

#[AsSettingsPage(
    slug: 'top-level',
    label: 'Top Level Settings',
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

#[AsSettingsPage(slug: 'validate-test', label: 'Validate Test')]
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

#[AsSettingsPage(slug: 'sanitize-test', label: 'Sanitize Test')]
class SanitizeTestSettingsPage extends AbstractSettingsPage
{
    protected function configure(SettingsConfigurator $settings): void {}

    protected function sanitize(array $input): array
    {
        return array_map('trim', $input);
    }
}

#[IsGranted('edit_posts')]
#[AsSettingsPage(
    slug: 'full-plugin',
    label: 'Full Plugin Settings',
    menuLabel: 'Full Plugin',
    optionName: 'full_plugin_opts',
    optionGroup: 'full_plugin_grp',
    parent: 'tools.php',
)]
class FullAttributeTestSettingsPage extends AbstractSettingsPage
{
    protected function configure(SettingsConfigurator $settings): void {}
}

class OverrideTestRenderer extends SettingsRenderer {}

#[AsSettingsPage(slug: 'renderer-override', label: 'Renderer Override')]
class RendererOverrideTestSettingsPage extends AbstractSettingsPage
{
    protected function configure(SettingsConfigurator $settings): void {}

    protected function createRenderer(): SettingsRenderer
    {
        return new OverrideTestRenderer();
    }
}

#[AsSettingsPage(slug: 'sanitize-validate-test', label: 'Sanitize & Validate Test')]
class SanitizeAndValidateTestSettingsPage extends AbstractSettingsPage
{
    protected function configure(SettingsConfigurator $settings): void {}

    protected function sanitize(array $input): array
    {
        return array_map('trim', $input);
    }

    protected function validate(array $input, ValidationContext $context): array
    {
        if (($input['api_key'] ?? '') === '') {
            $context->error('api_key_empty', 'API Key must not be empty.');
        }

        return $input;
    }
}
