# サーバレスアーキテクチャ

## 概要

WpPack は AWS のサーバレスサービスを活用して、WordPress の非同期処理・スケジューリング・メール送信・メディア管理を実現します。Bref を使って PHP を Lambda 上で実行します。

## AsyncAWS 採用理由

AWS SDK として [AsyncAWS](https://async-aws.com/) を採用しています。

- **軽量**: 必要なサービスのパッケージだけをインストール（AWS SDK 全体を含まない）
- **非同期対応**: Promise ベースの非同期 API
- **Symfony コミュニティ**: Symfony エコシステムとの親和性が高い
- **型安全**: PHP 8.x の型システムを活用した厳格な型付け

### パッケージ構成

各プラグイン・コンポーネントが必要な `async-aws/*` パッケージを直接 `require` します。AWS 関連の共通パッケージは作りません。

```json
// wppack/messenger の composer.json
{
    "require": {
        "async-aws/sqs": "^2.0"
    }
}

// wppack/scheduler の composer.json
{
    "require": {
        "async-aws/scheduler": "^1.0"
    }
}
```

## S3 ストレージフロー

ブラウザから S3 に直接アップロードし、サーバーの負荷を回避します。

```
┌──────────┐    Pre-signed URL     ┌──────────┐
│ Browser  │ ───────────────────── │ WordPress│
│          │ ←──────────────────── │ (Lambda) │
└──────────┘                       └──────────┘
     │
     │  Direct upload (PUT)
     ▼
┌──────────┐    S3 Event           ┌──────────┐
│  AWS S3  │ ───────────────────── │ AWS SQS  │
│          │                       │          │
└──────────┘                       └──────────┘
                                        │
                                        ▼
                                   ┌──────────┐
                                   │  Lambda  │
                                   │  (Bref)  │
                                   └──────────┘
                                        │
                                        ▼
                                   WpPack Messenger
                                   → Handler 実行
                                   （サムネイル生成等）
```

1. WordPress が Pre-signed URL を生成してブラウザに返す
2. ブラウザが S3 に直接アップロード（サーバーを経由しない）
3. S3 Event が SQS にメッセージを送信
4. Lambda が SQS からメッセージを受信
5. WpPack Messenger がハンドラーを実行（サムネイル生成、メタデータ登録など）

## SES メール送信フロー

`wp_mail()` をフックして SES 経由で送信し、バウンス・苦情を自動処理します。

```
┌──────────┐    wp_mail()          ┌──────────┐
│WordPress │ ───────────────────── │  Mailer  │
│          │                       │Component │
└──────────┘                       └──────────┘
                                        │
                                        │ SesTransport
                                        ▼
                                   ┌──────────┐
                                   │ AWS SES  │
                                   └──────────┘
                                        │
                                        │ Bounce / Complaint
                                        ▼
                                   ┌──────────┐
                                   │ AWS SNS  │
                                   └──────────┘
                                        │
                                        ▼
                                   ┌──────────┐
                                   │ AWS SQS  │
                                   └──────────┘
                                        │
                                        ▼
                                   ┌──────────┐
                                   │  Lambda  │
                                   │  (Bref)  │
                                   └──────────┘
                                        │
                                        ▼
                                   WpPack Messenger
                                   → Handler 実行
                                   （バウンス処理等）
```

1. `wp_mail()` が Mailer コンポーネントにインターセプトされる
2. `SesTransport` が SES API でメールを送信
3. バウンス・苦情が発生すると SNS → SQS → Lambda の流れで通知される
4. WpPack Messenger がハンドラーを実行（バウンスアドレスの記録など）

## EventBridge Scheduler 統合

スケジュール定義を Action Scheduler に保存し、EventBridge Scheduler と同期します。

```
┌──────────────────────────────────────────────────────────┐
│ スケジュール定義                                           │
│                                                          │
│  #[AsSchedule]                                           │
│  class MyScheduleProvider                                │
│      RecurringMessage::cron('@daily', new CleanupMsg())  │
│                                                          │
└──────────────────────────────────────────────────────────┘
            │
            │ EventBridgeSchedulerPlugin が収集
            ▼
┌──────────────────────┐
│ Action Scheduler DB  │  ← 一次データソース
│ （WordPress DB）      │
└──────────────────────┘
            │
            │ リアルタイム同期
            ▼
┌──────────────────────┐
│ EventBridge          │  ← 分単位の正確な時刻管理
│ Scheduler            │
└──────────────────────┘
            │
            │ 指定時刻にトリガー
            ▼
┌──────────────────────┐
│ AWS SQS              │
└──────────────────────┘
            │
            ▼
┌──────────────────────┐
│ Lambda (Bref)        │
│ → Messenger          │
│ → Handler 実行       │
└──────────────────────┘
```

1. `#[AsSchedule]` で定義されたスケジュールを EventBridgeSchedulerPlugin が収集
2. Action Scheduler DB に一次データとして保存
3. EventBridge Scheduler にリアルタイム同期（作成・更新・削除）
4. 指定時刻に EventBridge が SQS にメッセージを送信
5. Lambda が SQS を消費し、WpPack Messenger がハンドラーを実行

### Action Scheduler が一次データソースである理由

- WordPress の管理画面から確認・操作できる
- EventBridge が利用できない環境でも Action Scheduler 単体で動作可能
- 障害時の復旧が容易（WordPress DB にデータが残る）

## Bref (PHP on Lambda) 統合

[Bref](https://bref.sh/) を使って PHP を AWS Lambda 上で実行します。

### Lambda の役割

- **SQS Consumer**: SQS メッセージを受信し、WpPack Messenger でハンドラーを実行
- **WordPress 環境**: Lambda 上で WordPress をブートストラップし、プラグイン・テーマの機能を利用可能

### Lambda ハンドラー

```php
// SQS イベントを受信し、Messenger でディスパッチ
return function (array $event, Context $context): void {
    // WordPress ブートストラップ
    require '/var/task/wp-load.php';

    // SQS メッセージを処理
    foreach ($event['Records'] as $record) {
        $messageBus->handleFromSqs($record);
    }
};
```

## 開発環境でのローカル実行

本番では AWS サービスを使いますが、開発環境ではローカルで動作します。

| 本番 | 開発環境 |
|---|---|
| SQS → Lambda | 同期実行（直接ハンドラーを呼び出し） |
| EventBridge Scheduler | Action Scheduler のみ（WP-Cron で実行） |
| S3 | ローカルファイルシステム（WordPress デフォルト） |
| SES | WordPress デフォルトの `wp_mail()` |

環境変数で切り替えます:

```bash
# 開発環境
WPPACK_USE_EVENTBRIDGE=false

# 本番環境
WPPACK_USE_EVENTBRIDGE=true
```

開発環境では:
- メッセージは同期的に処理される（SQS を経由しない）
- スケジュールは Action Scheduler + WP-Cron で実行される
- AWS の認証情報は不要
