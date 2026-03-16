# Serializer コンポーネント

**パッケージ:** `wppack/serializer`
**名前空間:** `WpPack\Component\Serializer\`
**レイヤー:** Abstraction

Symfony Serializer に準じた Normalizer + Encoder チェーン方式のオブジェクト直列化コンポーネント。Messenger の `JsonSerializer` や Scheduler の `SqsPayloadFactory` が共通基盤として利用します。

## インストール

```bash
composer require wppack/serializer
```

## 基本コンセプト

### Before（重複する正規化ロジック）

```php
// Messenger JsonSerializer 内
private function normalizeMessage(object $message): array
{
    $ref = new \ReflectionClass($message);
    $data = [];
    foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
        $data[$prop->getName()] = $prop->getValue($message);
    }
    return $data;
}

// Scheduler SqsPayloadFactory 内 — まったく同じロジック
private function normalizeMessage(object $message): array
{
    $ref = new \ReflectionClass($message);
    $data = [];
    foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
        $data[$prop->getName()] = $prop->getValue($message);
    }
    return $data;
}
```

### After（Serializer コンポーネントに委譲）

```php
use WpPack\Component\Serializer\Encoder\JsonEncoder;
use WpPack\Component\Serializer\Normalizer\BackedEnumNormalizer;
use WpPack\Component\Serializer\Normalizer\DateTimeNormalizer;
use WpPack\Component\Serializer\Normalizer\ObjectNormalizer;
use WpPack\Component\Serializer\Serializer;

$serializer = new Serializer(
    normalizers: [
        new BackedEnumNormalizer(),
        new DateTimeNormalizer(),
        new ObjectNormalizer(),
    ],
    encoders: [new JsonEncoder()],
);

// どのコンポーネントからでも同一インスタンスで正規化
$normalized = $serializer->normalize($message);
$restored = $serializer->denormalize($data, MyMessage::class);
```

## アーキテクチャ

```
serialize()  =  normalize()  →  encode()
deserialize()  =  decode()  →  denormalize()

┌─ Serializer ──────────────────────────────────────┐
│                                                    │
│  Normalizer チェーン（具体的なものが先）            │
│  ├── BackedEnumNormalizer                          │
│  ├── DateTimeNormalizer                            │
│  └── ObjectNormalizer                              │
│                                                    │
│  Encoder チェーン                                  │
│  └── JsonEncoder                                   │
│                                                    │
└────────────────────────────────────────────────────┘
```

### 処理フロー

1. **serialize()**: `normalize()` でオブジェクトを配列に変換し、`encode()` で文字列に変換
2. **deserialize()**: `decode()` で文字列を配列に変換し、`denormalize()` でオブジェクトに復元
3. **normalize()/denormalize()**: 直接使用も可能（Messenger、Scheduler が利用するパターン）

## SerializerInterface

トップレベルの facade インターフェースです。

```php
namespace WpPack\Component\Serializer;

interface SerializerInterface
{
    public function serialize(mixed $data, string $format, array $context = []): string;
    public function deserialize(string $data, string $type, string $format, array $context = []): object;
    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|null;
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): object;
}
```

## Normalizer

### NormalizerInterface / DenormalizerInterface

個別の正規化戦略を定義するインターフェースです。

```php
namespace WpPack\Component\Serializer\Normalizer;

interface NormalizerInterface
{
    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|null;
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool;
}

interface DenormalizerInterface
{
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed;
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool;
}
```

### 組み込み Normalizer

#### ObjectNormalizer

Reflection ベースのオブジェクト正規化。パブリックプロパティを抽出し、コンストラクタ引数名で復元します。`NormalizerAwareInterface` / `DenormalizerAwareInterface` を実装し、ネストされたオブジェクトも再帰的に処理します。

```php
use WpPack\Component\Serializer\Normalizer\ObjectNormalizer;

$normalizer = new ObjectNormalizer();

// オブジェクト → 配列
$data = $normalizer->normalize(new MyMessage(content: 'hello', userId: 42));
// ['content' => 'hello', 'userId' => 42]

// 配列 → オブジェクト
$message = $normalizer->denormalize(['content' => 'hello', 'userId' => 42], MyMessage::class);
```

#### DateTimeNormalizer

`DateTimeInterface` と ISO 8601 文字列の相互変換。`context` でフォーマットをカスタマイズ可能。

```php
use WpPack\Component\Serializer\Normalizer\DateTimeNormalizer;

$normalizer = new DateTimeNormalizer();

$normalizer->normalize(new \DateTimeImmutable('2025-01-15T10:30:00+00:00'));
// '2025-01-15T10:30:00+00:00'

$normalizer->denormalize('2025-01-15T10:30:00+00:00', \DateTimeImmutable::class);
// DateTimeImmutable object

// カスタムフォーマット
$normalizer->normalize($date, context: [DateTimeNormalizer::FORMAT_KEY => 'Y-m-d']);
```

#### BackedEnumNormalizer

`BackedEnum` と scalar value の相互変換。

```php
use WpPack\Component\Serializer\Normalizer\BackedEnumNormalizer;

enum Status: string {
    case Active = 'active';
    case Inactive = 'inactive';
}

$normalizer = new BackedEnumNormalizer();

$normalizer->normalize(Status::Active);  // 'active'
$normalizer->denormalize('active', Status::class);  // Status::Active
```

### Normalizer の順序

`Serializer` コンストラクタに渡す順序が評価順序です。具体的な型を扱う Normalizer を先に配置します。

```php
$serializer = new Serializer(
    normalizers: [
        new BackedEnumNormalizer(),   // 最も具体的（BackedEnum のみ）
        new DateTimeNormalizer(),     // DateTime 系のみ
        new ObjectNormalizer(),       // フォールバック（あらゆる object）
    ],
    encoders: [new JsonEncoder()],
);
```

### NormalizerAware / DenormalizerAware

ネストされたオブジェクトの処理には、`NormalizerAwareInterface` / `DenormalizerAwareInterface` を実装します。`Serializer` が自動的に `setNormalizer()` / `setDenormalizer()` を呼び出します。

```php
use WpPack\Component\Serializer\Normalizer\NormalizerAwareInterface;
use WpPack\Component\Serializer\Normalizer\NormalizerAwareTrait;

final class MyNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        // ネストされたオブジェクトはチェーンに委譲
        return [
            'child' => $this->normalizer->normalize($data->child, $format, $context),
        ];
    }
}
```

## Encoder

### EncoderInterface / DecoderInterface

フォーマット変換を行うインターフェースです。

```php
namespace WpPack\Component\Serializer\Encoder;

interface EncoderInterface
{
    public function encode(mixed $data, string $format, array $context = []): string;
    public function supportsEncoding(string $format, array $context = []): bool;
}

interface DecoderInterface
{
    public function decode(string $data, string $format, array $context = []): mixed;
    public function supportsDecoding(string $format, array $context = []): bool;
}
```

### JsonEncoder

`json_encode` / `json_decode` ラッパー。`JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE` を使用。

```php
use WpPack\Component\Serializer\Encoder\JsonEncoder;

$encoder = new JsonEncoder();

$json = $encoder->encode(['key' => '日本語'], 'json');
// '{"key":"日本語"}'

$data = $encoder->decode('{"key":"value"}', 'json');
// ['key' => 'value']
```

## 使用例

### フル直列化 / 逆直列化

```php
$serializer = new Serializer(
    normalizers: [new BackedEnumNormalizer(), new DateTimeNormalizer(), new ObjectNormalizer()],
    encoders: [new JsonEncoder()],
);

// オブジェクト → JSON 文字列
$json = $serializer->serialize($myObject, 'json');

// JSON 文字列 → オブジェクト
$object = $serializer->deserialize($json, MyClass::class, 'json');
```

### 正規化のみ（Messenger/Scheduler パターン）

```php
// オブジェクト → 配列（JSON エンコードは呼び出し側で行う）
$normalized = $serializer->normalize($message);

// 配列 → オブジェクト
$message = $serializer->denormalize($data, MyMessage::class);
```

## Messenger / Scheduler との統合

### Messenger JsonSerializer

```php
use WpPack\Component\Messenger\Serializer\JsonSerializer;

// デフォルトで Serializer を内部生成（ObjectNormalizer + JsonEncoder）
$jsonSerializer = new JsonSerializer();

// カスタム Serializer を注入可能
$jsonSerializer = new JsonSerializer($customSerializer);
```

### Scheduler SqsPayloadFactory

```php
use WpPack\Component\Scheduler\Bridge\EventBridge\SqsPayloadFactory;

// デフォルトで Serializer を内部生成
$factory = new SqsPayloadFactory();

// カスタム Serializer を注入可能
$factory = new SqsPayloadFactory($customSerializer);
```

## 例外

| 例外 | 説明 |
|------|------|
| `NotNormalizableValueException` | 正規化/非正規化できない値 |
| `NotEncodableValueException` | エンコード/デコードできない値 |
| `InvalidArgumentException` | 不正な引数（対応するエンコーダーがない等） |

## 主要クラス一覧

| クラス | 説明 |
|-------|------|
| `SerializerInterface` | トップレベル facade インターフェース |
| `Serializer` | Normalizer/Encoder チェーンのオーケストレーター |
| `Normalizer\NormalizerInterface` | 正規化インターフェース |
| `Normalizer\DenormalizerInterface` | 非正規化インターフェース |
| `Normalizer\ObjectNormalizer` | Reflection ベースのオブジェクト正規化 |
| `Normalizer\DateTimeNormalizer` | DateTime ↔ ISO 8601 文字列 |
| `Normalizer\BackedEnumNormalizer` | BackedEnum ↔ scalar value |
| `Normalizer\NormalizerAwareInterface` | ネスト正規化委譲用インターフェース |
| `Normalizer\DenormalizerAwareInterface` | ネスト非正規化委譲用インターフェース |
| `Normalizer\NormalizerAwareTrait` | NormalizerAware のデフォルト実装 |
| `Normalizer\DenormalizerAwareTrait` | DenormalizerAware のデフォルト実装 |
| `Encoder\EncoderInterface` | エンコードインターフェース |
| `Encoder\DecoderInterface` | デコードインターフェース |
| `Encoder\JsonEncoder` | JSON エンコーダー/デコーダー |

## 依存関係

### 必須
- **php ^8.2**

### 利用コンポーネント
- **wppack/messenger** — `JsonSerializer` が `wppack/serializer` に依存
- **wppack/eventbridge-scheduler** — `SqsPayloadFactory` が `wppack/serializer` に依存
