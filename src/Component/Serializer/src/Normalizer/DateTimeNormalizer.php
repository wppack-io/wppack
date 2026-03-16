<?php

declare(strict_types=1);

namespace WpPack\Component\Serializer\Normalizer;

use WpPack\Component\Serializer\Exception\NotNormalizableValueException;

final class DateTimeNormalizer implements NormalizerInterface, DenormalizerInterface
{
    public const string FORMAT_KEY = 'datetime_format';

    private const string DEFAULT_FORMAT = \DateTimeInterface::ATOM;

    public function normalize(mixed $data, ?string $format = null, array $context = []): string
    {
        if (!$data instanceof \DateTimeInterface) {
            throw new NotNormalizableValueException(sprintf(
                'Expected a DateTimeInterface instance, got "%s".',
                get_debug_type($data),
            ));
        }

        $dateTimeFormat = $context[self::FORMAT_KEY] ?? self::DEFAULT_FORMAT;

        return $data->format($dateTimeFormat);
    }

    public function supportsNormalization(mixed $data, ?string $format = null): bool
    {
        return $data instanceof \DateTimeInterface;
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): \DateTimeInterface
    {
        $dateTimeFormat = $context[self::FORMAT_KEY] ?? self::DEFAULT_FORMAT;

        if ($type === \DateTimeImmutable::class || $type === \DateTimeInterface::class) {
            $dateTime = \DateTimeImmutable::createFromFormat($dateTimeFormat, $data);
            if ($dateTime === false) {
                $dateTime = new \DateTimeImmutable($data);
            }

            return $dateTime;
        }

        if ($type === \DateTime::class) {
            $dateTime = \DateTime::createFromFormat($dateTimeFormat, $data);
            if ($dateTime === false) {
                $dateTime = new \DateTime($data);
            }

            return $dateTime;
        }

        throw new NotNormalizableValueException(sprintf(
            'Unsupported datetime type "%s".',
            $type,
        ));
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null): bool
    {
        return \is_string($data) && ($type === \DateTimeInterface::class
            || $type === \DateTimeImmutable::class
            || $type === \DateTime::class);
    }
}
