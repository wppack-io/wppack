# Aurora MySQL Data API Database Bridge

**パッケージ:** `wppack/mysql-data-api-database`
**名前空間:** `WPPack\Component\Database\Bridge\MySQLDataApi\`
**Category:** Data

Aurora MySQL Data API (HTTP ベース) 経由で DB 接続する Bridge。ネイティブ MySQL プロトコルではなく AWS RDS Data API を使うため、サーバーレス環境 (Lambda / Fargate) から VPC 内の Aurora に接続する際の PrivateLink / NAT Gateway コストを回避できます。

## インストール

```bash
composer require wppack/mysql-data-api-database
```

AWS SDK 依存:

```bash
composer require async-aws/rds-data-service
```

## DSN 設定

```php
// wp-config.php

define('DATABASE_DSN',
    'mysql+dataapi://arn:aws:rds:us-east-1:123456789012:cluster:my-cluster/mydb'
    . '?secret_arn=arn:aws:secretsmanager:us-east-1:123456789012:secret:db-creds-XXX'
    . '&region=us-east-1'
);
```

### DSN 書式

```
mysql+dataapi://<cluster-arn>/<database>?secret_arn=<arn>[&region=<region>]
```

必須パラメータ:

| 場所 | 値 | 説明 |
|------|-----|------|
| host | Cluster ARN | `arn:aws:rds:{region}:{account}:cluster:{name}` |
| path | database name | Aurora MySQL の database 名 |
| `secret_arn` | Secrets Manager ARN | DB 認証情報を保存した Secrets Manager シークレット |
| `region` | AWS region | ARN から自動抽出されるが明示も可 |

> [!IMPORTANT]
> `secret_arn` 省略時は `ConnectionException` で即 fail します (silent fallback は認証情報漏洩につながるため)。

## 特徴

- **HTTP ベース**: TCP/3306 や PrivateLink が不要。IAM 認証付き HTTPS のみ
- **Stateless**: 接続プール管理不要、Lambda cold start にも強い
- **Query translation なし**: Aurora MySQL は MySQL 互換なので `NullQueryTranslator` がパススルー

## 制限 / 注意点

- **1 MB response cap**: Data API は 1 レスポンスあたり約 1 MB が上限 (pagination なし)。大きな SELECT は `LIMIT` / keyset pagination で分割する。5000 行超のレスポンスは logger warning
- **Error classification**: AWS exception は `DriverThrottledException` / `DriverTimeoutException` / `CredentialsExpiredException` にマップされる (`DataApiErrorClassificationTest` 参照)
- **Transaction**: Data API のトランザクション API 経由で透過対応

## 関連ドキュメント

- [Database component](./README.md)
- [src/Component/Database/Bridge/MySQLDataApi/README.md](../../../src/Component/Database/Bridge/MySQLDataApi/README.md) — 実装詳細
- [PostgreSQL Data API Bridge](./postgresql-data-api-database.md)

## License

MIT
