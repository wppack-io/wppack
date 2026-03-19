<?php

declare(strict_types=1);

namespace WpPack\Component\Ajax;

use WpPack\Component\HttpFoundation\JsonResponse;
use WpPack\Component\Security\SecurityAwareTrait;

abstract class AbstractAjaxController
{
    use SecurityAwareTrait;

    /**
     * @param array<string, string> $headers
     */
    protected function json(mixed $data, int $statusCode = 200, array $headers = []): JsonResponse
    {
        return new JsonResponse($data, $statusCode, $headers);
    }
}
