<?php

declare(strict_types=1);

namespace WpPack\Component\Routing;

use WpPack\Component\Routing\Response\BinaryFileResponse;
use WpPack\Component\Routing\Response\BlockTemplateResponse;
use WpPack\Component\Routing\Response\JsonResponse;
use WpPack\Component\Routing\Response\RedirectResponse;
use WpPack\Component\Routing\Response\TemplateResponse;

abstract class AbstractController
{
    /**
     * @param array<string, mixed> $context
     * @param array<string, string> $headers
     */
    protected function render(
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
    protected function block(
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
