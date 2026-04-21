# DynamoDB Cache Bridge

**パッケージ:** `wppack/dynamodb-cache`
**名前空間:** `WPPack\Component\Cache\Bridge\DynamoDb\`
**Category:** Data

Cache コンポーネントの AWS DynamoDB アダプタ実装。サーバーレスでフルマネージドなキャッシュバックエンドを提供します。Redis / Valkey のようなインフラ管理が不要で、オンデマンドキャパシティによるスケーラブルなキャッシュストレージとして利用できます。

## インストール

```bash
composer require wppack/dynamodb-cache
```

> [!NOTE]
> `async-aws/dynamo-db` が依存として自動的にインストールされます。`async-aws/ses`（`wppack/amazon-mailer` の依存）や `async-aws/core`（`wppack/elasticache-auth` の依存）を既に使用している場合、共通の `async-aws/core` は共有されます。

## ユースケース

- **サーバーレスアーキテクチャ**: Lambda + API Gateway 環境で Redis のインフラ管理が不要
- **オンデマンドスケーリング**: トラフィックに応じて自動スケール（PAY_PER_REQUEST）
- **マネージドサービス**: AWS 側で完全に管理されるため運用負荷が最小
- **VPC 不要**: DynamoDB はパブリックサービスのため VPC エンドポイントなしでもアクセス可能（ただし本番では VPC エンドポイント推奨）

### Redis / Valkey との比較

| | DynamoDB | Redis / Valkey (ElastiCache) |
|---|---|---|
| **インフラ管理** | 不要（フルマネージド） | Serverless またはノード管理 |
| **レイテンシ** | 数ミリ秒（HTTP ベース） | サブミリ秒（TCP 接続） |
| **スケーリング** | 自動（無制限） | 手動またはオートスケーリング |
| **コストモデル** | リクエスト + ストレージ課金 | インスタンス時間課金 |
| **接続方式** | HTTP/HTTPS（ステートレス） | TCP（持続的接続） |
| **VPC 要件** | なし（VPC エンドポイント推奨） | VPC 内に配置が必要 |
| **データ永続化** | 常時（3 AZ レプリケーション） | オプション（スナップショット / AOF） |
| **最大アイテムサイズ** | 400 KB | 512 MB |
| **適した環境** | サーバーレス、低〜中頻度アクセス | 高頻度アクセス、低レイテンシ要件 |

> [!TIP]
> 極めて低レイテンシが必要なケース（サブミリ秒）では Redis / Valkey を推奨します。DynamoDB は HTTP ベースのためネットワークラウンドトリップが大きくなります。

## 前提条件

- AWS アカウント
- DynamoDB テーブル（後述のスキーマで作成済み）
- IAM 権限（後述のポリシー参照）
- `async-aws/dynamo-db` ^3.0（自動インストール）

## テーブル設計

### スキーマ

DynamoDB アダプタは **複合キー設計**（パーティションキー + ソートキー）を採用しています。WordPress のグループベース flush に対応するため、キャッシュキーをパーティションキー（グループ）とソートキー（キー本体）に分解します。

| 属性 | 型 | キー | 説明 |
|------|-----|------|------|
| `p` | String (S) | パーティションキー (HASH) | グループプレフィックス（例: `wp:1:posts`） |
| `k` | String (S) | ソートキー (RANGE) | キャッシュキー（例: `my_cache_key`） |
| `v` | String (S) | — | キャッシュ値（シリアライズ済み文字列） |
| `t` | Number (N) | — | TTL Unix タイムスタンプ（DynamoDB TTL 対象属性） |

### 複合キーの利点

- **グループ単位の flush が高速**: `Query(PK = "wp:1:posts")` でパーティション内のみ読み取り（`Scan` 不要）
- **パーティション分散**: サイト + グループ単位で自然に分散し、ホットパーティションを回避
- **マルチサイト対応**: `blogId` がパーティションキーに含まれるため、サイト間のデータ分離が明確

### テーブル作成

#### AWS CLI

```bash
aws dynamodb create-table \
    --table-name cache \
    --key-schema \
        AttributeName=p,KeyType=HASH \
        AttributeName=k,KeyType=RANGE \
    --attribute-definitions \
        AttributeName=p,AttributeType=S \
        AttributeName=k,AttributeType=S \
    --billing-mode PAY_PER_REQUEST

# TTL を有効化（期限切れアイテムのバックグラウンド削除）
aws dynamodb update-time-to-live \
    --table-name cache \
    --time-to-live-specification Enabled=true,AttributeName=t
```

#### CloudFormation

```yaml
Resources:
  CacheTable:
    Type: AWS::DynamoDB::Table
    Properties:
      TableName: cache
      BillingMode: PAY_PER_REQUEST
      KeySchema:
        - AttributeName: p
          KeyType: HASH
        - AttributeName: k
          KeyType: RANGE
      AttributeDefinitions:
        - AttributeName: p
          AttributeType: S
        - AttributeName: k
          AttributeType: S
      TimeToLiveSpecification:
        AttributeName: t
        Enabled: true
      PointInTimeRecoverySpecification:
        PointInTimeRecoveryEnabled: false
      SSESpecification:
        SSEEnabled: true   # 保存時暗号化（AWS マネージドキー）
      Tags:
        - Key: Application
          Value: wordpress-cache
```

#### Terraform

```hcl
resource "aws_dynamodb_table" "cache" {
  name         = "cache"
  billing_mode = "PAY_PER_REQUEST"
  hash_key     = "p"
  range_key    = "k"

  attribute {
    name = "p"
    type = "S"
  }

  attribute {
    name = "k"
    type = "S"
  }

  ttl {
    attribute_name = "t"
    enabled        = true
  }

  server_side_encryption {
    enabled = true   # AWS マネージドキー
  }

  tags = {
    Application = "wordpress-cache"
  }
}
```

> [!IMPORTANT]
> テーブル作成後、必ず TTL を `t` 属性に対して有効化してください。TTL はバックグラウンドで期限切れアイテムを自動削除し、ストレージコストを最適化します（追加の DynamoDB コストは発生しません）。

## DSN 設定

```php
// wp-config.php

// 基本（リージョン / テーブル名）
define('CACHE_DSN', 'dynamodb://ap-northeast-1/cache');

// テーブル名省略（デフォルト "cache"）
define('CACHE_DSN', 'dynamodb://ap-northeast-1');

// DynamoDB Local（開発/テスト用）
define('CACHE_DSN', 'dynamodb://us-east-1/cache?endpoint=http://localhost:8000');

// key_prefix 指定（デフォルト "wp:"）
define('CACHE_DSN', 'dynamodb://ap-northeast-1/cache?key_prefix=mysite:');
```

### DSN 書式

```
dynamodb://region[/table-name][?options]
```

- **リージョン**: DSN のホスト部に AWS リージョンを指定（必須）
- **テーブル名**: DSN のパスに指定（省略時は `cache`）
- **オプション**: クエリパラメータで指定

### DSN オプション

| オプション | 型 | デフォルト | 説明 |
|-----------|-----|-----------|------|
| `endpoint` | string | — | カスタムエンドポイント URL（DynamoDB Local 等） |
| `key_prefix` | string | `wp:` | キープレフィックス（`WPPACK_CACHE_PREFIX` と合わせる） |

### オプション配列での上書き

DSN のクエリパラメータは `WPPACK_CACHE_OPTIONS` 定数で上書き / 補完できます。

```php
// wp-config.php
define('CACHE_DSN', 'dynamodb://ap-northeast-1/cache');
define('WPPACK_CACHE_OPTIONS', [
    'table' => 'my_custom_table',       // テーブル名上書き
    'endpoint' => 'http://localhost:8000', // エンドポイント上書き
    'key_prefix' => 'mysite:',           // プレフィックス上書き
]);
```

`WPPACK_CACHE_OPTIONS` の値は DSN のクエリパラメータよりも優先されます。

## AWS IAM ポリシー

### 基本ポリシー（全機能）

すべてのキャッシュ操作（読み取り / 書き込み / 削除 / flush）を許可するポリシーです。

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "dynamodb:GetItem",
                "dynamodb:PutItem",
                "dynamodb:DeleteItem",
                "dynamodb:BatchWriteItem",
                "dynamodb:Query",
                "dynamodb:Scan",
                "dynamodb:DescribeTable"
            ],
            "Resource": "arn:aws:dynamodb:ap-northeast-1:123456789012:table/cache"
        }
    ]
}
```

### 操作と IAM アクションの対応

| アダプタメソッド | DynamoDB API | IAM アクション |
|----------------|-------------|---------------|
| `get()` | `GetItem` | `dynamodb:GetItem` |
| `getMultiple()` | `GetItem` × N | `dynamodb:GetItem` |
| `set()` | `PutItem` | `dynamodb:PutItem` |
| `setMultiple()` | `BatchWriteItem` (Put) | `dynamodb:BatchWriteItem`, `dynamodb:PutItem` |
| `add()` | `PutItem` (条件付き) | `dynamodb:PutItem` |
| `delete()` | `DeleteItem` | `dynamodb:DeleteItem` |
| `deleteMultiple()` | `BatchWriteItem` (Delete) | `dynamodb:BatchWriteItem`, `dynamodb:DeleteItem` |
| `increment()` / `decrement()` | `GetItem` + `PutItem` | `dynamodb:GetItem`, `dynamodb:PutItem` |
| `flush(prefix)` | `Query` + `BatchWriteItem` | `dynamodb:Query`, `dynamodb:BatchWriteItem`, `dynamodb:DeleteItem` |
| `flush('')` | `Scan` + `BatchWriteItem` | `dynamodb:Scan`, `dynamodb:BatchWriteItem`, `dynamodb:DeleteItem` |
| `isAvailable()` | `DescribeTable` | `dynamodb:DescribeTable` |

### 制限付きポリシー（Scan 除外）

本番環境では全テーブルスキャン（`flush('')`）を制限し、グループ単位の flush のみ許可することを推奨します。

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "dynamodb:GetItem",
                "dynamodb:PutItem",
                "dynamodb:DeleteItem",
                "dynamodb:BatchWriteItem",
                "dynamodb:Query",
                "dynamodb:DescribeTable"
            ],
            "Resource": "arn:aws:dynamodb:ap-northeast-1:123456789012:table/cache"
        }
    ]
}
```

> [!TIP]
> `dynamodb:Scan` を除外すると `wp_cache_flush()`（全キャッシュ削除）は `AccessDeniedException` で失敗しますが、`wp_cache_flush_group()`（グループ削除）は正常に動作します。大規模テーブルでの意図しない全削除を防げます。

### 読み取り専用ポリシー

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "dynamodb:GetItem",
                "dynamodb:Query",
                "dynamodb:DescribeTable"
            ],
            "Resource": "arn:aws:dynamodb:ap-northeast-1:123456789012:table/cache"
        }
    ]
}
```

### 条件キーによる制限

特定のパーティションキーのみアクセスを許可する場合:

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "dynamodb:GetItem",
                "dynamodb:PutItem",
                "dynamodb:DeleteItem",
                "dynamodb:Query"
            ],
            "Resource": "arn:aws:dynamodb:ap-northeast-1:123456789012:table/cache",
            "Condition": {
                "ForAllValues:StringLike": {
                    "dynamodb:LeadingKeys": ["wp:1:*"]
                }
            }
        }
    ]
}
```

この例では、サイト1（`wp:1:*`）のキャッシュのみアクセスを許可します。マルチテナント環境でサイト間のアクセス分離に有用です。

## クレデンシャル解決

`async-aws/dynamo-db` は `async-aws/core` のデフォルトクレデンシャルチェーンを使用します。

| 優先順位 | ソース | 環境 |
|---------|--------|------|
| 1 | 環境変数 (`AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`) | すべて |
| 2 | Web Identity Token (`AWS_WEB_IDENTITY_TOKEN_FILE`) | EKS（IRSA） |
| 3 | SSO クレデンシャル | ローカル開発 |
| 4 | 共有クレデンシャルファイル (`~/.aws/credentials`) | ローカル開発 |
| 5 | ECS コンテナクレデンシャル (`AWS_CONTAINER_CREDENTIALS_*`) | ECS |
| 6 | EC2 インスタンスメタデータ (IMDS) | EC2 |

### EC2 インスタンスプロファイル

EC2 上で実行する場合、インスタンスプロファイルに DynamoDB 権限を持つ IAM ロールをアタッチするだけで動作します。

```bash
# インスタンスプロファイルを EC2 にアタッチ
aws ec2 associate-iam-instance-profile \
    --instance-id i-1234567890abcdef0 \
    --iam-instance-profile Name=WordPress-CacheRole
```

### ECS タスクロール

ECS の場合、タスクロールに DynamoDB 権限を付与します。タスク定義の `taskRoleArn` を使用してください。

```json
{
    "taskRoleArn": "arn:aws:iam::123456789012:role/wordpress-cache-role",
    "containerDefinitions": [
        {
            "name": "wordpress",
            "image": "wordpress:latest"
        }
    ]
}
```

### EKS IRSA（IAM Roles for Service Accounts）

EKS の場合、サービスアカウントに IAM ロールをアノテーションします。

```yaml
apiVersion: v1
kind: ServiceAccount
metadata:
  name: wordpress
  annotations:
    eks.amazonaws.com/role-arn: arn:aws:iam::123456789012:role/wordpress-cache-role
```

### Lambda 実行ロール

Lambda の場合、実行ロールに DynamoDB 権限を付与します。

```yaml
# SAM テンプレート例
Resources:
  WordPressFunction:
    Type: AWS::Serverless::Function
    Properties:
      Policies:
        - DynamoDBCrudPolicy:
            TableName: !Ref CacheTable
```

### ローカル開発

環境変数または `~/.aws/credentials` ファイルでクレデンシャルを設定します。

```bash
# 環境変数
export AWS_ACCESS_KEY_ID=AKIAIOSFODNN7EXAMPLE
export AWS_SECRET_ACCESS_KEY=wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY
export AWS_DEFAULT_REGION=ap-northeast-1

# または AWS CLI プロファイル
aws configure --profile wordpress-dev
```

## マルチサイト対応

### キープレフィックス構造

`ObjectCache` がアダプタに渡すキーの構造:

```
{prefix}{blogId}:{group}:{key}
```

例:
- サイト1の posts グループ: `wp:1:posts:my_cache_key`
- サイト2の options グループ: `wp:2:options:my_setting`
- グローバルグループ（`users` 等）: `wp:0:users:user_1`

### DynamoDB テーブルでのマッピング

アダプタの `splitKey()` メソッドがキーをパーティションキー（PK）とソートキー（SK）に分解します。

| キー全体 | PK (`p`) | SK (`k`) |
|---------|----------|----------|
| `wp:1:posts:my_key` | `wp:1:posts` | `my_key` |
| `wp:2:options:my_setting` | `wp:2:options` | `my_setting` |
| `wp:0:users:user_1` | `wp:0:users` | `user_1` |
| `wp:1:posts:sub:key:value` | `wp:1:posts` | `sub:key:value` |

### flush のスコープと DynamoDB API

| 操作 | 対象 | DynamoDB API | コスト |
|------|------|-------------|--------|
| `flushGroup('posts')` | サイト固有の posts グループ | `Query(PK="wp:1:posts")` → `BatchWriteItem` | **低**（パーティション内のみ） |
| `flush()` | 全アイテム | `Scan` → `BatchWriteItem` | **高**（テーブル全体） |

- **prefix あり（グループ flush）**: `rtrim(prefix, ':')` で PK を取得 → `Query` でソートキー一覧を取得 → `BatchWriteItem` で 25 件ずつ削除
- **prefix なし（全 flush）**: `Scan` → `BatchWriteItem` ループ（ページネーション対応）

グローバルグループは `blogId=0` で管理されるため、サイト固有の flush の影響を受けません。

## TTL の仕組み

DynamoDB アダプタは **二重の TTL チェック**を行います。

### 1. アプリケーションレベルチェック（即時）

`get()` 時に `t` 属性を検査し、`time() > t` であれば即座に `false` を返します。ユーザーからは期限切れアイテムは見えません。

### 2. DynamoDB TTL（バックグラウンド削除）

DynamoDB の TTL 機能がバックグラウンドで期限切れアイテムを自動削除します。

- **コスト**: 追加の読み取り / 書き込みユニット消費なし
- **削除遅延**: 期限切れ後 **最大48時間**（通常は数分〜数時間）
- **削除順序**: 保証なし

> [!IMPORTANT]
> DynamoDB TTL の削除には遅延があるため、期限切れアイテムは `Scan` / `Query` の結果に含まれる可能性があります。アプリケーションレベルのチェックにより読み取り時にフィルタリングされるため、ユーザーへの影響はありません。

### TTL なし（永続キャッシュ）

`ttl = 0`（デフォルト）の場合、`t` 属性は保存されず、アイテムは永続化されます。明示的な `delete()` または `flush()` で削除するまで残ります。

## アダプタクラス

### DynamoDbAdapter

`AbstractAdapter` を継承。`async-aws/dynamo-db` の `DynamoDbClient` を使用し、DynamoDB テーブルへの HTTP ベースの CRUD 操作を提供します。

```php
final class DynamoDbAdapter extends AbstractAdapter
{
    public function __construct(
        private readonly string $table,
        string $region,
        private readonly string $keyPrefix = 'wp:',
        ?string $endpoint = null,
    ) {}

    public function getName(): string { return 'dynamodb'; }
}
```

### DynamoDbAdapterFactory

DSN から `DynamoDbAdapter` を生成するファクトリ。`dynamodb` スキームを処理し、リージョン / テーブル名 / エンドポイント / キープレフィックスを DSN とオプション配列から解決します。

```php
// 自動検出
$adapter = Adapter::fromDsn('dynamodb://ap-northeast-1/cache');

// DynamoDB Local
$adapter = Adapter::fromDsn('dynamodb://us-east-1/cache?endpoint=http://localhost:8000');
```

## クイックスタート

```php
// 1. wp-config.php で DSN を設定
define('CACHE_DSN', 'dynamodb://ap-northeast-1/cache');
define('WPPACK_CACHE_PREFIX', 'wp:');

// 2. ドロップインを配置
// cp vendor/wppack/cache/drop-in/object-cache.php wp-content/object-cache.php

// 3. WordPress の Object Cache が自動的に DynamoDB を使用
// CacheManager は透過的に動作
use WPPack\Component\Cache\CacheManager;

$cache = new CacheManager();
$cache->set('key', 'value', 'my_app', 3600);
$data = $cache->get('key', 'my_app');
```

### プログラマティックな使用

ドロップインを使わず、直接 `ObjectCache` を使用することもできます。

```php
use WPPack\Component\Cache\Adapter\Adapter;
use WPPack\Component\Cache\ObjectCache;

$adapter = Adapter::fromDsn('dynamodb://ap-northeast-1/cache');
$cache = new ObjectCache($adapter, 'wp:');

$cache->set('key', 'value', 'my_group', 3600);
$data = $cache->get('key', 'my_group');
```

## DynamoDB Local（開発/テスト）

### Docker 設定

`docker-compose.yml` に DynamoDB Local サービスが含まれています。

```bash
# DynamoDB Local 起動
docker compose up -d dynamodb

# テスト実行
vendor/bin/phpunit src/Component/Cache/Bridge/DynamoDb/tests/

# DynamoDB Local 停止
docker compose down dynamodb
```

### wp-config.php

```php
define('CACHE_DSN', 'dynamodb://us-east-1/cache?endpoint=http://localhost:8000');
```

> [!NOTE]
> DynamoDB Local ではリージョンは任意の値で構いませんが、DSN パース時に必須のため指定してください。AWS クレデンシャルも任意の値を受け付けますが、`async-aws` が認証ヘッダーを生成するため存在は必要です（テストコードでは自動的にダミー値を設定します）。

### テーブル手動作成（DynamoDB Local）

```bash
aws dynamodb create-table \
    --table-name cache \
    --key-schema \
        AttributeName=p,KeyType=HASH \
        AttributeName=k,KeyType=RANGE \
    --attribute-definitions \
        AttributeName=p,AttributeType=S \
        AttributeName=k,AttributeType=S \
    --billing-mode PAY_PER_REQUEST \
    --endpoint-url http://localhost:8000
```

## パフォーマンス考慮事項

### オンデマンド vs プロビジョンドキャパシティ

| | オンデマンド (PAY_PER_REQUEST) | プロビジョンド |
|---|---|---|
| **概要** | リクエストごとに自動課金 | RCU / WCU を事前に設定 |
| **スケーリング** | 自動（即時） | 手動または Auto Scaling |
| **コスト** | リクエスト単価が高い | 予約済み容量は安い |
| **適したケース** | 予測困難なトラフィック | 安定したトラフィック |
| **推奨** | **新規プロジェクト**（デフォルト） | コスト最適化フェーズ |

### DAX（DynamoDB Accelerator）

DynamoDB 前段にインメモリキャッシュを追加する場合は [DAX](https://docs.aws.amazon.com/amazondynamodb/latest/developerguide/DAX.html) を検討してください。

- マイクロ秒レベルのレイテンシを実現
- DynamoDB と API 互換（コード変更不要でエンドポイント切り替えのみ）
- VPC 内に配置が必要

> [!NOTE]
> DAX を使用する場合、`endpoint` オプションに DAX クラスタエンドポイントを指定します。ただし、`async-aws/dynamo-db` は DAX プロトコルに直接対応していないため、DAX の DynamoDB 互換エンドポイントを使用してください。

### 大規模 flush の注意点

- `flush('')`（全削除）は `Scan` を使用するため、テーブル全体を読み取る
- 大量のアイテムがある場合、読み取り容量ユニットを大量に消費する
- 可能な限り `flushGroup()` でグループ単位の削除を使用すること
- 本番環境では IAM ポリシーで `dynamodb:Scan` を除外することを推奨

### コスト最適化

| 操作 | 消費ユニット | 最適化のヒント |
|------|------------|-------------|
| `get()` | 0.5 RCU（結果整合性）/ 1 RCU（強整合性） | 現在は強整合性を使用 |
| `set()` | 1 WCU（1KB 未満） | 大きな値はサイズに比例 |
| `flush(prefix)` | Query RCU + Delete WCU | グループサイズに比例 |
| `flush('')` | Scan RCU + Delete WCU | **テーブル全体に比例** |

> [!TIP]
> DynamoDB の無料利用枠には月 25 GB のストレージと 25 WCU / 25 RCU（プロビジョンド）が含まれます。小規模サイトでは無料枠内で運用可能です。

## セキュリティ

### 保存時暗号化

DynamoDB は **保存時暗号化がデフォルトで有効**です。以下の暗号化オプションから選択できます:

| オプション | キー管理 | コスト |
|-----------|---------|--------|
| AWS マネージドキー（デフォルト） | AWS が管理 | 無料 |
| カスタマーマネージドキー (CMK) | 自分で KMS キーを管理 | KMS 料金 |
| AWS 所有キー | AWS が管理（最もシンプル） | 無料 |

### 転送中暗号化

DynamoDB への接続は HTTPS（TLS）がデフォルトです。`async-aws/dynamo-db` は常に HTTPS を使用します。

### VPC エンドポイント

本番環境では DynamoDB の VPC エンドポイント（Gateway タイプ）の使用を推奨します。

```hcl
resource "aws_vpc_endpoint" "dynamodb" {
  vpc_id       = aws_vpc.main.id
  service_name = "com.amazonaws.ap-northeast-1.dynamodb"

  route_table_ids = [aws_route_table.private.id]
}
```

VPC エンドポイントにより:
- トラフィックが AWS ネットワーク内に留まる
- NAT Gateway のデータ転送コストを削減
- パブリックインターネットを経由しない

## トラブルシューティング

### 接続エラー

```
AdapterException: ... cURL error ...
```

- エンドポイント URL を確認（DynamoDB Local の場合は `http://localhost:8000`）
- AWS クレデンシャルが利用可能であることを確認
- リージョンが正しいか確認
- VPC エンドポイントまたは NAT Gateway が正しく設定されているか確認

### 権限不足

```
AccessDeniedException: User: arn:aws:iam::123456789012:user/xxx is not authorized to perform: dynamodb:GetItem on resource: arn:aws:dynamodb:...
```

以下を確認:

1. IAM ポリシーが正しくアタッチされているか
2. テーブル ARN が一致しているか（リージョン、アカウント ID、テーブル名）
3. 条件キー（`dynamodb:LeadingKeys` 等）で制限されていないか
4. SCP（サービスコントロールポリシー）でブロックされていないか

### スロットリング

```
ProvisionedThroughputExceededException: The level of configured provisioned throughput for the table was exceeded.
```

対処方法:

1. **プロビジョンドモードの場合**: RCU / WCU を増加、または Auto Scaling を設定
2. **オンデマンドモードへの切り替え**を検討
3. **DAX の導入**を検討（読み取りスロットリングの場合）
4. **エクスポネンシャルバックオフ**: `async-aws` が自動的にリトライを実施

### テーブルが見つからない

```
ResourceNotFoundException: Requested resource not found: Table: cache not found
```

- テーブル名が DSN / オプションで正しく指定されているか確認
- リージョンが正しいか確認（テーブルはリージョン固有）
- テーブルが `ACTIVE` 状態であることを確認

### 大きなアイテムのエラー

```
ValidationException: Item size has exceeded the maximum allowed size
```

DynamoDB のアイテムサイズ上限は **400 KB** です。キャッシュ値がこれを超える場合は:

- キャッシュデータの構造を見直し、不要なデータを削減
- 大きなデータは S3 に保存し、キーのみキャッシュ
- Redis / Valkey への切り替えを検討（最大 512 MB）

## クラス一覧

| クラス | 説明 |
|-------|------|
| `DynamoDbAdapter` | DynamoDB テーブルへの CRUD アダプタ |
| `DynamoDbAdapterFactory` | `dynamodb://` DSN ファクトリ |

## 依存関係

### 必須
- **wppack/cache** — アダプタ基盤（`AdapterInterface`, `AbstractAdapter`, `Dsn`）
- **async-aws/dynamo-db** ^3.0 — DynamoDB クライアント
