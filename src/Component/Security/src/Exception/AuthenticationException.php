<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Exception;

class AuthenticationException extends \RuntimeException implements ExceptionInterface
{
    /**
     * Generic message to prevent user enumeration.
     */
    private string $safeMessage = 'Authentication failed.';

    public function getSafeMessage(): string
    {
        return $this->safeMessage;
    }
}
