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

namespace WPPack\Component\Routing;

use WPPack\Component\HttpFoundation\BinaryFileResponse;
use WPPack\Component\HttpFoundation\JsonResponse;
use WPPack\Component\HttpFoundation\RedirectResponse;
use WPPack\Component\HttpFoundation\Response;
use WPPack\Component\Routing\Response\BlockTemplateResponse;
use WPPack\Component\Routing\Response\TemplateResponse;
use WPPack\Component\Security\SecurityAwareTrait;
use WPPack\Component\Templating\TemplateRendererInterface;

abstract class AbstractController
{
    use SecurityAwareTrait;

    private ?TemplateRendererInterface $renderer = null;

    /** @internal */
    public function setTemplateRenderer(TemplateRendererInterface $renderer): void
    {
        $this->renderer = $renderer;
    }

    /**
     * @param array<string, mixed> $parameters
     * @param array<string, string> $headers
     */
    protected function render(
        string $view,
        array $parameters = [],
        int $statusCode = 200,
        array $headers = [],
    ): Response {
        $content = $this->renderView($view, $parameters);

        return new Response($content, $statusCode, $headers);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function renderView(string $view, array $parameters = []): string
    {
        if ($this->renderer === null) {
            throw new \LogicException('A TemplateRendererInterface is not available. Pass a TemplateRendererInterface to RouteRegistry or call setTemplateRenderer() to use render().');
        }

        return $this->renderer->render($view, $parameters);
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, string> $headers
     */
    protected function renderTemplate(
        string $template,
        array $context = [],
        int $statusCode = 200,
        array $headers = [],
    ): TemplateResponse {
        return new TemplateResponse($template, $context, $statusCode, $headers);
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, string> $headers
     */
    protected function renderBlockTemplate(
        string $slug,
        array $context = [],
        int $statusCode = 200,
        array $headers = [],
    ): BlockTemplateResponse {
        return new BlockTemplateResponse($slug, $context, $statusCode, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    protected function json(
        mixed $data,
        int $statusCode = 200,
        array $headers = [],
    ): JsonResponse {
        return new JsonResponse($data, $statusCode, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    protected function redirect(
        string $url,
        int $statusCode = 302,
        bool $safe = true,
        array $headers = [],
    ): RedirectResponse {
        return new RedirectResponse($url, $statusCode, $safe, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    protected function file(
        string $path,
        ?string $filename = null,
        string $disposition = 'attachment',
        array $headers = [],
    ): BinaryFileResponse {
        return new BinaryFileResponse($path, $filename, $disposition, headers: $headers);
    }
}
