<?php

declare(strict_types=1);

namespace WpPack\Component\Templating\Bridge\Twig\Tests\Extension;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\FilesystemLoader;
use WpPack\Component\Escaper\Escaper;
use WpPack\Component\Sanitizer\Sanitizer;
use WpPack\Component\Templating\Bridge\Twig\Extension\WordPressExtension;

final class WordPressExtensionTest extends TestCase
{
    private Environment $twig;

    protected function setUp(): void
    {
        $this->twig = new Environment(new ArrayLoader(), [
            'strict_variables' => true,
            'autoescape' => false,
        ]);
        $this->twig->addExtension(new WordPressExtension());
    }

    #[Test]
    public function providesEscapeFilters(): void
    {
        $extension = new WordPressExtension();
        $filters = $extension->getFilters();

        $filterNames = array_map(fn($f) => $f->getName(), $filters);

        self::assertContains('esc_html', $filterNames);
        self::assertContains('esc_attr', $filterNames);
        self::assertContains('esc_url', $filterNames);
        self::assertContains('esc_js', $filterNames);
        self::assertContains('esc_textarea', $filterNames);
        self::assertContains('wp_kses_post', $filterNames);
    }

    #[Test]
    public function providesWordPressFunctions(): void
    {
        $extension = new WordPressExtension();
        $functions = $extension->getFunctions();

        $functionNames = array_map(fn($f) => $f->getName(), $functions);

        self::assertContains('wp_head', $functionNames);
        self::assertContains('wp_footer', $functionNames);
        self::assertContains('body_class', $functionNames);
        self::assertContains('language_attributes', $functionNames);
    }

    #[Test]
    public function escHtmlFilterEscapesHtml(): void
    {
        $result = $this->renderFilter('esc_html', '<script>alert("xss")</script>');

        self::assertStringNotContainsString('<script>', $result);
        self::assertStringContainsString('&lt;script&gt;', $result);
    }

    #[Test]
    public function escAttrFilterEscapesAttributes(): void
    {
        $result = $this->renderFilter('esc_attr', '" onclick="alert(1)');

        self::assertStringNotContainsString('"', $result);
    }

    #[Test]
    public function escTextareaFilterEscapesTextarea(): void
    {
        $result = $this->renderFilter('esc_textarea', '<script>alert("xss")</script>');

        self::assertStringNotContainsString('<script>', $result);
    }

    #[Test]
    public function wpKsesPostFilterSanitizesHtml(): void
    {
        $result = $this->renderFilter('wp_kses_post', '<p>Hello</p><script>alert("xss")</script>');

        self::assertStringContainsString('<p>Hello</p>', $result);
        self::assertStringNotContainsString('<script>', $result);
    }

    #[Test]
    public function acceptsCustomEscaperAndSanitizer(): void
    {
        $extension = new WordPressExtension(new Escaper(), new Sanitizer());
        $filters = $extension->getFilters();

        $filterNames = array_map(fn($f) => $f->getName(), $filters);

        self::assertContains('esc_html', $filterNames);
        self::assertContains('wp_kses_post', $filterNames);
    }

    #[Test]
    public function integrationTest(): void
    {
        $loader = new FilesystemLoader(__DIR__ . '/../Fixtures/templates');
        $twig = new Environment($loader, [
            'strict_variables' => true,
            'autoescape' => 'html',
        ]);
        $twig->addExtension(new WordPressExtension());

        $html = $twig->render('with-escaping.html.twig', [
            'content' => '<script>xss</script>',
        ]);

        self::assertStringNotContainsString('<script>', $html);
    }

    private function renderFilter(string $filter, string $value): string
    {
        $template = $this->twig->createTemplate("{{ value|$filter }}");

        return $template->render(['value' => $value]);
    }
}
