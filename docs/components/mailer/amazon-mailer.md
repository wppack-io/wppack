# AmazonMailer コンポーネント

**パッケージ:** `wppack/amazon-mailer`
**名前空間:** `WPPack\Component\Mailer\Bridge\Amazon\`
**レイヤー:** Abstraction

Mailer コンポーネントの Amazon SES トランスポート実装。Symfony の `symfony/amazon-mailer` と同じ DSN パターンで、SES を使ったメール送信を提供します。

## インストール

```bash
composer require wppack/amazon-mailer
```

## DSN 設定

```php
// wp-config.php

// SES 構造化 API（デフォルト、推奨）
define('MAILER_DSN', 'ses://ACCESS_KEY:SECRET_KEY@default?region=ap-northeast-1');

// SES 構造化 API（明示的）
define('MAILER_DSN', 'ses+api://ACCESS_KEY:SECRET_KEY@default?region=ap-northeast-1');

// SES Raw MIME（PHPMailer が構築した MIME メッセージを送信）
define('MAILER_DSN', 'ses+https://ACCESS_KEY:SECRET_KEY@default?region=ap-northeast-1');

// SES SMTP（async-aws SDK 不要）
define('MAILER_DSN', 'ses+smtp://SMTP_USER:SMTP_PASS@default?region=ap-northeast-1');

// Configuration Set を指定
define('MAILER_DSN', 'ses://KEY:SECRET@default?region=ap-northeast-1&configuration_set=my-config');
```

### DSN 一覧

| DSN | トランスポート | 送信方式 |
|-----|-------------|---------|
| `ses://` | SesApiTransport | **構造化 API**: デフォルト（`ses+api://` のエイリアス） |
| `ses+api://` | SesApiTransport | **構造化 API**: From/To/Subject/Body/Attachments を個別フィールドで SES に渡す |
| `ses+https://` | SesHttpTransport | **Raw MIME**: PHPMailer が構築した MIME メッセージ全体をそのまま SES に渡す |
| `ses+smtp://` | SesSmtpTransport | **SMTP 接続**: SES SMTP エンドポイントに接続 |
| `ses+smtps://` | SesSmtpTransport | `ses+smtp://` の SSL 版（ポート 465） |

### 構造化 API vs Raw MIME

| | 構造化 API（`ses://`, `ses+api://`） | Raw MIME（`ses+https://`） |
|---|---|---|
| MIME 構築 | SES サーバー側 | PHPMailer（クライアント側） |
| 添付ファイル | 対応（Simple Message Attachments） | 対応 |
| インライン画像 | 対応（ContentId で指定） | 対応 |
| カスタムヘッダー | 一部のみ | すべて対応 |
| 用途 | 一般的なメール送信（推奨） | カスタムヘッダー等が必要な場合 |

### DSN オプション

| オプション | デフォルト | 説明 |
|-----------|-----------|------|
| `region` | `us-east-1` | AWS リージョン |
| `session_token` | -- | STS 一時認証トークン |
| `configuration_set` | -- | SES Configuration Set 名 |
| `ping_threshold` | -- | SMTP keepalive 間隔（`ses+smtp` のみ） |

## 認証方法

### IAM ロール（推奨）

DSN に `user:password` を指定しない場合、async-aws が自動的に以下の順序で認証情報を検出します:

1. 環境変数（`AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`）
2. AWS 認証ファイル（`~/.aws/credentials`）
3. ECS タスクロール
4. EC2 Instance Profile

```php
// IAM ロール使用（DSN にクレデンシャルなし）
define('MAILER_DSN', 'ses://default?region=ap-northeast-1');
```

### アクセスキー

```php
define('MAILER_DSN', 'ses://ACCESS_KEY_ID:SECRET_ACCESS_KEY@default?region=ap-northeast-1');
```

### STS 一時認証

```php
define('MAILER_DSN', 'ses://KEY:SECRET@default?region=ap-northeast-1&session_token=TOKEN');
```

## トランスポートクラス

### SesApiTransport

`AbstractApiTransport` を継承。PHPMailer のプロパティから構造化リクエストを構築して SES `Content.Simple` API で送信。添付ファイルは Simple Message の `Attachments` フィールドで対応。

```php
final class SesApiTransport extends AbstractApiTransport
{
    protected function getMailerName(): string { return 'sesapi'; }

    protected function doSendApi(PhpMailer $phpMailer): string
    {
        // SES Content.Simple API + Attachments で送信
    }
}
```

### SesHttpTransport

`AbstractTransport` を継承。PHPMailer が構築した MIME メッセージ全体を SES `Content.Raw` API で送信。

```php
final class SesHttpTransport extends AbstractTransport
{
    public function __construct(
        private readonly SesClient $sesClient,
        private readonly ?string $configurationSet = null,
    ) {}

    protected function getMailerName(): string { return 'ses+https'; }

    protected function doSend(PhpMailer $phpMailer): void
    {
        $mime = $phpMailer->getSentMIMEMessage();
        // SES Content.Raw API で送信
    }
}
```

### SesSmtpTransport

`SmtpTransport` を継承。SES の SMTP エンドポイント（`email-smtp.{region}.amazonaws.com`）に PHPMailer の SMTP 機能で接続。async-aws SDK 不要。

```php
final class SesSmtpTransport extends SmtpTransport
{
    public function __construct(
        string $username,
        string $password,
        string $region = 'us-east-1',
        string $encryption = 'tls',
        int $port = 587,
    ) {
        parent::__construct(
            host: sprintf('email-smtp.%s.amazonaws.com', $region),
            // ...
        );
    }
}
```

### SesTransportFactory

DSN から適切な SES トランスポートを生成するファクトリ。

```php
// wppack/amazon-mailer がインストールされていれば fromDsn() で自動検出
$transport = Transport::fromDsn('ses+api://KEY:SECRET@default?region=ap-northeast-1');
```

## AWS IAM ポリシー

SES メール送信に必要な最小権限:

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "ses:SendEmail",
                "ses:SendRawEmail"
            ],
            "Resource": "*"
        }
    ]
}
```

特定のメールアドレスに制限する場合:

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "ses:SendEmail",
                "ses:SendRawEmail"
            ],
            "Resource": "arn:aws:ses:ap-northeast-1:123456789012:identity/example.com"
        }
    ]
}
```

## クイックスタート

```php
use WPPack\Component\Mailer\Mailer;
use WPPack\Component\Mailer\Email;
use WPPack\Component\Mailer\Transport\Transport;

// SES トランスポートの初期化（wppack/amazon-mailer インストール済みで自動検出）
$transport = Transport::fromDsn(MAILER_DSN);
$mailer = new Mailer($transport);
$mailer->boot();

// wp_mail() が SES 経由で送信される
wp_mail('user@example.com', 'Hello', 'World');

// または Symfony スタイルで送信
$email = (new Email())
    ->from('noreply@example.com')
    ->to('user@example.com')
    ->subject('Welcome')
    ->html('<h1>Welcome!</h1>');

$mailer->send($email);
```

## クラス一覧

| クラス | 説明 |
|-------|------|
| `Transport\SesApiTransport` | 構造化 API トランスポート（`ses://`, `ses+api://`） |
| `Transport\SesHttpTransport` | Raw MIME トランスポート（`ses+https://`） |
| `Transport\SesSmtpTransport` | SMTP トランスポート（`ses+smtp://`） |
| `Transport\SesTransportFactory` | DSN ファクトリ |

## 依存関係

### 必須
- **wppack/mailer** -- トランスポート基盤（`TransportInterface`, `AbstractTransport`, `AbstractApiTransport`, `SmtpTransport`）
- **async-aws/ses ^1.14** -- SES API クライアント（`ses+smtp` / `ses+smtps` では不要）
