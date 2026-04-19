# PasskeyLoginPlugin

WebAuthn/Passkey によるパスワードレスログインを WordPress に統合するプラグイン。`wppack/passkey-security` コンポーネントを利用し、環境変数ベースの設定・DI コンテナ統合・REST API を提供する。

## 概要

PasskeyLoginPlugin は `wppack/passkey-security` の WebAuthn 認証機能を WordPress プラグインとして使えるようにするパッケージです:

- **パスワードレス認証**: パスキーによるパスワード不要のログイン
- **Conditional UI**: ユーザー名フィールドの `autocomplete="username webauthn"` によるオートフィルアシスト
- **環境変数設定**: `wp-config.php` の `define()` または環境変数で設定
- **デバイス管理**: ユーザープロフィール画面からパスキーの登録・リネーム・削除
- **AAGUID デバイス名推定**: AAGUID からデバイス名を自動推定（iCloud Passkey, Google Password Manager, YubiKey 等）
- **マルチサイト対応**: メインサイトのドメインを RP ID として使用

## アーキテクチャ

### パッケージ構成

```
wppack/security              <- 認証基盤（AuthenticationSession）
    ^
wppack/passkey-security      <- WebAuthn 実装（CeremonyManager, CredentialRepository, ...）
    ^                           + web-auth/webauthn-lib ^5.2
wppack/passkey-login-plugin  <- WordPress 統合（DI, REST API, 環境変数設定, ログインフォーム）
```

### レイヤー構成

```
src/Plugin/PasskeyLoginPlugin/
├── wppack-passkey-login.php                           <- Bootstrap（Kernel::registerPlugin）
├── src/
│   ├── PasskeyLoginPlugin.php                         <- AbstractPlugin 実装
│   ├── Configuration/
│   │   └── PasskeyLoginConfiguration.php              <- 設定 VO（環境変数 / wp_options）
│   ├── DependencyInjection/
│   │   └── PasskeyLoginPluginServiceProvider.php      <- サービス登録
│   ├── Admin/
│   │   ├── PasskeyLoginSettingsPage.php               <- 管理画面（React）
│   │   └── PasskeyLoginSettingsController.php         <- 設定 REST API
│   ├── Profile/
│   │   └── PasskeyProfileSection.php                  <- ユーザープロフィール画面
│   ├── LoginForm/
│   │   └── PasskeyLoginForm.php                       <- ログインフォーム統合
│   └── Migration/
│       └── PasskeyCredentialTable.php                 <- DB マイグレーション
├── js/
│   └── src/
│       ├── settings/                                  <- 設定画面 React アプリ
│       └── profile/                                   <- パスキー管理 React アプリ
└── tests/
```

### 認証フロー

#### パスキー登録（Attestation）

```
┌─ ブラウザ（プロフィール画面） ────────────────────────┐
│                                                      │
│  1. 「パスキーを追加」ボタンをクリック                  │
│     → POST /wppack/v1/passkey/register/options       │
│     → CeremonyManager::createRegistrationOptions()   │
│     → チャレンジを Transient に保存                    │
│                                                      │
│  2. navigator.credentials.create() で認証器を呼び出し  │
│     → 指紋/Face ID/PIN で本人確認                     │
│                                                      │
│  3. Attestation レスポンスをサーバーに送信              │
│     → POST /wppack/v1/passkey/register/verify        │
│     → RegistrationController::verify()               │
│     → 検証後、CredentialRepository::save() で保存     │
│                                                      │
└──────────────────────────────────────────────────────┘
```

#### パスキー認証（Assertion）

```
┌─ ブラウザ（ログインページ） ─────────────────────────┐
│                                                      │
│  1. 「パスキーでサインイン」ボタンをクリック            │
│     または Conditional UI でオートフィル選択           │
│     → POST /wppack/v1/passkey/authenticate/options   │
│     → CeremonyManager::createAuthenticationOptions() │
│     → チャレンジを Transient に保存                    │
│                                                      │
│  2. navigator.credentials.get() で認証器を呼び出し    │
│     → 指紋/Face ID/PIN で本人確認                     │
│                                                      │
│  3. Assertion レスポンスをサーバーに送信               │
│     → POST /wppack/v1/passkey/authenticate/verify    │
│     → AuthenticationController::verify()             │
│     → 署名検証 → セッション確立 → リダイレクト        │
│                                                      │
└──────────────────────────────────────────────────────┘
```

## 依存パッケージ

| パッケージ | 用途 |
|-----------|------|
| wppack/passkey-security | WebAuthn 認証（CeremonyManager, CredentialRepository, AaguidResolver） |
| wppack/security | 認証基盤（AuthenticationSession） |
| wppack/kernel | プラグインブートストラップ（AbstractPlugin） |
| wppack/dependency-injection | DI コンテナ |
| wppack/rest | REST API エンドポイント定義 |
| wppack/database | DB マイグレーション（SchemaManager） |
| wppack/transient | チャレンジの一時保存 |
| web-auth/webauthn-lib | WebAuthn プロトコル実装（passkey-security 経由） |

## 名前空間

```
WPPack\Plugin\PasskeyLoginPlugin\
```

## 設定

### 環境変数

`wp-config.php` で `define()` を使って設定します。環境変数（`$_ENV`, `getenv()`）にも対応。未設定の場合は管理画面（Settings > Passkey Login）から設定可能です。

優先順位: **定数 > wp_options > 環境変数 > デフォルト**

| 変数 | デフォルト | 説明 |
|------|-----------|------|
| `PASSKEY_ENABLED` | `true` | パスキー認証の有効化 |
| `PASSKEY_RP_NAME` | `''`（サイト名を使用） | Relying Party 名（パスキープロンプトに表示） |
| `PASSKEY_RP_ID` | `''`（サイトドメインを使用） | Relying Party ID（ドメイン） |
| `PASSKEY_ALLOW_SIGNUP` | `false` | パスキーによる新規ユーザー登録を許可 |
| `PASSKEY_REQUIRE_USER_VERIFICATION` | `preferred` | ユーザー検証の要求レベル |

### RP ID の設定

RP ID はパスキーの有効範囲を決定するドメインです。未設定の場合はサイトのドメインが自動的に使用されます:

```php
// 明示的に設定
define('PASSKEY_RP_ID', 'example.com');

// サブドメインでも親ドメインを指定可能
// app.example.com で PASSKEY_RP_ID='example.com' とすると
// example.com のすべてのサブドメインでパスキーが有効
```

### User Verification

| 値 | 説明 |
|----|------|
| `preferred` | 可能であれば生体認証/PIN を要求（デフォルト） |
| `required` | 生体認証/PIN を必須化 |
| `discouraged` | 生体認証/PIN を省略（セキュリティキーのみの場合） |

## REST API エンドポイント

### 認証（パブリック）

| メソッド | パス | 説明 |
|---------|------|------|
| `POST` | `/wppack/v1/passkey/authenticate/options` | 認証チャレンジ生成 |
| `POST` | `/wppack/v1/passkey/authenticate/verify` | Assertion レスポンス検証・セッション確立 |

### 登録（要ログイン）

| メソッド | パス | 説明 |
|---------|------|------|
| `POST` | `/wppack/v1/passkey/register/options` | 登録チャレンジ生成 |
| `POST` | `/wppack/v1/passkey/register/verify` | Attestation レスポンス検証・クレデンシャル保存 |

### クレデンシャル管理（要ログイン）

| メソッド | パス | 説明 |
|---------|------|------|
| `GET` | `/wppack/v1/passkey/credentials` | 自分のパスキー一覧取得 |
| `PUT` | `/wppack/v1/passkey/credentials/{id}` | パスキーのリネーム |
| `DELETE` | `/wppack/v1/passkey/credentials/{id}` | パスキーの削除 |

### 設定（管理者のみ）

| メソッド | パス | 説明 |
|---------|------|------|
| `GET` | `/wppack/v1/passkey-login/settings` | 設定取得 |
| `POST` | `/wppack/v1/passkey-login/settings` | 設定保存 |

## パスキー管理（ユーザープロフィール）

ユーザープロフィール画面（`/wp-admin/profile.php`）に「Passkeys」セクションが追加されます:

- **パスキー一覧**: 登録済みパスキーのデバイス名、タイプ（Synced / Device-bound）、登録日時、最終使用日時を表示
- **パスキー登録**: 「Add Passkey」ボタンから新しいパスキーを登録
- **リネーム**: デバイス名の変更
- **削除**: 不要なパスキーの削除（確認ダイアログ付き）

管理者は他のユーザーのプロフィール画面でもパスキーセクションを閲覧できます。

## Conditional UI

ログインフォームのユーザー名フィールドに `autocomplete="username webauthn"` 属性を自動付与します。これにより、ブラウザがパスキーのオートフィル候補を表示し、ボタンをクリックせずにパスキー認証を開始できます。

Conditional UI は以下の条件で有効化されます:

1. ブラウザが `PublicKeyCredential.isConditionalMediationAvailable()` をサポートしている
2. ユーザーが当該サイトのパスキーを登録済みである

非対応ブラウザでは「パスキーでサインイン」ボタンによるモーダル認証にフォールバックします。

## マルチサイト対応

- **RP ID**: マルチサイト環境ではメインサイトのドメインが RP ID として使用されます。これにより、すべてのサブサイトで同じパスキーが利用可能です
- **クレデンシャルテーブル**: `wp_wppack_passkey_credentials` テーブルはベースプレフィックス（`$wpdb->base_prefix`）を使用し、ネットワーク全体で共有されます
- **ブログメンバーシップ**: 認証時にユーザーが現在のブログのメンバーでない場合、自動的に追加されます

## AAGUID デバイス名推定

AAGUID（Authenticator Attestation Globally Unique Identifier）からデバイス名を自動推定します。`AaguidResolver` は以下の主要な認証器をサポートしています:

| AAGUID | デバイス名 |
|--------|-----------|
| `fbfc3007-...` | iCloud Passkey |
| `ea9b8d66-...` | Google Password Manager |
| `0ea242b4-...` | Windows Hello |
| `cb69481e-...` | YubiKey 5 NFC |
| `bada5566-...` | 1Password |
| `d548826e-...` | Bitwarden |

未知の AAGUID は「Passkey」として表示されます。ユーザーはプロフィール画面からデバイス名を変更できます。

## データベース

プラグイン有効化時に `wp_wppack_passkey_credentials` テーブルが自動作成されます:

| カラム | 型 | 説明 |
|--------|-----|------|
| `id` | `bigint(20)` | 主キー |
| `user_id` | `bigint(20)` | WordPress ユーザー ID |
| `credential_id` | `varchar(1024)` | WebAuthn クレデンシャル ID（Base64URL） |
| `public_key` | `text` | 公開鍵（Base64） |
| `counter` | `bigint(20)` | 署名カウンター |
| `transports` | `varchar(255)` | トランスポート（JSON 配列） |
| `device_name` | `varchar(255)` | デバイス名 |
| `aaguid` | `char(36)` | AAGUID |
| `backup_eligible` | `tinyint(1)` | バックアップ対応（Synced パスキー） |
| `created_at` | `datetime` | 登録日時 |
| `last_used_at` | `datetime` | 最終使用日時 |

## セキュリティ考慮事項

- **チャレンジ管理**: チャレンジは Transient API に保存され、5 分（300 秒）で自動期限切れ。Cookie（`wppack_passkey_ck`）でチャレンジキーを紐付け
- **リプレイ攻撃防止**: チャレンジは検証後に即座に削除（ワンタイム使用）
- **署名カウンター**: 認証のたびにカウンターを更新し、クローン検出に使用
- **RP ID バインディング**: パスキーは RP ID（ドメイン）に紐付けられ、他のドメインでは使用不可
- **Resident Key 必須**: `residentKey: required` により、discoverable credential（パスキー）のみを許可
- **Cookie セキュリティ**: チャレンジキー Cookie は `HttpOnly`, `Secure`, `SameSite=Strict` で保護
