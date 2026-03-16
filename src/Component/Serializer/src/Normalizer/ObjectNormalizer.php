<?php

declare(strict_types=1);

namespace WpPack\Component\Serializer\Normalizer;

use WpPack\Component\Serializer\Exception\NotNormalizableValueException;

final class ObjectNormalizer implements NormalizerInterface, DenormalizerInterface, NormalizerAwareInterface, DenormalizerAwareInterface
{
    use NormalizerAwareTrait;
    use DenormalizerAwareTrait;

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        if (!\is_object($data)) {
            throw new NotNormalizableValueException(sprintf(
                'Expected an object, got "%s".',
                get_debug_type($data),
            ));
        }

        $ref = new \ReflectionClass($data);
        $result = [];

        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $value = $prop->getValue($data);
            $result[$prop->getName()] = $this->normalizeValue($value, $format, $context);
        }

        return $result;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return \is_object($data);
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): object
    {
        if (!class_exists($type)) {
            throw new NotNormalizableValueException(sprintf(
                'Class "%s" does not exist.',
                $type,
            ));
        }

        $ref = new \ReflectionClass($type);
        $constructor = $ref->getConstructor();

        if ($constructor === null) {
            return $ref->newInstance();
        }

        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();

            if (\array_key_exists($name, $data)) {
                $args[$name] = $this->denormalizeValue($data[$name], $param, $format, $context);
            } elseif ($param->isDefaultValueAvailable()) {
                $args[$name] = $param->getDefaultValue();
            } elseif (!$param->isOptional()) {
                throw new NotNormalizableValueException(sprintf(
                    'Missing required constructor parameter "%s" for class "%s".',
                    $name,
                    $type,
                ));
            }
        }

        return $ref->newInstanceArgs($args);
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return \is_array($data) && class_exists($type);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function normalizeValue(mixed $value, ?string $format, array $context): mixed
    {
        if ($value === null || \is_scalar($value)) {
            return $value;
        }

        if (\is_array($value)) {
            return array_map(fn(mixed $v) => $this->normalizeValue($v, $format, $context), $value);
        }

        if (isset($this->normalizer) && \is_object($value) && $this->normalizer->supportsNormalization($value, $format, $context)) {
            return $this->normalizer->normalize($value, $format, $context);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function denormalizeValue(mixed $value, \ReflectionParameter $param, ?string $format, array $context): mixed
    {
        if ($value === null || \is_scalar($value)) {
            $type = $param->getType();

            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin() && \is_string($value)) {
                $typeName = $type->getName();
                if (isset($this->denormalizer) && $this->denormalizer->supportsDenormalization($value, $typeName, $format, $context)) {
                    return $this->denormalizer->denormalize($value, $typeName, $format, $context);
                }
            }

            return $value;
        }

        if (\is_array($value)) {
            $type = $param->getType();

            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $typeName = $type->getName();
                if (isset($this->denormalizer) && $this->denormalizer->supportsDenormalization($value, $typeName, $format, $context)) {
                    return $this->denormalizer->denormalize($value, $typeName, $format, $context);
                }
            }
        }

        return $value;
    }
}
