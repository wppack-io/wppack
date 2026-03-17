<?php

declare(strict_types=1);

namespace WpPack\Component\Storage\Exception;

final class UnsupportedOperationException extends \LogicException implements ExceptionInterface
{
    public function __construct(string $operation, string $adapterName)
    {
        parent::__construct(sprintf('The "%s" operation is not supported by the "%s" adapter.', $operation, $adapterName));
    }
}
