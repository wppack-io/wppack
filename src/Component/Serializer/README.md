# Serializer Component

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=serializer)](https://codecov.io/github/wppack-io/wppack)

Object serialization with normalizer chain for WPPack.

## Installation

```bash
composer require wppack/serializer
```

## Usage

```php
use WPPack\Component\Serializer\Encoder\JsonEncoder;
use WPPack\Component\Serializer\Normalizer\BackedEnumNormalizer;
use WPPack\Component\Serializer\Normalizer\DateTimeNormalizer;
use WPPack\Component\Serializer\Normalizer\ObjectNormalizer;
use WPPack\Component\Serializer\Serializer;

$serializer = new Serializer(
    normalizers: [
        new BackedEnumNormalizer(),
        new DateTimeNormalizer(),
        new ObjectNormalizer(),
    ],
    encoders: [new JsonEncoder()],
);

// Serialize
$json = $serializer->serialize($myObject, 'json');

// Deserialize
$object = $serializer->deserialize($json, MyClass::class, 'json');

// Normalize / Denormalize
$array = $serializer->normalize($myObject);
$object = $serializer->denormalize($array, MyClass::class);
```

## Architecture

Follows Symfony Serializer's normalizer + encoder chain pattern:

- **Normalizers** convert objects to arrays and back
- **Encoders** convert arrays to string formats (JSON, etc.) and back
- **Serializer** orchestrates: `serialize()` = `normalize()` → `encode()`

### Built-in Normalizers

| Normalizer | Handles |
|---|---|
| `BackedEnumNormalizer` | `BackedEnum` ↔ scalar value |
| `DateTimeNormalizer` | `DateTimeInterface` ↔ ISO 8601 string |
| `ObjectNormalizer` | Objects ↔ arrays via public properties / constructor |

### Built-in Encoders

| Encoder | Format |
|---|---|
| `JsonEncoder` | JSON (`json_encode` / `json_decode` with `JSON_THROW_ON_ERROR`) |
