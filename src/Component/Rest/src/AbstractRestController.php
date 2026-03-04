<?php

declare(strict_types=1);

namespace WpPack\Component\Rest;

use WpPack\Component\Rest\Response\JsonResponse;
use WpPack\Component\Rest\Response\Response;

abstract class AbstractRestController
{
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
        return new Response(null, 204, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    protected function response(mixed $data = null, int $statusCode = 200, array $headers = []): Response
    {
        return new Response($data, $statusCode, $headers);
    }
}
