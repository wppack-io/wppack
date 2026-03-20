<?php

declare(strict_types=1);

namespace WpPack\Component\Admin\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Admin\AbstractAdminPage;
use WpPack\Component\Admin\Attribute\AsAdminPage;
use WpPack\Component\Role\Attribute\IsGranted;

final class AbstractAdminPageTest extends TestCase
{
    #[Test]
    public function resolvesSlugFromAttribute(): void
    {
        $page = new ConcreteTestAdminPage();

        self::assertSame('my-admin-page', $page->slug);
    }

    #[Test]
    public function resolvesLabelFromAttribute(): void
    {
        $page = new ConcreteTestAdminPage();

        self::assertSame('My Admin Page', $page->label);
    }

    #[Test]
    public function resolvesMenuLabelFromAttribute(): void
    {
        $page = new ConcreteTestAdminPage();

        self::assertSame('My Admin', $page->menuLabel);
    }

    #[Test]
    public function menuLabelDefaultsToLabel(): void
    {
        $page = new MinimalTestAdminPage();

        self::assertSame('Minimal Page', $page->menuLabel);
    }

    #[Test]
    public function resolvesCapabilityFromAttribute(): void
    {
        $page = new ConcreteTestAdminPage();

        self::assertSame('manage_options', $page->capability);
    }

    #[Test]
    public function resolvesParentFromAttribute(): void
    {
        $page = new SubmenuTestAdminPage();

        self::assertSame('options-general.php', $page->parent);
    }

    #[Test]
    public function parentDefaultsToNull(): void
    {
        $page = new MinimalTestAdminPage();

        self::assertNull($page->parent);
    }

    #[Test]
    public function resolvesIconFromAttribute(): void
    {
        $page = new ConcreteTestAdminPage();

        self::assertSame('dashicons-admin-generic', $page->icon);
    }

    #[Test]
    public function resolvesPositionFromAttribute(): void
    {
        $page = new ConcreteTestAdminPage();

        self::assertSame(25, $page->position);
    }

    #[Test]
    public function throwsLogicExceptionWithoutAttribute(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must have the #[AsAdminPage] attribute');

        new NoAttributeTestAdminPage();
    }

    #[Test]
    public function hasEnqueueScriptsOverrideReturnsFalseByDefault(): void
    {
        $page = new MinimalTestAdminPage();

        self::assertFalse($page->hasEnqueueScriptsOverride());
    }

    #[Test]
    public function hasEnqueueScriptsOverrideReturnsTrueWhenOverridden(): void
    {
        $page = new EnqueueScriptsTestAdminPage();

        self::assertTrue($page->hasEnqueueScriptsOverride());
    }

    #[Test]
    public function hasEnqueueStylesOverrideReturnsFalseByDefault(): void
    {
        $page = new MinimalTestAdminPage();

        self::assertFalse($page->hasEnqueueStylesOverride());
    }

    #[Test]
    public function hasEnqueueStylesOverrideReturnsTrueWhenOverridden(): void
    {
        $page = new EnqueueStylesTestAdminPage();

        self::assertTrue($page->hasEnqueueStylesOverride());
    }

    #[Test]
    public function resolvesAllAttributeParameters(): void
    {
        $page = new FullAttributeTestAdminPage();

        self::assertSame('full-admin', $page->slug);
        self::assertSame('Full Admin Page', $page->label);
        self::assertSame('Full Admin', $page->menuLabel);
        self::assertSame('edit_posts', $page->capability);
        self::assertSame('tools.php', $page->parent);
        self::assertNull($page->icon);
        self::assertNull($page->position);
    }

    #[Test]
    public function capabilityDefaultsToManageOptions(): void
    {
        $page = new MinimalTestAdminPage();

        self::assertSame('manage_options', $page->capability);
    }

    #[Test]
    public function renderIsCalled(): void
    {
        $page = new ConcreteTestAdminPage();

        ob_start();
        $page->render();
        $output = ob_get_clean();

        self::assertSame('<div>admin page content</div>', $output);
    }

    #[Test]
    public function addMenuPageRegistersTopLevelPage(): void
    {
        global $menu;

        $page = new ConcreteTestAdminPage();
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
    public function addMenuPageRegistersSubmenuPage(): void
    {
        wp_set_current_user(1);

        global $submenu;

        $page = new SubmenuTestAdminPage();
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
    public function handleEnqueueCallsOnlyOnMatchingHookSuffix(): void
    {
        $page = new EnqueueScriptsTestAdminPage();
        $page->addMenuPage();

        // Get hookSuffix via reflection
        $ref = new \ReflectionProperty(AbstractAdminPage::class, 'hookSuffix');
        $hookSuffix = $ref->getValue($page);

        self::assertNotNull($hookSuffix);

        // Should not throw or error when called with matching hookSuffix
        $page->handleEnqueue($hookSuffix);
        self::assertTrue($page->scriptsEnqueued);
    }

    #[Test]
    public function handleEnqueueSkipsWhenHookSuffixDiffers(): void
    {
        $page = new EnqueueScriptsTestAdminPage();
        $page->addMenuPage();

        $page->handleEnqueue('some_other_page');
        self::assertFalse($page->scriptsEnqueued);
    }

    #[Test]
    public function handleEnqueueCallsStylesOnMatchingHookSuffix(): void
    {
        $page = new EnqueueBothTestAdminPage();
        $page->addMenuPage();

        $ref = new \ReflectionProperty(AbstractAdminPage::class, 'hookSuffix');
        $hookSuffix = $ref->getValue($page);

        self::assertNotNull($hookSuffix);

        $page->handleEnqueue($hookSuffix);

        self::assertTrue($page->scriptsEnqueued);
        self::assertTrue($page->stylesEnqueued);
    }

    #[Test]
    public function handleEnqueueBeforeAddMenuPageSkips(): void
    {
        $page = new EnqueueScriptsTestAdminPage();

        // hookSuffix is null before addMenuPage is called
        $page->handleEnqueue('any-page');

        self::assertFalse($page->scriptsEnqueued);
    }
}

#[AsAdminPage(
    slug: 'my-admin-page',
    label: 'My Admin Page',
    menuLabel: 'My Admin',
    icon: 'dashicons-admin-generic',
    position: 25,
)]
class ConcreteTestAdminPage extends AbstractAdminPage
{
    public function render(): void
    {
        echo '<div>admin page content</div>';
    }
}

#[AsAdminPage(slug: 'minimal-page', label: 'Minimal Page')]
class MinimalTestAdminPage extends AbstractAdminPage
{
    public function render(): void
    {
        echo '';
    }
}

#[AsAdminPage(
    slug: 'submenu-page',
    label: 'Submenu Page',
    parent: 'options-general.php',
)]
class SubmenuTestAdminPage extends AbstractAdminPage
{
    public function render(): void
    {
        echo '<div>submenu content</div>';
    }
}

class NoAttributeTestAdminPage extends AbstractAdminPage
{
    public function render(): void
    {
        echo '';
    }
}

#[AsAdminPage(slug: 'enqueue-scripts-page', label: 'Enqueue Scripts Page')]
class EnqueueScriptsTestAdminPage extends AbstractAdminPage
{
    public bool $scriptsEnqueued = false;

    public function render(): void
    {
        echo '';
    }

    protected function enqueueScripts(string $hookSuffix): void
    {
        $this->scriptsEnqueued = true;
    }
}

#[AsAdminPage(slug: 'enqueue-styles-page', label: 'Enqueue Styles Page')]
class EnqueueStylesTestAdminPage extends AbstractAdminPage
{
    public function render(): void
    {
        echo '';
    }

    protected function enqueueStyles(string $hookSuffix): void
    {
        // styles enqueued
    }
}

#[IsGranted('edit_posts')]
#[AsAdminPage(
    slug: 'full-admin',
    label: 'Full Admin Page',
    menuLabel: 'Full Admin',
    parent: 'tools.php',
)]
class FullAttributeTestAdminPage extends AbstractAdminPage
{
    public function render(): void
    {
        echo '';
    }
}

#[AsAdminPage(slug: 'enqueue-both-page', label: 'Enqueue Both Page')]
class EnqueueBothTestAdminPage extends AbstractAdminPage
{
    public bool $scriptsEnqueued = false;
    public bool $stylesEnqueued = false;

    public function render(): void
    {
        echo '';
    }

    protected function enqueueScripts(string $hookSuffix): void
    {
        $this->scriptsEnqueued = true;
    }

    protected function enqueueStyles(string $hookSuffix): void
    {
        $this->stylesEnqueued = true;
    }
}
