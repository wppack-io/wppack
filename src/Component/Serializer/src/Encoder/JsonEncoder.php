<?php

declare(strict_types=1);

namespace WpPack\Component\Serializer\Encoder;

use WpPack\Component\Serializer\Exception\NotEncodableValueException;

final class JsonEncoder implements EncoderInterface, DecoderInterface
{
    public const string FORMAT = 'json';

    public function encode(mixed $data, string $format, array $context = []): string
    {
        try {
            if (\function_exists('wp_json_encode')) {
                $json = wp_json_encode($data, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);

                if ($json === false) {
                    throw new NotEncodableValueException('wp_json_encode returned false.');
                }

                return $json;
            }

            return json_encode($data, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
        } catch (\JsonException $e) {
            throw new NotEncodableValueException($e->getMessage(), previous: $e);
        }
    }

    public function supportsEncoding(string $format): bool
    {
        return $format === self::FORMAT;
    }

    public function decode(string $data, string $format, array $context = []): mixed
    {
        try {
            if (\function_exists('wp_json_decode')) {
                return wp_json_decode($data, true, 512, \JSON_THROW_ON_ERROR);
            }

            return json_decode($data, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new NotEncodableValueException($e->getMessage(), previous: $e);
        }
    }

    public function supportsDecoding(string $format): bool
    {
        return $format === self::FORMAT;
    }
}
