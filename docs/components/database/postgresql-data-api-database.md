# Aurora PostgreSQL Data API Database Bridge

**パッケージ:** `wppack/postgresql-data-api-database`
**名前空間:** `WPPack\Component\Database\Bridge\PostgreSQLDataApi\`
**Category:** Data

Aurora PostgreSQL Data API (HTTP) 経由の接続 Bridge。ネイティブ PostgreSQL プロトコルではなく AWS RDS Data API を使うため、サーバーレス (Lambda / Fargate) から VPC 内の Aurora PostgreSQL にアクセスする際のネットワーク設定が不要になります。

## インストール

```bash
composer require wppack/postgresql-data-api-database
composer require async-aws/rds-data-service
```

## DSN 設定

```php
// wp-config.php

define('DATABASE_DSN',
    'pgsql+dataapi://arn:aws:rds:us-east-1:123456789012:cluster:my-cluster/mydb'
    . '?secret_arn=arn:aws:secretsmanager:us-east-1:123456789012:secret:db-creds-XXX'
    . '&region=us-east-1'
);
```

### DSN 書式

```
pgsql+dataapi://<cluster-arn>/<database>?secret_arn=<arn>[&region=<region>]
```

| 場所 | 値 |
|------|-----|
| host | Cluster ARN |
| path | database name |
| `secret_arn` | Secrets Manager ARN (必須) |
| `region` | AWS region (ARN から自動抽出可) |

> [!IMPORTANT]
> `secret_arn` 省略時は `ConnectionException`。

## 制限事項 (ネイティブ PostgreSQL bridge との違い)

| 機能 | `pgsql://` | `pgsql+dataapi://` |
|------|-----------|---------------------|
| `search_path` | ✅ connection-level | ⚠️ transaction-scope のみ |
| `lastInsertId()` | ✅ `lastval()` | ❌ 0 を返す |
| Cursor / streaming | ✅ | ❌ 1 MB cap |
| Persistent connection | ✅ `pg_pconnect` | ❌ stateless HTTP |

### search_path の注意

Data API は HTTP-stateless のため `SET search_path` はコール間で持続しません。schema スコープが必要な場合は、明示的な `transactionId` を伴うトランザクション内で `SET LOCAL search_path TO ...` を実行してください。

### lastInsertId

`SELECT lastval()` は HTTP コール間で未定義のため、このドライバは例外を潰して 0 を返します。シーケンス値が必要な場合は `INSERT ... RETURNING id` を使用してください。

## 共通特徴 (MySQL Data API と同じ)

- **1 MB response cap** (pagination なし) — 大 SELECT は `LIMIT` / keyset pagination で分割
- **Error classification**: Throttling / Timeout / ExpiredToken が専用例外にマップ (`DataApiErrorClassificationTest` 参照)
- **5000 行超で logger warning**

## 関連ドキュメント

- [Database component](./README.md)
- [MySQL Data API Bridge](./mysql-data-api-database.md)
- [Aurora DSQL Bridge](./aurora-dsql-database.md)
- [src/Component/Database/Bridge/PostgreSQLDataApi/README.md](../../../src/Component/Database/Bridge/PostgreSQLDataApi/README.md)

## License

MIT
