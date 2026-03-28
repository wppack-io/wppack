# Scim Component

**パッケージ:** `wppack/scim`
**名前空間:** `WpPack\Component\Scim\`
**レイヤー:** Feature

WordPress 上で SCIM 2.0（RFC 7643/7644）プロビジョニングを実現するコンポーネントです。Azure AD、Okta、OneLogin などの IdP からのユーザー・グループの自動プロビジョニングを提供します。

## インストール

```bash
composer require wppack/scim
```

## 基本コンセプト

### Before（従来の WordPress）

```php
// IdP のユーザー変更を手動で反映
// - 管理者が CSV をインポート
// - カスタム API を独自実装
// - ユーザーの無効化は手動操作
```

### After（WpPack Scim）

```php
// IdP が SCIM API を呼び出し、自動的にプロビジョニング
// POST /wp-json/scim/v2/Users → ユーザー作成
// PATCH /wp-json/scim/v2/Users/42 → 属性更新
// DELETE /wp-json/scim/v2/Users/42 → ユーザー削除
// PUT /wp-json/scim/v2/Groups/editor → グループ更新
```

### アーキテクチャ概要

| 概念 | 説明 |
|---|---|
| Controller | REST API エンドポイント（Users, Groups, Schemas, ResourceTypes, ServiceProviderConfig） |
| Repository | WordPress ユーザー・ロールの CRUD 操作 |
| Mapper | SCIM 属性と WordPress データの双方向変換 |
| Filter | SCIM フィルター式の解析と `WP_User_Query` への変換 |
| PatchProcessor | RFC 7644 §3.5.2 PATCH 操作の処理 |
| Serializer | WordPress オブジェクトから SCIM JSON への変換 |
| Authenticator | Bearer トークン認証（`StatelessAuthenticatorInterface`） |
| Event | プロビジョニングライフサイクルイベント |

## 認証

### Bearer トークン認証フロー

```
┌─ IdP ──────────────────────────────────────────┐
│                                                 │
│  1. Authorization: Bearer {token} でリクエスト   │
│                                                 │
│  2. ScimBearerAuthenticator::supports()         │
│     → パス /wp-json/scim/v2/* かつ              │
│       Authorization: Bearer ヘッダーあり？       │
│                                                 │
│  3. ScimBearerAuthenticator::authenticate()     │
│     → hash_equals() で定数時間トークン比較       │
│     → SelfValidatingPassport（サービス識別子）    │
│                                                 │
│  4. ServiceToken 生成                            │
│     → capabilities: ['scim_provision']           │
│     → WordPress ユーザーなし（トークンベース認可）│
│                                                 │
│  5. CapabilityVoter                              │
│     → ServiceToken の capabilities をチェック     │
│     → 'scim_provision' あり → GRANTED            │
│                                                 │
└─────────────────────────────────────────────────┘
```

`ScimBearerAuthenticator` は Security コンポーネントの `StatelessAuthenticatorInterface` を実装し、`determine_current_user` フィルター経由で毎リクエスト検証されます。認証成功時は `ServiceToken` を生成し、WordPress ユーザーに依存しないトークンベースの認可を行います。

```php
use WpPack\Component\Scim\Authentication\ScimBearerAuthenticator;

$authenticator = new ScimBearerAuthenticator(
    bearerToken: $token,               // #[\SensitiveParameter]
);
```

## ユーザープロビジョニング

### CRUD 操作

| 操作 | HTTP メソッド | エンドポイント | 説明 |
|------|-------------|---------------|------|
| 一覧 | GET | `/scim/v2/Users` | フィルター・ページネーション対応 |
| 取得 | GET | `/scim/v2/Users/{id}` | 単一ユーザー取得 |
| 作成 | POST | `/scim/v2/Users` | ユーザー作成 |
| 置換 | PUT | `/scim/v2/Users/{id}` | ユーザー全属性更新 |
| 部分更新 | PATCH | `/scim/v2/Users/{id}` | PATCH 操作 |
| 削除 | DELETE | `/scim/v2/Users/{id}` | ユーザー削除 |

### 属性マッピング

`UserAttributeMapper` が SCIM 属性と WordPress データを双方向に変換します:

| SCIM 属性 | WordPress フィールド | 備考 |
|-----------|---------------------|------|
| `userName` | `user_login` | 作成後は immutable |
| `name.givenName` | `first_name` | |
| `name.familyName` | `last_name` | |
| `displayName` | `display_name` | |
| `nickName` | `nickname` | |
| `profileUrl` | `user_url` | |
| `emails[primary].value` | `user_email` | primary メールを使用 |
| `externalId` | `_wppack_scim_external_id` (meta) | IdP 側 ID |
| `active` | `_wppack_scim_active` (meta) | `0`/`1` |
| `locale` | `locale` (meta) | |
| `timezone` | `_wppack_scim_timezone` (meta) | |
| `title` | `_wppack_scim_title` (meta) | |

すべてのユーザー入力は WordPress のサニタイズ関数（`sanitize_user`, `sanitize_email`, `sanitize_text_field`, `esc_url_raw`）で処理されます。

## グループ管理

SCIM Group は WordPress のロールに対応します:

| SCIM 属性 | WordPress | 説明 |
|-----------|-----------|------|
| `id` | ロール名（slug） | 例: `editor`, `administrator` |
| `displayName` | ロールラベル | 例: "Editor", "Administrator" |
| `members` | ロールに属するユーザー一覧 | `value` = ユーザー ID |

> **WordPress の複数ロールサポート**
>
> WordPress は `WP_User::$roles`（配列）で 1 ユーザーに対し複数ロールを保持できます。SCIM のメンバー操作はこの仕組みに対応しています:
>
> - **メンバー追加** — `add_role()` を使用し、既存ロールを保持したまま新しいロールを追加
> - **メンバー削除** — `remove_role()` を使用し、該当ロールのみを削除（他のロールには影響しない）
> - **SCIM User レスポンス** — `groups` 配列にユーザーが持つ全ロールが含まれる

### グループ CRUD

| 操作 | HTTP メソッド | エンドポイント | 説明 |
|------|-------------|---------------|------|
| 一覧 | GET | `/scim/v2/Groups` | 全ロール一覧 |
| 取得 | GET | `/scim/v2/Groups/{id}` | 単一ロール取得 |
| 作成 | POST | `/scim/v2/Groups` | ロール作成 |
| 置換 | PUT | `/scim/v2/Groups/{id}` | ロール更新・メンバー置換 |
| 部分更新 | PATCH | `/scim/v2/Groups/{id}` | メンバー追加・削除 |
| 削除 | DELETE | `/scim/v2/Groups/{id}` | ロール削除 |

## フィルター

SCIM フィルター式（RFC 7644 §3.4.2.2）を解析し、`WP_User_Query` 引数に変換します。

### 対応演算子

| 演算子 | 説明 | 例 |
|--------|------|-----|
| `eq` | 等値 | `userName eq "john"` |
| `ne` | 不等値 | `active ne false` |
| `co` | 部分一致 | `displayName co "Smith"` |
| `sw` | 前方一致 | `userName sw "j"` |
| `ew` | 後方一致 | `emails.value ew "@example.com"` |
| `pr` | 存在確認 | `externalId pr` |
| `gt` | より大きい | 数値比較 |
| `ge` | 以上 | 数値比較 |
| `lt` | より小さい | 数値比較 |
| `le` | 以下 | 数値比較 |

### 論理演算子

```
// AND（優先度高）
userName eq "john" and active eq true

// OR（優先度低）
displayName co "Smith" or displayName co "Jones"

// 括弧によるグルーピング
(userName sw "j" or userName sw "k") and active eq true
```

### フィルター対応属性

| SCIM 属性 | WP_User_Query 変換先 |
|-----------|---------------------|
| `userName` | `login` / `user_login` |
| `displayName` | `display_name` |
| `name.givenName` | `first_name` |
| `name.familyName` | `last_name` |
| `emails.value` | `user_email` |
| `externalId` | `meta_query`（`_wppack_scim_external_id`） |
| `active` | `meta_query`（`_wppack_scim_active`） |

## PATCH 操作

RFC 7644 §3.5.2 に準拠した 3 種類の PATCH 操作をサポートします:

| 操作 | 説明 | 例 |
|------|------|-----|
| `add` | 属性の追加または既存値へのマージ | `{"op": "add", "path": "emails", "value": [...]}` |
| `replace` | 属性の置換 | `{"op": "replace", "path": "name.givenName", "value": "Jane"}` |
| `remove` | 属性の削除 | `{"op": "remove", "path": "title"}` |

### イミュータブル属性

`userName` と `id` は作成後に変更できません。PATCH でこれらを変更しようとすると `MutabilityException`（HTTP 400）が返されます。

### PATCH リクエスト例

```json
{
  "schemas": ["urn:ietf:params:scim:api:messages:2.0:PatchOp"],
  "Operations": [
    {
      "op": "replace",
      "path": "name.givenName",
      "value": "Jane"
    },
    {
      "op": "replace",
      "path": "active",
      "value": false
    }
  ]
}
```

## イベントシステム

プロビジョニングのライフサイクルで EventDispatcher イベントが発行されます:

### ユーザーイベント

| イベント | タイミング | 主なデータ |
|---------|----------|-----------|
| `UserProvisionedEvent` | ユーザー作成時 | `WP_User`, SCIM 属性 |
| `UserUpdatedEvent` | ユーザー更新時 | `WP_User`, 変更属性 |
| `UserDeactivatedEvent` | ユーザー無効化時 | `WP_User` |
| `UserDeletedEvent` | ユーザー削除時 | userId, userLogin |

### グループイベント

| イベント | タイミング | 主なデータ |
|---------|----------|-----------|
| `GroupProvisionedEvent` | ロール作成時 | roleName, roleLabel |
| `GroupUpdatedEvent` | ロール更新時 | roleName, 変更内容 |
| `GroupDeletedEvent` | ロール削除時 | roleName |
| `GroupMembershipChangedEvent` | メンバー変更時 | roleName, added[], removed[] |

### イベントリスナー例

```php
use WpPack\Component\EventDispatcher\Attribute\AsEventListener;
use WpPack\Component\Scim\Event\UserProvisionedEvent;
use WpPack\Component\Scim\Event\GroupMembershipChangedEvent;

final class ScimAuditListener
{
    #[AsEventListener(event: UserProvisionedEvent::class)]
    public function onUserProvisioned(UserProvisionedEvent $event): void
    {
        $user = $event->getUser();
        $attributes = $event->getScimAttributes();
        // 監査ログ記録...
    }

    #[AsEventListener(event: GroupMembershipChangedEvent::class)]
    public function onMembershipChanged(GroupMembershipChangedEvent $event): void
    {
        $roleName = $event->getRoleName();
        $added = $event->getAdded();       // list<int>
        $removed = $event->getRemoved();   // list<int>
        // メンバー変更の通知...
    }
}
```

## DI 統合

### ServiceProvider

```php
use WpPack\Component\Scim\DependencyInjection\ScimServiceProvider;

$builder->registerServiceProvider(new ScimServiceProvider());
```

`ScimServiceProvider` は以下のサービスを登録します:

- Mapper（`UserAttributeMapper`, `GroupMapper`）
- Serializer（`ScimUserSerializer`, `ScimGroupSerializer`）
- Filter（`FilterParser`, `WpUserQueryAdapter`）
- Patch（`PatchProcessor`）
- Repository（`ScimUserRepository`, `ScimGroupRepository`）
- Controller（`UserController`, `GroupController`, `SchemaController`, `ResourceTypeController`, `ServiceProviderConfigController`）

### CompilerPass

```php
use WpPack\Component\EventDispatcher\DependencyInjection\RegisterEventListenersPass;
use WpPack\Component\Rest\DependencyInjection\RegisterRestControllersPass;
use WpPack\Component\Security\DependencyInjection\RegisterAuthenticatorsPass;

$builder->addCompilerPass(new RegisterAuthenticatorsPass());
$builder->addCompilerPass(new RegisterEventListenersPass());
$builder->addCompilerPass(new RegisterRestControllersPass());
```

## ディスカバリーエンドポイント

SCIM 2.0 仕様に準拠したディスカバリーエンドポイントを提供します:

| エンドポイント | 説明 |
|--------------|------|
| GET `/scim/v2/ServiceProviderConfig` | サポート機能の公開（Patch, Filter, 認証スキーム） |
| GET `/scim/v2/Schemas` | User/Group スキーマ定義一覧 |
| GET `/scim/v2/Schemas/{id}` | 個別スキーマ取得 |
| GET `/scim/v2/ResourceTypes` | リソースタイプ定義一覧 |
| GET `/scim/v2/ResourceTypes/{id}` | 個別リソースタイプ取得 |

## セキュリティ考慮事項

| 脅威 | 対策 | 実装箇所 |
|------|------|---------|
| トークン漏洩 | `#[\SensitiveParameter]` でスタックトレースからの漏洩防止 | `ScimBearerAuthenticator`, `ScimConfiguration` |
| タイミング攻撃 | `hash_equals()` による定数時間比較 | `ScimBearerAuthenticator::authenticate()` |
| 不正アクセス | `#[IsGranted('scim_provision')]` + `ServiceToken` によるトークンベース認可 | `UserController`, `GroupController` |
| XSS / インジェクション | WordPress サニタイズ関数による入力検証 | `UserAttributeMapper` |
| userName 変更 | `MutabilityException` でイミュータブル属性の変更を拒否 | `UserController`, `PatchProcessor` |
| 不正なフィルター | `InvalidFilterException` でパース失敗を安全にハンドリング | `FilterParser` |

## 依存パッケージ

| パッケージ | 用途 |
|-----------|------|
| wppack/rest | REST API エンドポイント定義（`AbstractRestController`, `#[RestRoute]`） |
| wppack/user | ユーザーリポジトリ（`UserRepositoryInterface`） |
| wppack/role | ロール・権限管理（`#[IsGranted]`） |
| wppack/security | 認証フレームワーク（`StatelessAuthenticatorInterface`, `SelfValidatingPassport`） |
| wppack/http-foundation | Request/Response 抽象化 |
| wppack/event-dispatcher | イベントディスパッチ |
| wppack/site | マルチサイト対応 |
