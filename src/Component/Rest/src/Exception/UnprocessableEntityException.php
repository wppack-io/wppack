<?php

declare(strict_types=1);

namespace WpPack\Component\Rest\Exception;

final class UnprocessableEntityException extends HttpException
{
    public function __construct(string $message = 'Unprocessable entity.', string $errorCode = 'rest_unprocessable_entity', ?\Throwable $previous = null)
    {
        parent::__construct($message, 422, $errorCode, $previous);
    }
}
