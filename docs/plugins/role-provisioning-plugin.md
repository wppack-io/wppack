# ロールプロビジョニングプラグイン

ルールベースのロール割当・ブログ所属管理プラグイン。

## 概要

ユーザー登録時および SSO ログイン時に、設定されたルールに基づいてロールを自動割当します。SAML 属性、OAuth claims、メールアドレスなど任意の条件で柔軟にロールを制御できます。

## インストール

```bash
composer require wppack/role-provisioning-plugin
```

## 設定

WordPress 管理画面の **設定 > ロールプロビジョニング** から設定します。

### 基本設定

| 設定 | 説明 |
|------|------|
| 有効 | ロールプロビジョニングルールを有効化 |
| ブログに追加 | 新規ユーザーを現在のサイトに自動追加（マルチサイト） |
| ログイン時に同期 | SSO ログインのたびにルールを再評価 |
| 保護対象ロール | プロビジョニングで変更されないロール（デフォルト: administrator） |

### ログイン時同期の保護

「ログイン時に同期」を有効にした場合、以下の保護が適用されます:

1. **保護対象ロール**: 設定で指定したロール（デフォルト: administrator）を持つユーザーは、ルール再評価の対象外
2. **手動変更の保護**: プロビジョニングでロールが設定されると `_wppack_provisioned_role` メタに記録。管理者が手動でロールを変更した場合（現在のロール ≠ 記録値）、次回ログイン時の再評価はスキップ
3. **初回登録**: `user_register` 時は保護対象ロールのチェックのみ。手動変更の検出は初回登録には適用されない

### ルール構文

ルールは上から順に評価され、最初にマッチしたルールが適用されます。

```json
{
  "conditions": [
    {"field": "meta._wppack_sso_source", "operator": "equals", "value": "saml"},
    {"field": "user.email", "operator": "ends_with", "value": "@company.com"}
  ],
  "role": "editor",
  "blogIds": null
}
```

### 条件フィールド

| フィールド | 取得元 | 例 |
|-----------|--------|-----|
| `user.email` | WP_User->user_email | `user@example.com` |
| `user.login` | WP_User->user_login | `john.doe` |
| `meta.<key>` | ユーザーメタ値 | `meta._wppack_sso_source` → `saml` |
| `meta.<key>.<path>` | メタ値の JSON ドット記法パス | `meta._wppack_saml_attributes.groups.0` |

### 演算子

| 演算子 | 説明 |
|--------|------|
| `equals` | 完全一致 |
| `not_equals` | 不一致 |
| `contains` | 文字列に含まれる / 配列に含まれる |
| `starts_with` | 前方一致 |
| `ends_with` | 後方一致 |
| `matches` | 正規表現マッチ |
| `exists` | 値が存在する（value 不要） |

### 動的ロールテンプレート

`role` に `{{meta.<key>.<path>}}` を指定すると、ユーザーメタの値をそのままロール名として使用します。

```json
{
  "conditions": [{"field": "meta._wppack_sso_source", "operator": "equals", "value": "saml"}],
  "role": "{{meta._wppack_saml_attributes.wp_role}}",
  "blogIds": null
}
```

SAML 属性 `wp_role` の値（例: `editor`）がそのまま WordPress ロールになります。無効なロール名の場合は `default_role` にフォールバックします。

### マルチサイト設定

`blogIds` でルールの適用先サイトを制御します。

| 値 | 動作 |
|----|------|
| `null` | 全サイトに適用 |
| `[1, 3]` | 指定サイトのみに適用 |

## SSO 連携

### SAML

SAML ログイン時、以下のメタが自動保存されます:

| メタキー | 値 |
|---------|-----|
| `_wppack_sso_source` | `saml` |
| `_wppack_saml_attributes` | 全 SAML 属性（JSON） |

ルール例:
```json
{"field": "meta._wppack_saml_attributes.groups", "operator": "contains", "value": "admin"}
```

### OAuth

OAuth ログイン時、以下のメタが自動保存されます:

| メタキー | 値 |
|---------|-----|
| `_wppack_sso_source` | `oauth` |
| `_wppack_sso_provider` | プロバイダー名（例: `google`） |
| `_wppack_oauth_claims_<provider>` | 全 claims（JSON） |

ルール例:
```json
{"field": "meta._wppack_sso_provider", "operator": "equals", "value": "google"}
```

## フック

| タイミング | フック | 用途 |
|-----------|--------|------|
| 新規ユーザー登録 | `user_register` | 初回ロール/ブログ設定 |
| SAML ログイン（既存） | `SamlUserUpdatedEvent` | ログインごとのロール同期 |
| OAuth ログイン（既存） | `OAuthUserUpdatedEvent` | ログインごとのロール同期 |
