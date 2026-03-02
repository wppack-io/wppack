<?php

declare(strict_types=1);

namespace WpPack\Component\Cache\Exception;

use WpPack\Component\Cache\Adapter\Dsn;

final class UnsupportedSchemeException extends \LogicException implements ExceptionInterface
{
    /** @param list<string> $supported */
    public function __construct(Dsn $dsn, ?string $name = null, array $supported = [])
    {
        $message = sprintf('The "%s" scheme is not supported.', $dsn->getScheme());
        if ($name !== null && $supported !== []) {
            $message .= sprintf(' Supported schemes for "%s": %s.', $name, implode(', ', $supported));
        }
        parent::__construct($message);
    }
}
