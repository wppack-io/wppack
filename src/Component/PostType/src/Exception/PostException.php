<?php

declare(strict_types=1);

namespace WpPack\Component\PostType\Exception;

class PostException extends \RuntimeException implements ExceptionInterface
{
    /**
     * @param list<string> $wpErrorCodes
     * @param list<string> $wpErrorMessages
     */
    public function __construct(
        string $message = '',
        private readonly array $wpErrorCodes = [],
        private readonly array $wpErrorMessages = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function fromWpError(\WP_Error $error): self
    {
        return new self(
            message: $error->get_error_message(),
            wpErrorCodes: $error->get_error_codes(),
            wpErrorMessages: $error->get_error_messages(),
        );
    }

    /**
     * @return list<string>
     */
    public function getWpErrorCodes(): array
    {
        return $this->wpErrorCodes;
    }

    /**
     * @return list<string>
     */
    public function getWpErrorMessages(): array
    {
        return $this->wpErrorMessages;
    }
}
