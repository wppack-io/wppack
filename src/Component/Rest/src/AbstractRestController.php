<?php

declare(strict_types=1);

namespace WpPack\Component\Rest;

use WpPack\Component\HttpFoundation\JsonResponse;
use WpPack\Component\HttpFoundation\Response;
use WpPack\Component\Security\Security;

abstract class AbstractRestController
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
     * @param array<string, string> $headers
     */
    protected function json(mixed $data, int $statusCode = 200, array $headers = []): JsonResponse
    {
        return new JsonResponse($data, $statusCode, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    protected function created(mixed $data = null, array $headers = []): JsonResponse
    {
        return new JsonResponse($data, 201, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    protected function noContent(array $headers = []): Response
    {
        return new Response('', 204, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    protected function response(mixed $data = null, int $statusCode = 200, array $headers = []): Response
    {
        $content = $data !== null ? (is_string($data) ? $data : (string) json_encode($data)) : '';

        return new Response($content, $statusCode, $headers);
    }
}
