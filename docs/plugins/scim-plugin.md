# ScimPlugin

SCIM 2.0 プロビジョニングを WordPress に統合するプラグイン。`wppack/scim` コンポーネントを利用し、環境変数ベースの設定・DI コンテナ統合・Bearer トークン認証を提供する。

## 概要

ScimPlugin は `wppack/scim` の SCIM 2.0 プロビジョニング機能を WordPress プラグインとして使えるようにするパッケージです:

- **ユーザープロビジョニング**: IdP からの自動ユーザー作成・更新・無効化・削除
- **グループ管理**: SCIM Group を WordPress ロールにマッピング
- **Bearer トークン認証**: ステートレスな API 認証
- **環境変数設定**: `wp-config.php` の `define()` または環境変数で設定
- **イベント連携**: プロビジョニングライフサイクルイベントの発行
- **マルチサイト対応**: メインサイトのみでルート登録、全サイトにロール定義を伝播

## アーキテクチャ

### パッケージ構成

```
wppack/security              ← 認証基盤（AuthenticationManager, StatelessAuthenticatorInterface）
    ↑
wppack/scim                  ← SCIM 2.0 実装（Controller, Repository, Mapper, Filter, Patch）
    ↑
wppack/scim-plugin           ← WordPress 統合（DI, 認証, 環境変数設定）
```

### レイヤー構成

```
src/Plugin/ScimPlugin/
├── src/
│   ├── ScimPlugin.php                            ← PluginInterface 実装
│   ├── Configuration/
│   │   └── ScimConfiguration.php                 ← 設定 VO（環境変数）
│   └── DependencyInjection/
│       └── ScimPluginServiceProvider.php          ← サービス登録
└── tests/
```

## プロビジョニングフロー

### User CREATE

```
┌─ IdP ──────────────────────────────────────────┐
│                                                 │
│  1. POST /wp-json/scim/v2/Users                 │
│     Authorization: Bearer {token}               │
│     Body: { userName, emails, name, ... }       │
│                                                 │
│  2. ScimBearerAuthenticator                     │
│     → hash_equals() でトークン検証               │
│     → ServiceToken 生成（トークンベース認可）     │
│                                                 │
│  3. UserController::create()                    │
│     → externalId 重複チェック                    │
│     → userName 重複チェック                      │
│     → UserAttributeMapper::toWordPress()        │
│     → ScimUserRepository::create()              │
│                                                 │
│  4. UserProvisionedEvent をディスパッチ           │
│                                                 │
│  5. HTTP 201 + SCIM User JSON + Location ヘッダー│
│                                                 │
└─────────────────────────────────────────────────┘
```

### User UPDATE / PATCH

```
┌─ IdP ──────────────────────────────────────────┐
│                                                 │
│  PUT  /wp-json/scim/v2/Users/{id}               │
│  PATCH /wp-json/scim/v2/Users/{id}              │
│                                                 │
│  → userName immutability チェック                │
│  → PatchProcessor で差分適用（PATCH の場合）      │
│  → UserAttributeMapper::toWordPress()           │
│  → active=false の場合 → deactivate()           │
│  → UserUpdatedEvent / UserDeactivatedEvent      │
│                                                 │
└─────────────────────────────────────────────────┘
```

### Group CRUD

```
┌─ IdP ──────────────────────────────────────────┐
│                                                 │
│  SCIM Group = WordPress ロール                  │
│                                                 │
│  POST   /scim/v2/Groups     → ロール作成        │
│  PUT    /scim/v2/Groups/{id} → ロール更新       │
│  PATCH  /scim/v2/Groups/{id} → メンバー追加/削除│
│  DELETE /scim/v2/Groups/{id} → ロール削除       │
│                                                 │
│  メンバー変更時:                                 │
│  → GroupMembershipChangedEvent(added, removed)  │
│                                                 │
└─────────────────────────────────────────────────┘
```

> **メンバーシップとロール操作**
>
> - SCIM グループメンバーシップはユーザーメタ（`_wppack_scim_group_{roleName} = '1'`）として保存される
> - メンバー追加・削除は WordPress ロール割り当てに影響しない（メタデータのみ）
> - ロール割り当ては将来の設定画面で制御予定
> - ユーザー無効化時（`active=false`）は全サイトで `set_role('')` によるロール剥奪 + `wp_authenticate_user` フィルターによるログインブロック + マルチサイトでは `update_user_status()` によるネイティブ無効化

## 依存パッケージ

| パッケージ | 用途 |
|-----------|------|
| wppack/scim | SCIM 2.0 コア実装（Controller, Repository, Mapper, Filter） |
| wppack/security | 認証基盤（AuthenticationManager, StatelessAuthenticatorInterface） |
| wppack/dependency-injection | DI コンテナ |
| wppack/event-dispatcher | イベントシステム |
| wppack/http-foundation | Request/Response 抽象化 |
| wppack/kernel | プラグインブートストラップ（PluginInterface） |
| wppack/rest | REST API エンドポイント登録（RestRegistry） |
| wppack/site | マルチサイト対応（BlogSwitcher） |
| wppack/user | ユーザーリポジトリ（UserRepositoryInterface） |

## 名前空間

```
WpPack\Plugin\ScimPlugin\
```

## 設定

### 環境変数

`wp-config.php` で `define()` を使って設定します。環境変数（`$_ENV`, `getenv()`）にも対応。

#### 必須

| 変数 | 説明 |
|------|------|
| `SCIM_BEARER_TOKEN` | SCIM API 認証用 Bearer トークン |

#### オプション

| 変数 | デフォルト | 説明 |
|------|-----------|------|
| `SCIM_AUTO_PROVISION` | `true` | 自動ユーザープロビジョニングの有効化 |
| `SCIM_DEFAULT_ROLE` | `subscriber` | プロビジョニング時のデフォルトロール |
| `SCIM_ALLOW_GROUP_MANAGEMENT` | `true` | SCIM によるグループ（ロール）管理の許可 |
| `SCIM_ALLOW_USER_DELETION` | `false` | 永続的なユーザー削除の許可（false = 無効化のみ） |
| `SCIM_MAX_RESULTS` | `100` | 一覧リクエストの最大結果数 |

### 設定例

```php
// wp-config.php
define('SCIM_BEARER_TOKEN', 'your-secure-random-token');
define('SCIM_DEFAULT_ROLE', 'subscriber');
define('SCIM_ALLOW_USER_DELETION', false);
```

## エンドポイント一覧

| メソッド | エンドポイント | 説明 |
|---------|--------------|------|
| GET | `/wp-json/scim/v2/ServiceProviderConfig` | サービスプロバイダー設定 |
| GET | `/wp-json/scim/v2/Schemas` | スキーマ定義一覧 |
| GET | `/wp-json/scim/v2/Schemas/{id}` | 個別スキーマ取得 |
| GET | `/wp-json/scim/v2/ResourceTypes` | リソースタイプ定義一覧 |
| GET | `/wp-json/scim/v2/ResourceTypes/{id}` | 個別リソースタイプ取得 |
| GET | `/wp-json/scim/v2/Users` | ユーザー一覧（フィルター・ページネーション対応） |
| POST | `/wp-json/scim/v2/Users` | ユーザー作成 |
| GET | `/wp-json/scim/v2/Users/{id}` | ユーザー取得 |
| PUT | `/wp-json/scim/v2/Users/{id}` | ユーザー置換 |
| PATCH | `/wp-json/scim/v2/Users/{id}` | ユーザー部分更新 |
| DELETE | `/wp-json/scim/v2/Users/{id}` | ユーザー削除 |
| GET | `/wp-json/scim/v2/Groups` | グループ一覧 |
| POST | `/wp-json/scim/v2/Groups` | グループ作成 |
| GET | `/wp-json/scim/v2/Groups/{id}` | グループ取得 |
| PUT | `/wp-json/scim/v2/Groups/{id}` | グループ置換 |
| PATCH | `/wp-json/scim/v2/Groups/{id}` | グループ部分更新 |
| DELETE | `/wp-json/scim/v2/Groups/{id}` | グループ削除 |

## IdP 設定ガイド

### Entra ID (Azure AD)

1. Azure Portal → Enterprise Applications → 新しいアプリケーション作成
2. Provisioning → Automatic に設定
3. 以下を入力:
   - Tenant URL: `https://your-site.com/wp-json/scim/v2`
   - Secret Token: `SCIM_BEARER_TOKEN` に設定した値

```php
// wp-config.php
define('SCIM_BEARER_TOKEN', 'your-azure-provisioning-token');
```

### Okta

1. Applications → SCIM 2.0 アプリを作成
2. Provisioning → Integration に設定:
   - SCIM connector base URL: `https://your-site.com/wp-json/scim/v2`
   - Authentication Mode: HTTP Header
   - Authorization: `SCIM_BEARER_TOKEN` の値

```php
define('SCIM_BEARER_TOKEN', 'your-okta-provisioning-token');
```

### OneLogin

1. Applications → SCIM 2.0 アプリを追加
2. Configuration:
   - SCIM Base URL: `https://your-site.com/wp-json/scim/v2`
   - SCIM Bearer Token: `SCIM_BEARER_TOKEN` の値

```php
define('SCIM_BEARER_TOKEN', 'your-onelogin-provisioning-token');
```

## セキュリティ考慮事項

- **トークン管理**: `SCIM_BEARER_TOKEN` は十分な長さ（64 文字以上推奨）のランダム文字列を使用。シークレット管理サービス経由での注入を推奨
- **SensitiveParameter**: `ScimConfiguration::$bearerToken` は `#[\SensitiveParameter]` でスタックトレースからの漏洩を防止
- **定数時間比較**: `hash_equals()` によるタイミング攻撃対策
- **HTTPS 必須**: Bearer トークンは平文で送信されるため、本番環境では HTTPS を必須化
- **トークンベース認可**: SCIM API は `ServiceToken` を使用し、WordPress ユーザーに依存しない最小権限（`scim_provision`）で認可。`manage_options` のような広い権限を付与しない
- **削除制御**: デフォルトで `SCIM_ALLOW_USER_DELETION=false`（無効化のみ）。完全削除は明示的に有効化が必要
- **ユーザー無効化の多層防御**: SCIM メタ（`_wppack_scim_active = '0'`）+ `wp_authenticate_user` フィルターによるログインブロック + 全サイトでのロール剥奪 + マルチサイトでの `update_user_status()` ネイティブ無効化
- **マルチサイト**: SCIM エンドポイントはメインサイトのみで登録。ロール定義 CRUD は全サイトに伝播
