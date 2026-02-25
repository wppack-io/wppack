# AmazonMailerPlugin

WordPress のメール送信を Amazon SES に置き換えるプラグイン。`wppack/amazon-mailer` が提供する SES トランスポートを利用し、`wp_mail()` の自動置き換えとバウンス/苦情通知の処理を行う。

## 概要

AmazonMailerPlugin は `wppack/amazon-mailer` の SES トランスポートを WordPress で実際に使えるようにするプラグインです:

- **wp_mail() 自動置き換え**: プラグイン有効化だけで `wp_mail()` が SES 経由に変更
- **DSN 設定**: `wp-config.php` の `MAILER_DSN` で SES トランスポートを有効化
- **バウンス/苦情処理**: SNS → SQS 経由でバウンスと苦情通知を非同期処理
- **管理画面**: SES 設定ページと送信抑制リストの管理
- **WP-CLI**: Identity 検証とテストメール送信コマンド

## アーキテクチャ

### パッケージ構成

```
wppack/mailer              ← トランスポート基盤（TransportInterface, AbstractTransport, DSN, Factory）
    ↑
wppack/amazon-mailer       ← SES トランスポート実装（SesTransport, SesApiTransport, SesTransportFactory）
    ↑
wppack/amazon-mailer-plugin   ← WordPress 統合（wp_mail 置き換え, 管理画面, バウンス処理）
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
│ BounceHandler                                │
│   → バウンスメールアドレスの記録              │
│   → 管理者通知                               │
│                                              │
│ ComplaintHandler                             │
│   → 苦情メールアドレスの記録                  │
│   → 送信停止処理                             │
│                                              │
└──────────────────────────────────────────────┘
```

## 依存パッケージ

| パッケージ | 用途 |
|-----------|------|
| wppack/amazon-mailer | SES トランスポート（SesTransport, SesApiTransport） |
| wppack/mailer | メール送信基盤（Mailer, TransportInterface） |
| wppack/hook | WordPress フック統合 |

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

## 主要クラス

### WordPress\WpMailOverride

`pre_wp_mail` フィルタで `wp_mail()` をインターセプトし、`wppack/amazon-mailer` の SES トランスポートで送信する。

```php
namespace WpPack\Plugin\AmazonMailerPlugin\WordPress;

final class WpMailOverride
{
    public function register(): void;

    /**
     * @param null|bool $return null で wp_mail() のデフォルト処理を続行
     * @param array<string, mixed> $atts wp_mail() の引数
     */
    public function filter(null|bool $return, array $atts): ?bool;
}
```

### Message\SesBounceMessage / SesComplaintMessage

SES から SNS → SQS 経由で配信されるバウンスおよび苦情通知メッセージ。

```php
namespace WpPack\Plugin\AmazonMailerPlugin\Message;

final class SesBounceMessage
{
    public function __construct(
        public readonly string $messageId,
        public readonly string $bounceType,        // 'Permanent' | 'Transient'
        public readonly string $bounceSubType,
        /** @var list<string> */
        public readonly array $bouncedRecipients,
        public readonly \DateTimeImmutable $timestamp,
    ) {}
}

final class SesComplaintMessage
{
    public function __construct(
        public readonly string $messageId,
        public readonly string $complaintFeedbackType,
        /** @var list<string> */
        public readonly array $complainedRecipients,
        public readonly \DateTimeImmutable $timestamp,
    ) {}
}
```

### Handler\BounceHandler

バウンス通知を処理し、バウンスしたメールアドレスを記録する。Permanent バウンスの場合は送信抑制リストに追加する。

```php
namespace WpPack\Plugin\AmazonMailerPlugin\Handler;

use WpPack\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class BounceHandler
{
    public function __invoke(SesBounceMessage $message): void
    {
        // 1. バウンス情報をログに記録
        // 2. Permanent バウンスの場合、送信抑制リストに追加
        // 3. 管理者に通知（設定に応じて）
    }
}
```

### Handler\ComplaintHandler

苦情通知を処理し、苦情を申し立てたメールアドレスへの送信を停止する。

```php
namespace WpPack\Plugin\AmazonMailerPlugin\Handler;

use WpPack\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ComplaintHandler
{
    public function __invoke(SesComplaintMessage $message): void
    {
        // 1. 苦情情報をログに記録
        // 2. 送信抑制リストに追加
        // 3. 管理者に通知
    }
}
```

### Admin\SettingsPage

管理画面の SES 設定ページ。送信元アドレス、Configuration Set、送信抑制リストを管理する。

```php
namespace WpPack\Plugin\AmazonMailerPlugin\Admin;

final class SettingsPage
{
    public function register(): void;
    public function render(): void;
}
```

### Command\VerifyIdentityCommand

SES のメールアドレスまたはドメインの検証状態を確認・開始する WP-CLI コマンド。

```bash
# 検証状態を確認
wp wppack ses verify-identity --email=noreply@example.com

# ドメイン検証を開始
wp wppack ses verify-identity --domain=example.com

# 全 Identity の状態を一覧表示
wp wppack ses verify-identity --list
```

### Command\TestEmailCommand

テストメールを送信して SES の設定を確認する WP-CLI コマンド。

```bash
# テストメール送信
wp wppack ses test-email --to=test@example.com

# 件名と本文を指定
wp wppack ses test-email --to=test@example.com --subject="Test" --body="Hello"
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

```php
// カスタムバウンスハンドラの例
use WpPack\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CustomBounceHandler
{
    public function __invoke(SesBounceMessage $message): void
    {
        foreach ($message->bouncedRecipients as $recipient) {
            error_log(sprintf('Bounce: %s (%s)', $recipient, $message->bounceType));
        }
    }
}
```
