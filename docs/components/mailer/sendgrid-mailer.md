# SendGridMailer コンポーネント

**パッケージ:** `wppack/sendgrid-mailer`
**名前空間:** `WpPack\Component\Mailer\Bridge\SendGrid\`
**レイヤー:** Abstraction

Mailer コンポーネントの SendGrid トランスポート実装。SendGrid v3 Mail Send API および SMTP 経由でのメール送信を提供します。外部 SDK 不要で、API は `wppack/http-client` 経由で呼び出します。

## インストール

```bash
composer require wppack/sendgrid-mailer
```

## DSN 設定

```php
// wp-config.php

// SendGrid v3 API（推奨）
define('MAILER_DSN', 'sendgrid://API_KEY@default');

// SendGrid v3 API（明示的）
define('MAILER_DSN', 'sendgrid+api://API_KEY@default');

// SendGrid SMTP
define('MAILER_DSN', 'sendgrid+smtp://apikey:API_KEY@default');

// SendGrid SMTP SSL（ポート 465）
define('MAILER_DSN', 'sendgrid+smtps://apikey:API_KEY@default');
```

### DSN 一覧

| DSN | トランスポート | 送信方式 |
|-----|-------------|---------|
| `sendgrid://` | SendGridApiTransport | **v3 Mail Send API**: デフォルト（`sendgrid+api://` のエイリアス） |
| `sendgrid+api://` | SendGridApiTransport | **v3 Mail Send API** |
| `sendgrid+smtp://` | SendGridSmtpTransport | **SMTP TLS 接続**（ポート 587） |
| `sendgrid+smtps://` | SendGridSmtpTransport | **SMTP SSL 接続**（ポート 465） |

### DSN 形式

```
# API（推奨）
sendgrid://API_KEY@default
sendgrid+api://API_KEY@default

# SMTP
sendgrid+smtp://apikey:API_KEY@default
sendgrid+smtps://apikey:API_KEY@default
```

- API の場合: `user` = API キー
- SMTP の場合: `user` = `apikey`（固定）、`password` = API キー（SendGrid の仕様）

## 認証方法

### API キー

SendGrid ダッシュボードの Settings > API Keys から取得:

```php
// API 経由
define('MAILER_DSN', 'sendgrid://SG.xxxxxxxxxxxxx@default');

// SMTP 経由
define('MAILER_DSN', 'sendgrid+smtp://apikey:SG.xxxxxxxxxxxxx@default');
```

## トランスポートクラス

### SendGridApiTransport

`AbstractApiTransport` を継承。SendGrid v3 Mail Send API（`POST https://api.sendgrid.com/v3/mail/send`）を `HttpClient` 経由で送信。添付ファイル対応。複数の reply-to アドレスに対応（`reply_to_list` フィールドを使用）。

```php
final class SendGridApiTransport extends AbstractApiTransport
{
    public function __construct(
        private readonly string $apiKey,
        private readonly ?HttpClient $httpClient = null,
    ) {}

    protected function getMailerName(): string { return 'sendgridapi'; }

    protected function doSendApi(PhpMailer $phpMailer): string
    {
        // SendGrid v3 API で送信
    }
}
```

### SendGridSmtpTransport

`SmtpTransport` を継承。SendGrid の SMTP エンドポイント（`smtp.sendgrid.net`）に PHPMailer の SMTP 機能で接続。

```php
final class SendGridSmtpTransport extends SmtpTransport
{
    public function __construct(
        string $apiKey,
        string $encryption = 'tls',
        int $port = 587,
    ) {
        parent::__construct(
            host: 'smtp.sendgrid.net',
            port: $port,
            username: 'apikey',
            password: $apiKey,
            // ...
        );
    }
}
```

### SendGridTransportFactory

DSN から適切な SendGrid トランスポートを生成するファクトリ。

```php
// wppack/sendgrid-mailer がインストールされていれば fromDsn() で自動検出
$transport = Transport::fromDsn('sendgrid://SG.xxxxx@default');
```

## クイックスタート

```php
use WpPack\Component\Mailer\Mailer;
use WpPack\Component\Mailer\Transport\Transport;

// SendGrid トランスポートの初期化（wppack/sendgrid-mailer インストール済みで自動検出）
$transport = Transport::fromDsn(MAILER_DSN);
$mailer = new Mailer($transport);
$mailer->boot();

// wp_mail() が SendGrid 経由で送信される
wp_mail('user@example.com', 'Hello', 'World');
```

## クラス一覧

| クラス | 説明 |
|-------|------|
| `Transport\SendGridApiTransport` | v3 API トランスポート（`sendgrid://`, `sendgrid+api://`） |
| `Transport\SendGridSmtpTransport` | SMTP トランスポート（`sendgrid+smtp://`） |
| `Transport\SendGridTransportFactory` | DSN ファクトリ |

## 依存関係

### 必須
- **wppack/mailer** -- トランスポート基盤（`TransportInterface`, `AbstractApiTransport`, `SmtpTransport`）
- **wppack/http-client** -- HTTP クライアント
