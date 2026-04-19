# OAuthLoginPlugin

OAuth 2.0 / OpenID Connect マルチプロバイダー認証を WordPress に統合するプラグイン。`wppack/oauth-security` コンポーネントを利用し、環境変数ベースの設定・DI コンテナ統合・ルーティングを提供する。

## 概要

OAuthLoginPlugin は `wppack/oauth-security` の OAuth/OIDC 認証機能を WordPress プラグインとして使えるようにするパッケージです:

- **マルチプロバイダー対応**: Google、Microsoft Entra ID、GitHub、LINE、Yahoo! JAPAN など 17 種以上の IdP を同時に利用可能
- **SSO 統合**: wp-login.php に OAuth ログインボタンを追加、または完全置き換え
- **環境変数設定**: `wp-config.php` の `define()` で設定
- **JIT プロビジョニング**: ID トークン / UserInfo からWordPress ユーザーを自動作成
- **ロールマッピング**: トークンクレームから WordPress ロールへのマッピング
- **PKCE 対応**: Authorization Code Flow with PKCE でセキュアな認証

## アーキテクチャ

### パッケージ構成

```
wppack/security            ← 認証基盤（AuthenticationManager, AuthenticatorInterface）
    ^
wppack/oauth-security      ← OAuth 2.0 / OIDC 実装（OAuthAuthenticator, OAuthEntryPoint, ...）
    ^
wppack/oauth-login-plugin  ← WordPress 統合（DI, ルーティング, 環境変数設定）
```

### レイヤー構成

```
src/Plugin/OAuthLoginPlugin/
├── wppack-oauth-login.php                        ← Bootstrap（Kernel::registerPlugin）
├── src/
│   ├── OAuthLoginPlugin.php                      ← PluginInterface 実装
│   ├── OAuthLoginForm.php                        ← ログインフォーム（ボタン描画）
│   ├── Configuration/
│   │   ├── OAuthLoginConfiguration.php           ← 設定 VO（環境変数）
│   │   └── ProviderConfiguration.php             ← プロバイダー別設定 VO
│   ├── Controller/
│   │   ├── AuthorizeController.php               ← 認可リクエスト開始
│   │   ├── CallbackController.php                ← IdP コールバック処理
│   │   └── VerifyController.php                  ← クロスサイトトークン検証
│   └── DependencyInjection/
│       └── OAuthLoginPluginServiceProvider.php   ← サービス登録
└── tests/
```

### 認証フロー

#### SP-Initiated OAuth（Authorization Code Flow with PKCE）

```
┌─ ブラウザ ─────────────────────────────────────────────────┐
│                                                            │
│  1. /wp-login.php にアクセス                                │
│     → OAuthLoginForm が OAuth ログインボタンを表示           │
│     （SSO-only + 単一プロバイダーの場合は直接リダイレクト）    │
│                                                            │
│  2. "Login with Google" ボタンをクリック                     │
│     → /oauth/google/authorize                              │
│     → AuthorizeController                                  │
│     → OAuthEntryPoint が state + PKCE code_verifier を生成  │
│     → IdP の Authorization Endpoint にリダイレクト           │
│                                                            │
│  3. IdP で認証（ユーザー名/パスワード、MFA 等）              │
│                                                            │
│  4. IdP が /oauth/google/callback に認可コードを返却         │
│     → CallbackController                                   │
│     → AuthenticationManager → OAuthAuthenticator            │
│     → TokenExchanger で認可コードをトークンに交換            │
│     → ID トークン検証 / UserInfo 取得                       │
│     → OAuthUserResolver でユーザー解決                      │
│     → セッション確立、リダイレクト                           │
│                                                            │
└────────────────────────────────────────────────────────────┘
```

#### マルチサイトのクロスサイト認証

```
┌─ マルチサイト環境 ────────────────────────────────────────────────┐
│                                                                  │
│  1. サブサイト（blog2.example.com）でログインボタンをクリック      │
│     → /oauth/google/authorize → IdP にリダイレクト                │
│                                                                  │
│  2. IdP が メインサイト の /oauth/google/callback にコールバック   │
│     （redirect_uri は常にメインサイトの URL で構築）               │
│                                                                  │
│  3. メインサイトでトークン交換・ユーザー解決                      │
│     → CrossSiteRedirector がワンタイムトークンを生成              │
│     → サブサイトの /oauth/google/verify に POST リダイレクト      │
│                                                                  │
│  4. サブサイトの VerifyController がトークンを検証                │
│     → セッション確立、元のページにリダイレクト                    │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘
```

## 依存パッケージ

| パッケージ | 用途 |
|-----------|------|
| wppack/oauth-security | OAuth 2.0 / OIDC 認証（OAuthAuthenticator, OAuthEntryPoint, TokenExchanger） |
| wppack/security | 認証基盤（AuthenticationManager, AuthenticatorInterface, AuthenticationSession） |
| wppack/event-dispatcher | イベントシステム |
| wppack/dependency-injection | DI コンテナ |
| wppack/kernel | プラグインブートストラップ（PluginInterface） |

## 名前空間

```
WPPack\Plugin\OAuthLoginPlugin\
```

## 設定

### OAUTH_PROVIDERS

`wp-config.php` で `OAUTH_PROVIDERS` 定数に配列を定義します。キーがプロバイダー名（URL パスに使用）、値がプロバイダー設定です。

プロバイダー名は小文字英数字とハイフンのみ使用可能です（`/^[a-z0-9\-]+$/`）。

#### Google

```php
define('OAUTH_PROVIDERS', [
    'google' => [
        'type'          => 'google',
        'client_id'     => 'your-client-id.apps.googleusercontent.com',
        'client_secret' => 'GOCSPX-...',
        'hosted_domain' => 'example.com',  // オプション: Google Workspace ドメインに制限
        'label'         => 'Google',       // オプション: ボタン表示名
    ],
]);
```

#### Microsoft Entra ID

```php
define('OAUTH_PROVIDERS', [
    'entra-id' => [
        'type'          => 'entra-id',
        'client_id'     => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
        'client_secret' => 'your-client-secret',
        'tenant_id'     => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx', // 必須
        'label'         => 'Entra ID',
    ],
]);
```

#### GitHub

```php
define('OAUTH_PROVIDERS', [
    'github' => [
        'type'          => 'github',
        'client_id'     => 'Iv1.xxxxxxxxxxxx',
        'client_secret' => 'your-client-secret',
        'scopes'        => ['user:email'],  // デフォルト: ['user:email']
        'label'         => 'GitHub',
    ],
]);
```

#### Generic OIDC

```php
define('OAUTH_PROVIDERS', [
    'keycloak' => [
        'type'          => 'oidc',
        'client_id'     => 'wordpress',
        'client_secret' => 'your-client-secret',
        'discovery_url' => 'https://keycloak.example.com/realms/master/.well-known/openid-configuration',
        'label'         => 'Keycloak',
    ],
]);
```

#### 複数プロバイダーの同時利用

```php
define('OAUTH_PROVIDERS', [
    'google' => [
        'type'          => 'google',
        'client_id'     => 'google-client-id',
        'client_secret' => 'google-client-secret',
        'hosted_domain' => 'example.com',
        'label'         => 'Google',
    ],
    'entra-id' => [
        'type'          => 'entra-id',
        'client_id'     => 'entra-client-id',
        'client_secret' => 'entra-client-secret',
        'tenant_id'     => 'your-tenant-id',
        'label'         => 'Entra ID',
    ],
    'github' => [
        'type'          => 'github',
        'client_id'     => 'github-client-id',
        'client_secret' => 'github-client-secret',
        'label'         => 'GitHub',
    ],
]);
```

### プロバイダー設定パラメータ

#### 必須

| パラメータ | 説明 |
|-----------|------|
| `type` | プロバイダー種別（`google`, `entra-id`, `github`, `oidc` 等。`ProviderRegistry::types()` で全一覧取得可） |
| `client_id` | OAuth Client ID |
| `client_secret` | OAuth Client Secret |

#### オプション

| パラメータ | デフォルト | 説明 |
|-----------|-----------|------|
| `label` | プロバイダー名 | ログインボタンの表示名 |
| `tenant_id` | - | Microsoft Entra ID テナント ID（`entra-id` タイプで必須） |
| `hosted_domain` | `null` | Google Workspace ドメイン制限（`google` タイプ用） |
| `discovery_url` | `null` | OIDC Discovery URL（`oidc` タイプで必須） |
| `scopes` | タイプ依存 | OAuth スコープ。デフォルト: OIDC は `['openid', 'email', 'profile']`、GitHub は `['user:email']` |
| `auto_provision` | グローバル値 | プロバイダー別の JIT プロビジョニング設定 |
| `default_role` | グローバル値 | プロバイダー別のデフォルトロール |
| `role_claim` | `null` | ロールマッピングに使用するトークンクレーム名 |
| `role_mapping` | `null` | クレーム値から WordPress ロールへのマッピング |
| `domain` | - | プロバイダードメイン（`okta`, `auth0`, `onelogin`, `keycloak`, `cognito` タイプで必須） |
| `button_style` | `null` | ボタンスタイルバリアント（`ProviderIcons::styles()` のキー） |

### グローバル設定

| 定数 | デフォルト | 説明 |
|------|-----------|------|
| `OAUTH_SSO_ONLY` | `false` | `true` の場合、wp-login.php を OAuth ログインに置き換え。単一プロバイダーの場合は直接 IdP にリダイレクト、複数プロバイダーの場合はプロバイダー選択画面を表示 |
| `OAUTH_AUTO_PROVISION` | `false` | JIT ユーザープロビジョニング（プロバイダー別の `auto_provision` でオーバーライド可能） |
| `OAUTH_DEFAULT_ROLE` | `subscriber` | 新規ユーザーのデフォルトロール（プロバイダー別の `default_role` でオーバーライド可能） |
| `OAUTH_AUTHORIZE_PATH` | `/oauth/{provider}/authorize` | 認可エンドポイントパス |
| `OAUTH_CALLBACK_PATH` | `/oauth/{provider}/callback` | コールバックエンドポイントパス |
| `OAUTH_VERIFY_PATH` | `/oauth/{provider}/verify` | 検証エンドポイントパス |
| `OAUTH_BUTTON_DISPLAY` | `icon-text` | ログインボタン表示モード（`icon-text`, `icon-left`, `icon-only`, `text-only`） |

## マルチサイト対応

マルチサイト環境では、OAuth の `redirect_uri` はメインサイトの URL から構築されます。これは IdP 側に登録する Callback URL を 1 つに統一するためです。

```
redirect_uri = get_home_url(main_site_id, '/oauth/{provider}/callback')
```

### クロスサイトリダイレクト

サブサイトからの認証リクエストは以下のフローで処理されます:

1. サブサイトの `/oauth/{provider}/authorize` で認可リクエストを開始（`return_to` に元の URL を含む）
2. IdP がメインサイトの `/oauth/{provider}/callback` にコールバック
3. `CrossSiteRedirector` がワンタイムトークンを Transient に保存
4. サブサイトの `/oauth/{provider}/verify` に自動フォーム POST でリダイレクト
5. `VerifyController` がトークンを検証し、サブサイトでセッションを確立

## ユーザープロビジョニング

### JIT プロビジョニング

`OAUTH_AUTO_PROVISION` を `true` に設定する（またはプロバイダー別に `auto_provision` を設定する）と、IdP で認証済みの未登録ユーザーを自動作成します:

```php
define('OAUTH_AUTO_PROVISION', true);
define('OAUTH_DEFAULT_ROLE', 'subscriber');
```

ID トークン / UserInfo から以下のユーザー情報が同期されます:
- `email` → `user_email`
- `name` / `given_name` + `family_name` → `display_name`
- `given_name` → `first_name`
- `family_name` → `last_name`

### ロールマッピング

トークンクレームから WordPress ロールへのマッピング:

```php
define('OAUTH_PROVIDERS', [
    'entra-id' => [
        'type'          => 'entra-id',
        'client_id'     => 'your-client-id',
        'client_secret' => 'your-client-secret',
        'tenant_id'     => 'your-tenant-id',
        'role_claim'    => 'roles',
        'role_mapping'  => [
            'Admin'  => 'administrator',
            'Editor' => 'editor',
            'Author' => 'author',
        ],
    ],
]);
```

## IdP 設定ガイド

### Google

1. [Google Cloud Console](https://console.cloud.google.com/) → API とサービス → 認証情報
2. OAuth 2.0 クライアント ID を作成
3. 承認済みのリダイレクト URI: `https://your-site.com/oauth/google/callback`
4. OAuth 同意画面でスコープ `email`, `profile`, `openid` を設定

```php
define('OAUTH_PROVIDERS', [
    'google' => [
        'type'          => 'google',
        'client_id'     => 'your-client-id.apps.googleusercontent.com',
        'client_secret' => 'GOCSPX-...',
        'hosted_domain' => 'example.com', // Google Workspace ドメイン制限
    ],
]);
```

### Microsoft Entra ID

1. [Azure Portal](https://portal.azure.com/) → Microsoft Entra ID → アプリの登録 → 新規登録
2. リダイレクト URI: `https://your-site.com/oauth/entra-id/callback`（Web プラットフォーム）
3. 証明書とシークレット → 新しいクライアントシークレットを作成
4. API のアクセス許可 → `openid`, `email`, `profile` を追加

```php
define('OAUTH_PROVIDERS', [
    'entra-id' => [
        'type'          => 'entra-id',
        'client_id'     => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
        'client_secret' => 'your-client-secret',
        'tenant_id'     => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
    ],
]);
```

### GitHub

1. [GitHub Settings](https://github.com/settings/developers) → OAuth Apps → New OAuth App
2. Authorization callback URL: `https://your-site.com/oauth/github/callback`
3. Client ID と Client Secret を取得

```php
define('OAUTH_PROVIDERS', [
    'github' => [
        'type'          => 'github',
        'client_id'     => 'Iv1.xxxxxxxxxxxx',
        'client_secret' => 'your-client-secret',
    ],
]);
```

> [!NOTE]
> GitHub は OAuth 2.0 のみ対応（OpenID Connect 非対応）。ID トークンは発行されず、UserInfo API（`/user` および `/user/emails`）からユーザー情報を取得します。

### Keycloak

1. Keycloak 管理コンソール → Clients → Create client
2. Client type: OpenID Connect
3. Client ID: 任意（例: `wordpress`）
4. Valid redirect URIs: `https://your-site.com/oauth/keycloak/callback`
5. Client authentication: On → Credentials タブで Client Secret を取得

```php
define('OAUTH_PROVIDERS', [
    'keycloak' => [
        'type'          => 'keycloak',
        'client_id'     => 'wordpress',
        'client_secret' => 'your-client-secret',
        'domain'        => 'keycloak.example.com/realms/master',
    ],
]);
```

### 管理画面設定

プラグインの設定は管理画面（ネットワーク管理者 → Settings → OAuth Login）からも行えます:

- **プロバイダー管理**: GUI でプロバイダーの追加・削除・並び替え
- **ブランドスタイル**: プロバイダーごとにボタンスタイル（ライト/ダーク/ブランド等）を選択
- **ボタン表示モード**: アイコン+テキスト / アイコン左寄せ+テキスト / アイコンのみ / テキストのみ
- **ボタンプレビュー**: 選択したスタイルのリアルタイムプレビュー

定数（`OAUTH_PROVIDERS` 等）で定義されたプロバイダーは管理画面から編集できません（読み取り専用として表示されます）。`wp_options` で定義されたプロバイダーは GUI から編集可能です。

## セキュリティ考慮事項

- **PKCE（Proof Key for Code Exchange）**: すべてのプロバイダーで Authorization Code Flow with PKCE を使用。認可コードの横取り攻撃を防止
- **state パラメータ検証**: CSRF 攻撃を防止するため、state パラメータの生成・検証を実施。state は Transient に保存され、コールバック時に照合
- **HTTPS 強制**: 本番環境では HTTPS を使用すること。OAuth の redirect_uri は IdP 側でも HTTPS を要求するのが一般的
- **`#[\SensitiveParameter]`**: `client_secret` は `#[\SensitiveParameter]` 属性でスタックトレースからの漏洩を防止
- **hostedDomain 検証**: Google プロバイダーで `hosted_domain` を設定すると、指定ドメインのアカウントのみ認証を許可。ID トークンの `hd` クレームをサーバー側で検証
- **ID トークン検証**: OIDC プロバイダーでは JWKS エンドポイントから公開鍵を取得し、ID トークンの署名・issuer・audience・有効期限を検証
- **シークレット管理**: `client_secret` を `wp-config.php` に直接記述する代わりに、環境変数やシークレット管理サービス（AWS Secrets Manager 等）の利用を推奨

## エンドポイント一覧

| メソッド | パス | 説明 |
|---------|------|------|
| GET | `/oauth/{provider}/authorize` | 認可リクエストを開始。state と PKCE code_verifier を生成し、IdP の Authorization Endpoint にリダイレクト |
| GET | `/oauth/{provider}/callback` | IdP からのコールバック。認可コードをトークンに交換し、ユーザー認証を完了 |
| POST | `/oauth/{provider}/verify` | マルチサイトのクロスサイトトークン検証。メインサイトで発行されたワンタイムトークンを検証し、サブサイトでセッションを確立 |

パスはデフォルトで `/oauth/{provider}/...` 形式です。`OAUTH_AUTHORIZE_PATH`, `OAUTH_CALLBACK_PATH`, `OAUTH_VERIFY_PATH` で個別に変更可能です。
