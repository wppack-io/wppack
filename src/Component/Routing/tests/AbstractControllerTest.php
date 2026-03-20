<?php

declare(strict_types=1);

namespace WpPack\Component\Routing\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpFoundation\BinaryFileResponse;
use WpPack\Component\HttpFoundation\JsonResponse;
use WpPack\Component\HttpFoundation\RedirectResponse;
use WpPack\Component\HttpFoundation\Response;
use WpPack\Component\Routing\AbstractController;
use WpPack\Component\Routing\Response\BlockTemplateResponse;
use WpPack\Component\Routing\Response\TemplateResponse;
use WpPack\Component\Role\Exception\AccessDeniedException;
use WpPack\Component\Security\Tests\SecurityTestTrait;
use WpPack\Component\Templating\TemplateRendererInterface;

final class AbstractControllerTest extends TestCase
{
    use SecurityTestTrait;

    private AbstractController $controller;

    protected function setUp(): void
    {
        $this->controller = new class extends AbstractController {
            public function callRender(
                string $view,
                array $parameters = [],
                int $statusCode = 200,
                array $headers = [],
            ): Response {
                return $this->render($view, $parameters, $statusCode, $headers);
            }

            public function callRenderView(
                string $view,
                array $parameters = [],
            ): string {
                return $this->renderView($view, $parameters);
            }

            public function callRenderTemplate(
                string $template,
                array $context = [],
                int $statusCode = 200,
                array $headers = [],
            ): TemplateResponse {
                return $this->renderTemplate($template, $context, $statusCode, $headers);
            }

            public function callRenderBlockTemplate(
                string $slug,
                array $context = [],
                int $statusCode = 200,
                array $headers = [],
            ): BlockTemplateResponse {
                return $this->renderBlockTemplate($slug, $context, $statusCode, $headers);
            }

            public function callJson(
                mixed $data,
                int $statusCode = 200,
                array $headers = [],
            ): JsonResponse {
                return $this->json($data, $statusCode, $headers);
            }

            public function callRedirect(
                string $url,
                int $statusCode = 302,
                bool $safe = true,
                array $headers = [],
            ): RedirectResponse {
                return $this->redirect($url, $statusCode, $safe, $headers);
            }

            public function callFile(
                string $path,
                ?string $filename = null,
                string $disposition = 'attachment',
                array $headers = [],
            ): BinaryFileResponse {
                return $this->file($path, $filename, $disposition, $headers);
            }

            public function callGetUser(): ?\WP_User
            {
                return $this->getUser();
            }

            public function callIsGranted(string $attribute, mixed $subject = null): bool
            {
                return $this->isGranted($attribute, $subject);
            }

            public function callDenyAccessUnlessGranted(string $attribute, mixed $subject = null, string $message = 'Access Denied.'): void
            {
                $this->denyAccessUnlessGranted($attribute, $subject, $message);
            }
        };
    }

    #[Test]
    public function renderReturnsResponseWithRenderedContent(): void
    {
        $renderer = $this->createMock(TemplateRendererInterface::class);
        $renderer->method('render')
            ->with('templates/product.html.twig', ['name' => 'Widget'])
            ->willReturn('<h1>Widget</h1>');

        $this->controller->setTemplateRenderer($renderer);

        $response = $this->controller->callRender(
            'templates/product.html.twig',
            ['name' => 'Widget'],
            201,
            ['X-Custom' => 'header'],
        );

        self::assertInstanceOf(Response::class, $response);
        self::assertSame('<h1>Widget</h1>', $response->content);
        self::assertSame(201, $response->statusCode);
        self::assertSame(['X-Custom' => 'header'], $response->headers);
    }

    #[Test]
    public function renderViewReturnsRenderedString(): void
    {
        $renderer = $this->createMock(TemplateRendererInterface::class);
        $renderer->method('render')
            ->with('templates/hello.html.twig', ['name' => 'World'])
            ->willReturn('Hello World');

        $this->controller->setTemplateRenderer($renderer);

        $html = $this->controller->callRenderView('templates/hello.html.twig', ['name' => 'World']);

        self::assertSame('Hello World', $html);
    }

    #[Test]
    public function renderThrowsWithoutRenderer(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('TemplateRendererInterface is not available');

        $this->controller->callRender('templates/test.html.twig');
    }

    #[Test]
    public function renderViewThrowsWithoutRenderer(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('TemplateRendererInterface is not available');

        $this->controller->callRenderView('templates/test.html.twig');
    }

    #[Test]
    public function renderTemplateReturnsTemplateResponse(): void
    {
        $response = $this->controller->callRenderTemplate(
            '/path/to/template.php',
            ['key' => 'value'],
            201,
            ['X-Custom' => 'header'],
        );

        self::assertInstanceOf(TemplateResponse::class, $response);
        self::assertSame('/path/to/template.php', $response->template);
        self::assertSame(['key' => 'value'], $response->context);
        self::assertSame(201, $response->statusCode);
        self::assertSame(['X-Custom' => 'header'], $response->headers);
    }

    #[Test]
    public function renderBlockTemplateReturnsBlockTemplateResponse(): void
    {
        $response = $this->controller->callRenderBlockTemplate(
            'single-portfolio',
            ['slug' => 'my-project'],
            201,
            ['X-Block' => 'yes'],
        );

        self::assertInstanceOf(BlockTemplateResponse::class, $response);
        self::assertSame('single-portfolio', $response->slug);
        self::assertSame(['slug' => 'my-project'], $response->context);
        self::assertSame(201, $response->statusCode);
        self::assertSame(['X-Block' => 'yes'], $response->headers);
    }

    #[Test]
    public function jsonReturnsJsonResponse(): void
    {
        $data = ['products' => [['id' => 1]]];
        $response = $this->controller->callJson($data, 201, ['X-Total' => '1']);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame($data, $response->data);
        self::assertSame(201, $response->statusCode);
        self::assertArrayHasKey('X-Total', $response->headers);
    }

    #[Test]
    public function redirectReturnsRedirectResponse(): void
    {
        $response = $this->controller->callRedirect(
            'https://example.com',
            301,
            false,
            ['X-Redirect' => 'yes'],
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('https://example.com', $response->url);
        self::assertSame(301, $response->statusCode);
        self::assertFalse($response->safe);
        self::assertArrayHasKey('X-Redirect', $response->headers);
    }

    #[Test]
    public function fileReturnsBinaryFileResponse(): void
    {
        $response = $this->controller->callFile(
            '/path/to/file.pdf',
            'report.pdf',
            'inline',
            ['Cache-Control' => 'no-cache'],
        );

        self::assertInstanceOf(BinaryFileResponse::class, $response);
        self::assertSame('/path/to/file.pdf', $response->path);
        self::assertSame('report.pdf', $response->filename);
        self::assertSame('inline', $response->disposition);
        self::assertSame(['Cache-Control' => 'no-cache'], $response->headers);
    }

    #[Test]
    public function getUserDelegatesToSecurity(): void
    {
        $user = new \WP_User();
        $user->ID = 42;

        $security = $this->createSecurity(user: $user);
        $this->controller->setSecurity($security);

        self::assertSame($user, $this->controller->callGetUser());
    }

    #[Test]
    public function getUserThrowsWithoutSecurity(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Security is not available');

        $this->controller->callGetUser();
    }

    #[Test]
    public function isGrantedDelegatesToSecurity(): void
    {
        $security = $this->createSecurity(granted: true);
        $this->controller->setSecurity($security);

        self::assertTrue($this->controller->callIsGranted('edit_posts'));
    }

    #[Test]
    public function denyAccessUnlessGrantedDelegatesToSecurity(): void
    {
        $security = $this->createSecurity(granted: false);
        $this->controller->setSecurity($security);

        $this->expectException(AccessDeniedException::class);

        $this->controller->callDenyAccessUnlessGranted('edit_posts');
    }

    #[Test]
    public function isGrantedThrowsWithoutSecurity(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Security is not available');

        $this->controller->callIsGranted('edit_posts');
    }

    #[Test]
    public function denyAccessUnlessGrantedThrowsWithoutSecurity(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Security is not available');

        $this->controller->callDenyAccessUnlessGranted('edit_posts');
    }

    #[Test]
    public function renderWithDefaultParameters(): void
    {
        $renderer = $this->createMock(TemplateRendererInterface::class);
        $renderer->method('render')
            ->with('templates/default.html.twig', [])
            ->willReturn('<p>default</p>');

        $this->controller->setTemplateRenderer($renderer);

        $response = $this->controller->callRender('templates/default.html.twig');

        self::assertInstanceOf(Response::class, $response);
        self::assertSame('<p>default</p>', $response->content);
        self::assertSame(200, $response->statusCode);
        self::assertSame([], $response->headers);
    }

    #[Test]
    public function renderViewWithDefaultParameters(): void
    {
        $renderer = $this->createMock(TemplateRendererInterface::class);
        $renderer->method('render')
            ->with('templates/view.html.twig', [])
            ->willReturn('view content');

        $this->controller->setTemplateRenderer($renderer);

        $html = $this->controller->callRenderView('templates/view.html.twig');

        self::assertSame('view content', $html);
    }

    #[Test]
    public function renderTemplateWithDefaultParameters(): void
    {
        $response = $this->controller->callRenderTemplate('/path/to/template.php');

        self::assertInstanceOf(TemplateResponse::class, $response);
        self::assertSame('/path/to/template.php', $response->template);
        self::assertSame([], $response->context);
        self::assertSame(200, $response->statusCode);
        self::assertSame([], $response->headers);
    }

    #[Test]
    public function renderBlockTemplateWithDefaultParameters(): void
    {
        $response = $this->controller->callRenderBlockTemplate('single');

        self::assertInstanceOf(BlockTemplateResponse::class, $response);
        self::assertSame('single', $response->slug);
        self::assertSame([], $response->context);
        self::assertSame(200, $response->statusCode);
        self::assertSame([], $response->headers);
    }

    #[Test]
    public function jsonWithDefaultParameters(): void
    {
        $response = $this->controller->callJson(['key' => 'value']);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(['key' => 'value'], $response->data);
        self::assertSame(200, $response->statusCode);
        self::assertArrayHasKey('Content-Type', $response->headers);
    }

    #[Test]
    public function redirectWithDefaultParameters(): void
    {
        $response = $this->controller->callRedirect('/new-location');

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/new-location', $response->url);
        self::assertSame(302, $response->statusCode);
        self::assertTrue($response->safe);
        self::assertArrayHasKey('Location', $response->headers);
    }

    #[Test]
    public function fileWithDefaultParameters(): void
    {
        $response = $this->controller->callFile('/path/to/download.zip');

        self::assertInstanceOf(BinaryFileResponse::class, $response);
        self::assertSame('/path/to/download.zip', $response->path);
        self::assertNull($response->filename);
        self::assertSame('attachment', $response->disposition);
        self::assertSame([], $response->headers);
    }

    #[Test]
    public function denyAccessUnlessGrantedPassesWhenGranted(): void
    {
        $security = $this->createSecurity(granted: true);
        $this->controller->setSecurity($security);

        $this->controller->callDenyAccessUnlessGranted('edit_posts');

        // No exception means success
        self::assertTrue(true);
    }

    #[Test]
    public function isGrantedReturnsFalseWhenNotGranted(): void
    {
        $security = $this->createSecurity(granted: false);
        $this->controller->setSecurity($security);

        self::assertFalse($this->controller->callIsGranted('manage_options'));
    }

    #[Test]
    public function getUserReturnsNullWhenNotAuthenticated(): void
    {
        $security = $this->createSecurity();
        $this->controller->setSecurity($security);

        self::assertNull($this->controller->callGetUser());
    }
}
