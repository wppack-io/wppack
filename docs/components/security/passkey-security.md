# PasskeySecurity

WebAuthn/Passkey 認証ブリッジ

## 概要

| 項目 | 値 |
|------|-----|
| パッケージ名 | `wppack/passkey-security` |
| 名前空間 | `WPPack\Component\Security\Bridge\Passkey\` |
| レイヤー | Abstraction（Bridge） |
| 依存 | `wppack/security`, `web-auth/webauthn-lib ^5.2` |

WebAuthn/Passkey によるパスワードレス認証を WPPack Security コンポーネントに統合する Bridge パッケージです。`web-auth/webauthn-lib` を使用した WebAuthn プロトコル実装と、クレデンシャルのストレージ抽象化を提供します。

## インストール

```bash
composer require wppack/passkey-security
```

## コアクラス

### CeremonyManager

WebAuthn の登録（Attestation）と認証（Assertion）のセレモニーを管理します。チャレンジの生成・保存・消費を担当し、`TransientManager` でチャレンジを一時保存します。

```php
use WPPack\Component\Security\Bridge\Passkey\Ceremony\CeremonyManager;

// 登録オプション生成（ログイン済みユーザー向け）
$options = $ceremonyManager->createRegistrationOptions($user);

// 認証オプション生成（ログインページ向け）
$options = $ceremonyManager->createAuthenticationOptions();

// チャレンジの消費（検証時に使用）
$data = $ceremonyManager->consumeChallenge();
```

主な設定:

- **Resident Key**: `required`（discoverable credential のみ許可）
- **アルゴリズム**: ES256（-7）、RS256（-257）
- **チャレンジ TTL**: 300 秒
- **Attestation**: `none`（デフォルト）

### CredentialRepositoryInterface

パスキークレデンシャルの永続化インターフェースです。`DatabaseCredentialRepository` がデフォルト実装として提供されます。

```php
use WPPack\Component\Security\Bridge\Passkey\Storage\CredentialRepositoryInterface;

interface CredentialRepositoryInterface
{
    public function findByUserId(int $userId): array;
    public function findByCredentialId(string $credentialId): ?PasskeyCredential;
    public function save(PasskeyCredential $credential): void;
    public function updateCounter(int $id, int $newCounter): void;
    public function updateLastUsed(int $id): void;
    public function updateDeviceName(int $id, string $name): void;
    public function delete(int $id): void;
    public function findAll(): array;
}
```

### AaguidResolver

AAGUID から認証器のデバイス名を推定するユーティリティクラスです。Apple iCloud Passkey、Google Password Manager、Windows Hello、YubiKey、1Password、Bitwarden 等の主要な認証器をサポートします。

```php
use WPPack\Component\Security\Bridge\Passkey\Storage\AaguidResolver;

$name = AaguidResolver::resolve('fbfc3007-154e-4ecc-8c0b-6e020557d7bd');
// => 'iCloud Passkey'

$name = AaguidResolver::resolve('unknown-aaguid');
// => 'Passkey'
```

### REST コントローラー

3 つの REST コントローラーが提供されます:

| コントローラー | 名前空間 | 説明 |
|--------------|---------|------|
| `AuthenticationController` | `wppack/v1/passkey` | Assertion セレモニー（パブリック） |
| `RegistrationController` | `wppack/v1/passkey` | Attestation セレモニー（要ログイン） |
| `CredentialController` | `wppack/v1/passkey` | クレデンシャル CRUD（要ログイン） |

## データモデル

### PasskeyCredential

パスキークレデンシャルの Value Object です。

```php
use WPPack\Component\Security\Bridge\Passkey\Storage\PasskeyCredential;

final readonly class PasskeyCredential
{
    public function __construct(
        public int $id,
        public int $userId,
        public string $credentialId,     // Base64URL エンコード
        public string $publicKey,         // Base64 エンコード
        public int $counter,              // 署名カウンター
        public array $transports,         // ['internal', 'hybrid', ...]
        public string $deviceName,        // デバイス名
        public string $aaguid,            // AAGUID (UUID)
        public bool $backupEligible,      // Synced パスキーかどうか
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $lastUsedAt,
    ) {}
}
```

### PasskeyConfiguration

WebAuthn セレモニーの設定を定義する Value Object です。

```php
use WPPack\Component\Security\Bridge\Passkey\Configuration\PasskeyConfiguration;

$config = new PasskeyConfiguration(
    rpName: 'My WordPress Site',
    rpId: 'example.com',
    userVerification: 'preferred',
    timeout: 60000,
    attestation: 'none',
);
```

## 依存ライブラリ

| ライブラリ | バージョン | 用途 |
|-----------|-----------|------|
| `web-auth/webauthn-lib` | `^5.2` | WebAuthn プロトコル実装（Attestation/Assertion 検証、CBOR パース） |
