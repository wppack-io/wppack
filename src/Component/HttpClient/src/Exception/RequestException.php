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

namespace WPPack\Component\HttpClient\Exception;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;
use WPPack\Component\HttpClient\Response;

final class RequestException extends \RuntimeException implements ExceptionInterface, ClientExceptionInterface
{
    public function __construct(
        public readonly Response $response,
        private readonly ?RequestInterface $request = null,
    ) {
        parent::__construct(
            sprintf('HTTP request returned status code %d.', $response->getStatusCode()),
        );
    }

    public function getRequest(): ?RequestInterface
    {
        return $this->request;
    }
}
