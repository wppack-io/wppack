# ElastiCache IAM 認証

**パッケージ:** `wppack/elasticache-auth`
**名前空間:** `WPPack\Component\Cache\Bridge\ElastiCacheAuth\`

AWS ElastiCache（Redis / Valkey）の IAM 認証をサポートする Bridge パッケージです。SigV4 署名付きトークンを AUTH パスワードとして使用し、パスワードの静的管理を不要にします。

## 概要

ElastiCache IAM 認証は、AWS IAM の認証情報（アクセスキー / インスタンスプロファイル / タスクロール等）を使って Redis / Valkey に接続する方式です。

**仕組み:**

1. `ElastiCacheIamTokenGenerator` が AWS SigV4 プリサイン URL を生成
2. プリサイン URL（`http://` プレフィックスを除去）を AUTH パスワードとして使用
3. トークンの有効期限は **15分**
4. PHP-FPM リクエストごとに新しいトークンを生成

**メリット:**

- パスワードの静的管理が不要
- IAM ポリシーによるきめ細かなアクセス制御
- クレデンシャルの自動ローテーション

## 前提条件

- **ElastiCache Redis 7.0+** または **Valkey 7.2+**
- **TLS 必須**（`rediss://` または `valkeys://` スキーム）
- **IAM ユーザーまたはロール**が ElastiCache への接続権限を持つこと
- ElastiCache キャッシュで **IAM 認証が有効**であること

## インストール

```bash
composer require wppack/elasticache-auth
```

> [!NOTE]
> `async-aws/core` が依存として自動的にインストールされます。`async-aws/ses`（`wppack/amazon-mailer` の依存）を既に使用している場合、追加の依存は発生しません。

## 設定方法

### 方法 1: `iam_auth` ショートカット（推奨）

最もシンプルな設定方法です。`RedisAdapterFactory` が `ElastiCacheIamTokenGenerator` を自動的に構成します。

```php
// wp-config.php
define('WPPACK_CACHE_DSN', 'rediss://my-cluster.xxxxx.apne1.cache.amazonaws.com:6379');
define('WPPACK_CACHE_OPTIONS', [
    'iam_auth' => true,
    'iam_region' => 'ap-northeast-1',
    'iam_user_id' => 'my-iam-user',
]);
```

### 方法 2: DSN クエリパラメータ

```php
// wp-config.php
define('WPPACK_CACHE_DSN', 'rediss://my-cluster.xxxxx.apne1.cache.amazonaws.com:6379?iam_auth=1&iam_region=ap-northeast-1&iam_user_id=my-iam-user');
```

### 方法 3: 手動 `credential_provider`

カスタムのクレデンシャル解決が必要な場合に使用します。

```php
// wp-config.php
use WPPack\Component\Cache\Bridge\ElastiCacheAuth\ElastiCacheIamTokenGenerator;

$generator = new ElastiCacheIamTokenGenerator(
    region: 'ap-northeast-1',
    userId: 'my-iam-user',
);

define('WPPACK_CACHE_DSN', 'rediss://my-cluster.xxxxx.apne1.cache.amazonaws.com:6379');
define('WPPACK_CACHE_OPTIONS', [
    'credential_provider' => $generator->createProvider(
        'my-cluster.xxxxx.apne1.cache.amazonaws.com:6379'
    ),
]);
```

### パラメータ

| パラメータ | 型 | 説明 |
|-----------|-----|------|
| `iam_auth` | bool | IAM 認証を有効にする |
| `iam_region` | string | AWS リージョン（例: `ap-northeast-1`） |
| `iam_user_id` | string | ElastiCache IAM ユーザー ID |

## AWS IAM ポリシー

ElastiCache に IAM 認証で接続するには、以下の権限が必要です。

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": "elasticache:Connect",
            "Resource": [
                "arn:aws:elasticache:ap-northeast-1:123456789012:replicationgroup:my-cluster",
                "arn:aws:elasticache:ap-northeast-1:123456789012:user:my-iam-user"
            ]
        }
    ]
}
```

> [!IMPORTANT]
> `Resource` には **レプリケーショングループ ARN** と **ユーザー ARN** の両方が必要です。

### Serverless の場合

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": "elasticache:Connect",
            "Resource": [
                "arn:aws:elasticache:ap-northeast-1:123456789012:serverlesscache:my-cache",
                "arn:aws:elasticache:ap-northeast-1:123456789012:user:my-iam-user"
            ]
        }
    ]
}
```

## クレデンシャル解決

`ElastiCacheIamTokenGenerator` は `async-aws/core` のデフォルトクレデンシャルチェーンを使用します。

| 優先順位 | ソース | 環境 |
|---------|--------|------|
| 1 | 環境変数 | すべて |
| 2 | Web Identity Token | EKS（IRSA） |
| 3 | SSO クレデンシャル | ローカル開発 |
| 4 | 共有クレデンシャルファイル | ローカル開発 |
| 5 | ECS コンテナクレデンシャル | ECS |
| 6 | EC2 インスタンスメタデータ | EC2 |

### EC2 インスタンスプロファイル

EC2 上で実行する場合、インスタンスプロファイルに ElastiCache Connect 権限を持つ IAM ロールをアタッチするだけで動作します。

### ECS タスクロール

ECS の場合、タスクロールに ElastiCache Connect 権限を付与します。タスク定義のタスクロール（`taskRoleArn`）を使用してください。

### EKS IRSA（IAM Roles for Service Accounts）

EKS の場合、サービスアカウントに IAM ロールをアノテーションします。

```yaml
apiVersion: v1
kind: ServiceAccount
metadata:
  name: my-app
  annotations:
    eks.amazonaws.com/role-arn: arn:aws:iam::123456789012:role/my-app-role
```

## トークンライフサイクル

### PHP-FPM 環境

PHP-FPM のリクエストごとに新しいトークンが生成されます。トークンの有効期限は 15 分であり、通常の PHP リクエスト処理時間内であれば問題ありません。

### 持続的接続での注意点

持続的接続（`persistent=1`）を使用する場合、接続が再利用されるため、初回接続時に生成されたトークンが使い続けられます。ElastiCache は接続タイムアウト（最大 12 時間）まで接続を維持するため、通常は問題ありません。

ただし、以下の点に注意してください:

- PHP-FPM ワーカーが長時間稼働する場合、接続が切断された際に新しいトークンで再接続されます
- `tcp_keepalive` を設定して接続の生存を確認することを推奨します

```php
define('WPPACK_CACHE_OPTIONS', [
    'iam_auth' => true,
    'iam_region' => 'ap-northeast-1',
    'iam_user_id' => 'my-iam-user',
    'persistent' => 1,
    'tcp_keepalive' => 60,
]);
```

## トラブルシューティング

### TLS 接続エラー

```
Error: Connection refused / SSL handshake failed
```

IAM 認証には TLS が必須です。DSN スキームが `rediss://` または `valkeys://` であることを確認してください。

### 認証失敗（AUTH failed）

```
Error: WRONGPASS invalid username-password pair
```

以下を確認してください:

1. **iam_user_id** が ElastiCache で作成した IAM ユーザー ID と一致すること
2. **iam_region** が ElastiCache クラスタのリージョンと一致すること
3. IAM ポリシーに `elasticache:Connect` 権限があること
4. ElastiCache キャッシュで IAM 認証が有効であること

### クレデンシャル解決エラー

```
RuntimeException: Unable to resolve AWS credentials for ElastiCache IAM authentication.
```

AWS クレデンシャルが利用可能であることを確認してください:

- EC2: インスタンスプロファイルが設定されていること
- ECS: タスクロールが設定されていること
- EKS: サービスアカウントに IRSA アノテーションがあること
- ローカル: 環境変数または `~/.aws/credentials` が設定されていること

### パッケージ未インストール

```
AdapterException: IAM authentication requires the wppack/elasticache-auth package.
```

`wppack/elasticache-auth` パッケージをインストールしてください:

```bash
composer require wppack/elasticache-auth
```

## 依存関係

### 必須
- **async-aws/core** ^1.28 — SigV4 署名、クレデンシャル解決

### 関連
- **wppack/redis-cache** — Redis / Valkey アダプタ（`credential_provider` 対応）
- **wppack/cache** — キャッシュ基盤
