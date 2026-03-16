<?php

declare(strict_types=1);

namespace WpPack\Component\Serializer;

use WpPack\Component\Serializer\Encoder\DecoderInterface;
use WpPack\Component\Serializer\Encoder\EncoderInterface;
use WpPack\Component\Serializer\Exception\InvalidArgumentException;
use WpPack\Component\Serializer\Exception\NotNormalizableValueException;
use WpPack\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use WpPack\Component\Serializer\Normalizer\DenormalizerInterface;
use WpPack\Component\Serializer\Normalizer\NormalizerAwareInterface;
use WpPack\Component\Serializer\Normalizer\NormalizerInterface;

final class Serializer implements SerializerInterface, NormalizerInterface, DenormalizerInterface
{
    /** @var list<NormalizerInterface> */
    private readonly array $normalizers;

    /** @var list<EncoderInterface|DecoderInterface> */
    private readonly array $encoders;

    /**
     * @param iterable<NormalizerInterface|DenormalizerInterface> $normalizers
     * @param iterable<EncoderInterface|DecoderInterface>         $encoders
     */
    public function __construct(
        iterable $normalizers = [],
        iterable $encoders = [],
    ) {
        $normalizerList = [];
        foreach ($normalizers as $normalizer) {
            if ($normalizer instanceof NormalizerAwareInterface) {
                $normalizer->setNormalizer($this);
            }
            if ($normalizer instanceof DenormalizerAwareInterface) {
                $normalizer->setDenormalizer($this);
            }
            $normalizerList[] = $normalizer;
        }
        $this->normalizers = $normalizerList;

        $encoderList = [];
        foreach ($encoders as $encoder) {
            $encoderList[] = $encoder;
        }
        $this->encoders = $encoderList;
    }

    public function serialize(mixed $data, string $format, array $context = []): string
    {
        $normalized = $this->normalize($data, $format, $context);

        return $this->encode($normalized, $format, $context);
    }

    public function deserialize(string $data, string $type, string $format, array $context = []): object
    {
        $decoded = $this->decode($data, $format, $context);

        return $this->denormalize($decoded, $type, $format, $context);
    }

    /**
     * @return array<mixed>|string|int|float|bool|null
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|null
    {
        if ($data === null || \is_scalar($data)) {
            return $data;
        }

        if (\is_array($data)) {
            $normalized = [];
            foreach ($data as $key => $value) {
                $normalized[$key] = $this->normalize($value, $format, $context);
            }

            return $normalized;
        }

        foreach ($this->normalizers as $normalizer) {
            if ($normalizer instanceof NormalizerInterface && $normalizer->supportsNormalization($data, $format)) {
                return $normalizer->normalize($data, $format, $context);
            }
        }

        throw new NotNormalizableValueException(sprintf(
            'Could not normalize object of type "%s", no supporting normalizer found.',
            get_debug_type($data),
        ));
    }

    public function supportsNormalization(mixed $data, ?string $format = null): bool
    {
        if ($data === null || \is_scalar($data) || \is_array($data)) {
            return true;
        }

        foreach ($this->normalizers as $normalizer) {
            if ($normalizer instanceof NormalizerInterface && $normalizer->supportsNormalization($data, $format)) {
                return true;
            }
        }

        return false;
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): object
    {
        foreach ($this->normalizers as $normalizer) {
            if ($normalizer instanceof DenormalizerInterface && $normalizer->supportsDenormalization($data, $type, $format)) {
                /** @var object */
                return $normalizer->denormalize($data, $type, $format, $context);
            }
        }

        throw new NotNormalizableValueException(sprintf(
            'Could not denormalize data into type "%s", no supporting denormalizer found.',
            $type,
        ));
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null): bool
    {
        foreach ($this->normalizers as $normalizer) {
            if ($normalizer instanceof DenormalizerInterface && $normalizer->supportsDenormalization($data, $type, $format)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function encode(mixed $data, string $format, array $context): string
    {
        foreach ($this->encoders as $encoder) {
            if ($encoder instanceof EncoderInterface && $encoder->supportsEncoding($format)) {
                return $encoder->encode($data, $format, $context);
            }
        }

        throw new InvalidArgumentException(sprintf('No encoder found for format "%s".', $format));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function decode(string $data, string $format, array $context): mixed
    {
        foreach ($this->encoders as $encoder) {
            if ($encoder instanceof DecoderInterface && $encoder->supportsDecoding($format)) {
                return $encoder->decode($data, $format, $context);
            }
        }

        throw new InvalidArgumentException(sprintf('No decoder found for format "%s".', $format));
    }
}
