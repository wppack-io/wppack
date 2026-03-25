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
