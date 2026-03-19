<?php

declare(strict_types=1);

namespace WpPack\Component\Rest;

use WpPack\Component\HttpFoundation\JsonResponse;
use WpPack\Component\HttpFoundation\Response;
use WpPack\Component\Security\SecurityAwareTrait;

abstract class AbstractRestController
{
    use SecurityAwareTrait;

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
