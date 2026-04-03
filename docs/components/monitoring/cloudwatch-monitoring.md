# CloudWatch Monitoring ブリッジ

AWS CloudWatch GetMetricData API を使用してメトリクスを取得する `MetricProviderInterface` 実装。

## パッケージ情報

| 項目 | 値 |
|------|-----|
| パッケージ | `wppack/cloudwatch-monitoring` |
| ブリッジ名 | `cloudwatch` |
| 依存 | `wppack/monitoring`, `async-aws/cloud-watch` |

## 概要

`CloudWatchMetricProvider` は AWS CloudWatch の `GetMetricData` API を呼び出し、メトリクスデータを `MetricResult` オブジェクトとして返します。プロバイダごとの認証情報（Access Key / Secret Key）または IAM ロールフォールバックに対応し、リージョンごとにクライアントをキャッシュします。

## 適応的な期間解像度

時間範囲に応じてデータポイントの粒度を自動調整します。

| 時間範囲 | Period |
|---------|--------|
| ≤6 時間 | 60 秒（1 分） |
| ≤1 日 | 300 秒（5 分） |
| ≤3 日 | 900 秒（15 分） |
| >3 日 | 3600 秒（1 時間） |

## IAM ポリシー

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": "cloudwatch:GetMetricData",
      "Resource": "*"
    }
  ]
}
```

`cloudwatch:GetMetricData` はリソースレベルの制限をサポートしないため、Resource は `"*"` にする必要があります。

## プロバイダ設定

| フィールド | 型 | 説明 |
|-----------|-----|------|
| `region` | string | AWS リージョン（デフォルト: `us-east-1`） |
| `accessKeyId` | string (sensitive) | AWS アクセスキー（省略時は IAM ロール） |
| `secretAccessKey` | string (sensitive) | AWS シークレットキー（省略時は IAM ロール） |

## メトリクス定義

| フィールド | 説明 |
|-----------|------|
| `namespace` | CloudWatch 名前空間（例: `AWS/RDS`, `AWS/EC2`） |
| `metricName` | メトリクス名 |
| `dimensions` | ディメンション Key-Value ペア |
| `periodSeconds` | データポイント期間（時間範囲に応じて自動調整） |
| `stat` | 統計タイプ（Sum, Average, Maximum, Minimum） |
