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
