# AmazonMailerPlugin

WordPress のメール送信を Amazon SES に置き換えるプラグイン。`wppack/amazon-mailer` が提供する SES トランスポートを利用し、`wp_mail()` の自動置き換えとバウンス/苦情通知の処理を行う。

## 概要

AmazonMailerPlugin は `wppack/amazon-mailer` の SES トランスポートを WordPress で実際に使えるようにするプラグインです:

- **wp_mail() 自動置き換え**: プラグイン有効化だけで `wp_mail()` が SES 経由に変更
- **DSN 設定**: `wp-config.php` の `MAILER_DSN` で SES トランスポートを有効化
- **バウンス/苦情処理**: SNS → SQS 経由でバウンスと苦情通知を非同期処理
- **送信抑制リスト**: Permanent バウンスと苦情を自動記録

## アーキテクチャ

### パッケージ構成

```
wppack/mailer              ← トランスポート基盤（Mailer, TransportInterface, DSN, Factory）
    ↑
wppack/amazon-mailer       ← SES トランスポート実装（SesApiTransport, SesTransportFactory）
    ↑
wppack/amazon-mailer-plugin   ← WordPress 統合（wp_mail 置き換え, バウンス処理, DI）
```

### レイヤー構成

```
src/Plugin/AmazonMailerPlugin/
├── amazon-mailer-plugin.php                     ← Bootstrap（Kernel::registerPlugin）
├── src/
│   ├── AmazonMailerPlugin.php                   ← PluginInterface 実装
│   ├── Configuration/
│   │   └── AmazonMailerConfiguration.php        ← 設定 VO（MAILER_DSN）
│   ├── DependencyInjection/
│   │   └── AmazonMailerPluginServiceProvider.php ← サービス登録
│   ├── Message/
│   │   ├── SesBounceMessage.php                 ← バウンス DTO
│   │   ├── SesComplaintMessage.php              ← 苦情 DTO
│   │   └── SesNotificationNormalizer.php        ← SNS JSON パーサー
│   └── Handler/
│       ├── BounceHandler.php                    ← バウンス処理
│       └── ComplaintHandler.php                 ← 苦情処理
└── tests/
```

### 送信フロー

```
┌─ メール送信 ────────────────────────────────┐
│                                              │
│  wp_mail() / $mailer->send()                 │
│    → WpPack\Component\Mailer\Mailer          │
│    → Transport::fromDsn(MAILER_DSN)          │
│      → SesTransportFactory::create()         │
│        → SesApiTransport / SesTransport      │
│          → AsyncAWS SES API                  │
│                                              │
└──────────────────────────────────────────────┘
```

`Mailer::boot()` が `wp_mail` フィルタを登録し、WordPress のグローバル `$phpmailer` を WpPack の `PhpMailer`（SES トランスポート付き）に差し替えます。以降の `wp_mail()` 呼び出しはすべて SES 経由で送信されます。

### バウンス/苦情通知フロー

```
┌─ SES 通知 ──────────────────────────────────┐
│                                              │
│  SES → SNS → SQS                            │
│                                              │
└──────────────────────────────────────────────┘
            ↓ WpPack\Component\Messenger
┌─ 通知処理 ──────────────────────────────────┐
│ Lambda (Bref WordPress)                      │
│                                              │
│ SesNotificationNormalizer                    │
│   → SNS JSON → SesBounceMessage / ...        │
│                                              │
│ BounceHandler                                │
│   → バウンスメールアドレスの記録              │
│   → Permanent バウンスを送信抑制リストに追加  │
│                                              │
│ ComplaintHandler                             │
│   → 苦情メールアドレスの記録                  │
│   → 送信抑制リストに追加                      │
│                                              │
└──────────────────────────────────────────────┘
```

## 依存パッケージ

| パッケージ | 用途 |
|-----------|------|
| wppack/amazon-mailer | SES トランスポート（SesApiTransport, SesTransportFactory） |
| wppack/mailer | メール送信基盤（Mailer, TransportInterface） |
| wppack/dependency-injection | DI コンテナ |
| wppack/kernel | プラグインブートストラップ（PluginInterface） |
| wppack/hook | WordPress フック統合 |
| wppack/option | WordPress options ラッパー（`OptionManager`、送信抑制リスト保存） |
| wppack/messenger | メッセージバス（バウンス/苦情ハンドラ） |

## 名前空間

```
WpPack\Plugin\AmazonMailerPlugin\
```

## 設定

`wp-config.php` で `MAILER_DSN` を定義して SES を有効にします。

```php
// wp-config.php
define('MAILER_DSN', 'ses+api://ACCESS_KEY:SECRET_KEY@default?region=ap-northeast-1');
```

AWS の認証情報は DSN に含めるか、環境変数で設定します。

```bash
# 環境変数で認証情報を設定する場合（DSN に含めない場合）
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
```

IAM ロール認証を使う場合は、DSN にキーを含めず `default` ホストのみ指定します:

```php
define('MAILER_DSN', 'ses+api://default?region=ap-northeast-1');
```

### 対応 DSN スキーム

| スキーム | トランスポート | 説明 |
|---------|--------------|------|
| `ses`, `ses+api` | SesApiTransport | SES API（SendEmail） |
| `ses+https` | SesHttpTransport | SES v2 API（SendRawEmail） |
| `ses+smtp`, `ses+smtps` | SesSmtpTransport | SES SMTP |

## 主要クラス

### AmazonMailerPlugin

`PluginInterface` 実装。`Kernel::registerPlugin()` で登録される。

```php
namespace WpPack\Plugin\AmazonMailerPlugin;

final class AmazonMailerPlugin extends AbstractPlugin
{
    public function register(ContainerBuilder $builder): void;
    public function getCompilerPasses(): array;  // RegisterHookSubscribersPass, RegisterTransportFactoriesPass
    public function boot(Container $container): void;  // Mailer::boot() を呼ぶ
    public function onActivate(): void;
    public function onDeactivate(): void;
}
```

`boot()` で `Container` から `Mailer` を取得して `boot()` を呼び、`wp_mail` フィルタを登録します。

### Configuration\AmazonMailerConfiguration

設定 VO。`MAILER_DSN` を PHP 定数または環境変数から読み込みます。

```php
namespace WpPack\Plugin\AmazonMailerPlugin\Configuration;

final readonly class AmazonMailerConfiguration
{
    public function __construct(public string $dsn);

    public static function fromEnvironment(): self;  // MAILER_DSN 定数 or 環境変数
}
```

### DependencyInjection\AmazonMailerPluginServiceProvider

DI サービスプロバイダ。以下のサービスを登録します:

- `AmazonMailerConfiguration` — 設定 VO（fromEnvironment ファクトリ）
- `SesTransportFactory` — SES トランスポートファクトリ（`mailer.transport_factory` タグ）
- `NativeTransportFactory` — ネイティブトランスポートファクトリ（フォールバック）
- `Transport` — トランスポートルーター（RegisterTransportFactoriesPass でファクトリ注入）
- `TransportInterface` — DSN から解決されたトランスポート
- `Mailer` — メーラー
- `SesNotificationNormalizer` — SNS JSON パーサー
- `OptionManager` — WordPress options ラッパー
- `SuppressionList` — 送信抑制リスト（`OptionManager` を注入）
- `BounceHandler` / `ComplaintHandler` — メッセージハンドラ（`SuppressionList` を注入、`messenger.message_handler` タグ）

### Message\SesBounceMessage / SesComplaintMessage

SES から SNS → SQS 経由で配信されるバウンスおよび苦情通知メッセージ DTO。

```php
namespace WpPack\Plugin\AmazonMailerPlugin\Message;

final readonly class SesBounceMessage
{
    public function __construct(
        public string $messageId,
        public string $bounceType,        // 'Permanent' | 'Transient'
        public string $bounceSubType,
        /** @var list<string> */
        public array $bouncedRecipients,
        public \DateTimeImmutable $timestamp,
    ) {}
}

final readonly class SesComplaintMessage
{
    public function __construct(
        public string $messageId,
        public string $complaintFeedbackType,
        /** @var list<string> */
        public array $complainedRecipients,
        public \DateTimeImmutable $timestamp,
    ) {}
}
```

### Message\SesNotificationNormalizer

SES SNS 通知 JSON をメッセージオブジェクトに変換します。

```php
namespace WpPack\Plugin\AmazonMailerPlugin\Message;

final readonly class SesNotificationNormalizer
{
    /** @return list<SesBounceMessage|SesComplaintMessage> */
    public function normalize(array $notification): array;
}
```

入力フォーマット（SES SNS 通知）:

```json
{
  "notificationType": "Bounce",
  "mail": { "messageId": "..." },
  "bounce": {
    "bounceType": "Permanent",
    "bounceSubType": "General",
    "bouncedRecipients": [{ "emailAddress": "..." }],
    "timestamp": "2024-01-15T10:30:00.000Z"
  }
}
```

### Handler\BounceHandler

バウンス通知を処理し、バウンスしたメールアドレスを記録します。Permanent バウンスの場合は `wp_options` テーブルの送信抑制リスト（`wppack_ses_suppression_list`）に追加します。

```php
namespace WpPack\Plugin\AmazonMailerPlugin\Handler;

#[AsMessageHandler]
final readonly class BounceHandler
{
    public function __construct(
        private SuppressionList $suppressionList,
        private ?LoggerInterface $logger = null,
    );
    public function __invoke(SesBounceMessage $message): void;
}
```

### Handler\ComplaintHandler

苦情通知を処理し、苦情を申し立てたメールアドレスを送信抑制リストに追加します。

```php
namespace WpPack\Plugin\AmazonMailerPlugin\Handler;

#[AsMessageHandler]
final readonly class ComplaintHandler
{
    public function __construct(
        private SuppressionList $suppressionList,
        private ?LoggerInterface $logger = null,
    );
    public function __invoke(SesComplaintMessage $message): void;
}
```

## 使用例

### プラグイン有効化で wp_mail() を自動置き換え

`MAILER_DSN` を設定してプラグインを有効化するだけで完了します。

```php
// wp-config.php
define('MAILER_DSN', 'ses+api://ACCESS_KEY:SECRET_KEY@default?region=ap-northeast-1');
```

```php
// 既存のコードを変更する必要はない
wp_mail('user@example.com', 'Subject', 'Message body');
```

### バウンス/苦情通知の処理

SES の通知設定で SNS トピックを作成し、SQS キューにサブスクライブします。WpPack Messenger が SQS メッセージを受信し、対応するハンドラを自動的に実行します。

#### SES SNS 通知の設定手順

1. **SNS トピック作成**: AWS コンソールで SNS トピック（例: `ses-notifications`）を作成
2. **SES 通知設定**: SES の Configuration Set または Identity 設定で、バウンスと苦情通知を SNS トピックに送信するよう設定
3. **SQS キュー作成**: SQS キュー（例: `ses-notifications-queue`）を作成し、SNS トピックをサブスクライブ
4. **Messenger 設定**: WpPack Messenger の SQS トランスポートでキューを設定

```php
// カスタムバウンスハンドラの例
use Psr\Log\LoggerInterface;
use WpPack\Component\Messenger\Attribute\AsMessageHandler;
use WpPack\Plugin\AmazonMailerPlugin\Message\SesBounceMessage;

#[AsMessageHandler]
final readonly class CustomBounceHandler
{
    public function __construct(private LoggerInterface $logger) {}

    public function __invoke(SesBounceMessage $message): void
    {
        foreach ($message->bouncedRecipients as $recipient) {
            $this->logger->warning('Bounce: {recipient} ({type})', [
                'recipient' => $recipient,
                'type' => $message->bounceType,
            ]);
        }
    }
}
```

## 送信抑制リスト

バウンス/苦情ハンドラは `wp_options` テーブルの `wppack_ses_suppression_list` キーに JSON 配列でメールアドレスを保存します。

- **Permanent バウンス**: アドレスを小文字に正規化して追加
- **苦情**: すべての苦情タイプでアドレスを追加
- **Transient バウンス**: ログに記録するのみ（抑制リストには追加しない）

## 将来の拡張（未実装）

- `Admin\SettingsPage` — 管理画面の SES 設定ページ
- `Command\VerifyIdentityCommand` — Identity 検証 WP-CLI コマンド
- `Command\TestEmailCommand` — テストメール WP-CLI コマンド
