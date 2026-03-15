<?php

declare(strict_types=1);

namespace WpPack\Component\Templating\Bridge\Twig\Tests\Extension;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use WpPack\Component\Escaper\Escaper;
use WpPack\Component\Templating\Bridge\Twig\Extension\WordPressExtension;

final class WordPressExtensionTest extends TestCase
{
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
    public function escHtmlDelegatesToEscaper(): void
    {
        $escaper = new Escaper();
        $extension = new WordPressExtension($escaper);

        $result = $extension->escHtml('<script>alert("xss")</script>');

        self::assertStringNotContainsString('<script>', $result);
        self::assertStringContainsString('&lt;script&gt;', $result);
    }

    #[Test]
    public function escAttrDelegatesToEscaper(): void
    {
        $escaper = new Escaper();
        $extension = new WordPressExtension($escaper);

        $result = $extension->escAttr('" onclick="alert(1)');

        self::assertStringNotContainsString('"', $result);
    }

    #[Test]
    public function filtersWorkWithoutEscaper(): void
    {
        $extension = new WordPressExtension();

        $result = $extension->escHtml('<b>bold</b>');

        self::assertStringNotContainsString('<b>', $result);
        self::assertStringContainsString('&lt;b&gt;', $result);
    }

    #[Test]
    public function integrationTest(): void
    {
        $extension = new WordPressExtension(new Escaper());
        $loader = new FilesystemLoader(__DIR__ . '/../Fixtures/templates');
        $twig = new Environment($loader, [
            'strict_variables' => true,
            'autoescape' => 'html',
        ]);
        $twig->addExtension($extension);

        $html = $twig->render('with-escaping.html.twig', [
            'content' => '<script>xss</script>',
        ]);

        self::assertStringNotContainsString('<script>', $html);
    }
}
