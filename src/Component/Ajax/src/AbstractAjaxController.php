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

namespace WPPack\Component\Ajax;

use WPPack\Component\HttpFoundation\JsonResponse;
use WPPack\Component\Security\SecurityAwareTrait;

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
