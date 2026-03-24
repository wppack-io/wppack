<?php

declare(strict_types=1);

namespace WpPack\Component\Admin\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Admin\AbstractAdminPage;
use WpPack\Component\Admin\Attribute\AsAdminPage;
use WpPack\Component\Role\Attribute\IsGranted;
use WpPack\Component\Templating\TemplateRendererInterface;

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
    public function hasEnqueueOverrideReturnsFalseByDefault(): void
    {
        $page = new MinimalTestAdminPage();

        self::assertFalse($page->hasEnqueueOverride());
    }

    #[Test]
    public function hasEnqueueOverrideReturnsTrueWhenOverridden(): void
    {
        $page = new EnqueueTestAdminPage();

        self::assertTrue($page->hasEnqueueOverride());
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
    public function invokeReturnsString(): void
    {
        $page = new ConcreteTestAdminPage();

        self::assertSame('<div>admin page content</div>', $page());
    }

    #[Test]
    public function handleRenderEchoesInvokeOutput(): void
    {
        $page = new ConcreteTestAdminPage();

        ob_start();
        $page->handleRender();
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
        $page = new EnqueueTestAdminPage();
        $page->addMenuPage();

        // Get hookSuffix via reflection
        $ref = new \ReflectionProperty(AbstractAdminPage::class, 'hookSuffix');
        $hookSuffix = $ref->getValue($page);

        self::assertNotNull($hookSuffix);

        // Should not throw or error when called with matching hookSuffix
        $page->handleEnqueue($hookSuffix);
        self::assertTrue($page->enqueued);
    }

    #[Test]
    public function handleEnqueueSkipsWhenHookSuffixDiffers(): void
    {
        $page = new EnqueueTestAdminPage();
        $page->addMenuPage();

        $page->handleEnqueue('some_other_page');
        self::assertFalse($page->enqueued);
    }

    #[Test]
    public function handleEnqueueBeforeAddMenuPageSkips(): void
    {
        $page = new EnqueueTestAdminPage();

        // hookSuffix is null before addMenuPage is called
        $page->handleEnqueue('any-page');

        self::assertFalse($page->enqueued);
    }

    #[Test]
    public function renderDelegatesToTemplateRenderer(): void
    {
        $renderer = $this->createMock(TemplateRendererInterface::class);
        $renderer->expects(self::once())
            ->method('render')
            ->with('admin/test.html.twig', ['key' => 'value'])
            ->willReturn('<div>rendered</div>');

        $page = new TemplatingTestAdminPage();
        $page->setTemplateRenderer($renderer);

        self::assertSame('<div>rendered</div>', $page());
    }

    #[Test]
    public function renderThrowsLogicExceptionWithoutRenderer(): void
    {
        $page = new TemplatingTestAdminPage();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('TemplateRendererInterface is not available');

        $page();
    }

    #[Test]
    public function setTemplateRendererSetsRenderer(): void
    {
        $renderer = $this->createMock(TemplateRendererInterface::class);
        $renderer->method('render')->willReturn('<p>ok</p>');

        $page = new TemplatingTestAdminPage();
        $page->setTemplateRenderer($renderer);

        self::assertSame('<p>ok</p>', $page());
    }

    #[Test]
    public function handleRenderWithoutResolverWorksForNoArgInvoke(): void
    {
        $page = new ConcreteTestAdminPage();

        ob_start();
        $page->handleRender();
        $output = ob_get_clean();

        self::assertSame('<div>admin page content</div>', $output);
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
    public function __invoke(): string
    {
        return '<div>admin page content</div>';
    }
}

#[AsAdminPage(slug: 'minimal-page', label: 'Minimal Page')]
class MinimalTestAdminPage extends AbstractAdminPage
{
    public function __invoke(): string
    {
        return '';
    }
}

#[AsAdminPage(
    slug: 'submenu-page',
    label: 'Submenu Page',
    parent: 'options-general.php',
)]
class SubmenuTestAdminPage extends AbstractAdminPage
{
    public function __invoke(): string
    {
        return '<div>submenu content</div>';
    }
}

class NoAttributeTestAdminPage extends AbstractAdminPage
{
    public function __invoke(): string
    {
        return '';
    }
}

#[AsAdminPage(slug: 'enqueue-test-page', label: 'Enqueue Test Page')]
class EnqueueTestAdminPage extends AbstractAdminPage
{
    public bool $enqueued = false;

    public function __invoke(): string
    {
        return '';
    }

    protected function enqueue(): void
    {
        $this->enqueued = true;
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
    public function __invoke(): string
    {
        return '';
    }
}

#[AsAdminPage(slug: 'templating-test-page', label: 'Templating Test Page')]
class TemplatingTestAdminPage extends AbstractAdminPage
{
    public function __invoke(): string
    {
        return $this->render('admin/test.html.twig', ['key' => 'value']);
    }
}
