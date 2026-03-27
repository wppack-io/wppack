<?php

/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Component\Scim\Exception;

use WpPack\Component\Scim\Schema\ScimConstants;

class ScimException extends \RuntimeException implements ExceptionInterface
{
    public function __construct(
        string $message,
        private readonly int $httpStatus = 400,
        private readonly ?string $scimType = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function getScimType(): ?string
    {
        return $this->scimType;
    }

    /**
     * @return array<string, mixed>
     */
    public function toScimError(): array
    {
        return array_filter([
            'schemas' => [ScimConstants::ERROR_SCHEMA],
            'status' => (string) $this->httpStatus,
            'scimType' => $this->scimType,
            'detail' => $this->getMessage(),
        ]);
    }
}
