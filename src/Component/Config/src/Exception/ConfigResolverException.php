<?php

declare(strict_types=1);

namespace WpPack\Component\Config\Exception;

final class ConfigResolverException extends \RuntimeException
{
    public static function missingValue(string $className, string $parameterName, string $source): self
    {
        return new self(sprintf(
            'Unable to resolve parameter "$%s" for config class "%s": %s is not set and no default value is provided.',
            $parameterName,
            $className,
            $source,
        ));
    }
}
