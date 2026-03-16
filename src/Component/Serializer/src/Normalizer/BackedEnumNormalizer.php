<?php

declare(strict_types=1);

namespace WpPack\Component\Serializer\Normalizer;

use WpPack\Component\Serializer\Exception\NotNormalizableValueException;

final class BackedEnumNormalizer implements NormalizerInterface, DenormalizerInterface
{
    public function normalize(mixed $data, ?string $format = null, array $context = []): string|int
    {
        if (!$data instanceof \BackedEnum) {
            throw new NotNormalizableValueException(sprintf(
                'Expected a BackedEnum instance, got "%s".',
                get_debug_type($data),
            ));
        }

        return $data->value;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof \BackedEnum;
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        if (!is_subclass_of($type, \BackedEnum::class)) {
            throw new NotNormalizableValueException(sprintf(
                'The type "%s" is not a BackedEnum.',
                $type,
            ));
        }

        return $type::from($data);
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return is_subclass_of($type, \BackedEnum::class);
    }
}
