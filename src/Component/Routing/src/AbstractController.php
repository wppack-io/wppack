<?php

declare(strict_types=1);

namespace WpPack\Component\Routing;

use WpPack\Component\HttpFoundation\BinaryFileResponse;
use WpPack\Component\HttpFoundation\JsonResponse;
use WpPack\Component\HttpFoundation\RedirectResponse;
use WpPack\Component\Routing\Response\BlockTemplateResponse;
use WpPack\Component\Routing\Response\TemplateResponse;
use WpPack\Component\Security\Security;

abstract class AbstractController
{
    private ?Security $security = null;

    /** @internal */
    public function setSecurity(Security $security): void
    {
        $this->security = $security;
    }

    protected function getUser(): ?\WP_User
    {
        if ($this->security === null) {
            throw new \LogicException('Security is not available. Register SecurityServiceProvider to use getUser().');
        }

        return $this->security->getUser();
    }

    protected function isGranted(string $attribute, mixed $subject = null): bool
    {
        if ($this->security === null) {
            throw new \LogicException('Security is not available. Register SecurityServiceProvider to use isGranted().');
        }

        return $this->security->isGranted($attribute, $subject);
    }

    protected function denyAccessUnlessGranted(string $attribute, mixed $subject = null, string $message = 'Access Denied.'): void
    {
        if ($this->security === null) {
            throw new \LogicException('Security is not available. Register SecurityServiceProvider to use denyAccessUnlessGranted().');
        }

        $this->security->denyAccessUnlessGranted($attribute, $subject, $message);
    }

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
