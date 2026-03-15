<?php

declare(strict_types=1);

namespace WpPack\Component\Templating\Exception;

final class TemplateNotFoundException extends \RuntimeException implements ExceptionInterface
{
    public function __construct(string $template, ?\Throwable $previous = null)
    {
        parent::__construct(
            sprintf('Template "%s" not found.', $template),
            0,
            $previous,
        );
    }
}
