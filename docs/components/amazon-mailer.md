# AmazonMailer コンポーネント

**パッケージ:** `wppack/amazon-mailer`
**名前空間:** `WpPack\Component\Mailer\Bridge\Amazon\`
**レイヤー:** Abstraction

Mailer コンポーネントの Amazon SES トランスポート実装。Symfony の `symfony/amazon-mailer` と同じパターンで、`AbstractTransport` / `AbstractApiTransport` を継承した SES メール送信トランスポートを提供します。

## インストール

```bash
composer require wppack/amazon-mailer
```

## DSN 設定

```php
// wp-config.php

// SES API（構造化リクエスト、推奨）
define('MAILER_DSN', 'ses+api://ACCESS_KEY:SECRET_KEY@default?region=ap-northeast-1');

// SES Raw（MIME 送信）
define('MAILER_DSN', 'ses://ACCESS_KEY:SECRET_KEY@default?region=ap-northeast-1');

// Configuration Set を指定
define('MAILER_DSN', 'ses+api://KEY:SECRET@default?region=ap-northeast-1&configuration_set=my-config');
```

| DSN | トランスポート | 説明 |
|-----|-----------|-------------|
| `ses://KEY:SECRET@default?region=REGION` | `SesTransport` | SendRawEmail API（MIME 送信） |
| `ses+api://KEY:SECRET@default?region=REGION` | `SesApiTransport` | SendEmail API（構造化送信） |

## 主要クラス

### Transport\SesTransportFactory

Mailer コンポーネントの `TransportFactoryInterface` を実装し、DSN から SES トランスポートを生成するファクトリ。

```php
namespace WpPack\Component\Mailer\Bridge\Amazon\Transport;

use WpPack\Component\Mailer\Transport\TransportFactoryInterface;
use WpPack\Component\Mailer\Transport\TransportInterface;
use WpPack\Component\Mailer\Transport\Dsn;

final class SesTransportFactory implements TransportFactoryInterface
{
    public function create(Dsn $dsn): TransportInterface
    {
        if ('ses+api' === $dsn->getScheme()) {
            return new SesApiTransport(
                sesClient: $this->createSesClient($dsn),
                configurationSet: $dsn->getOption('configuration_set'),
            );
        }

        return new SesTransport(
            sesClient: $this->createSesClient($dsn),
            configurationSet: $dsn->getOption('configuration_set'),
        );
    }

    public function supports(Dsn $dsn): bool
    {
        return in_array($dsn->getScheme(), ['ses', 'ses+api'], true);
    }
}
```

### Transport\SesTransport

Mailer コンポーネントの `AbstractTransport` を継承し、AsyncAWS SES の `SendRawEmail` API でメールを送信する。MIME メッセージをそのまま送信するため、添付ファイルや HTML メールに完全対応する。

```php
namespace WpPack\Component\Mailer\Bridge\Amazon\Transport;

use WpPack\Component\Mailer\Transport\AbstractTransport;
use WpPack\Component\Mailer\SentMessage;
use AsyncAws\Ses\SesClient;

final class SesTransport extends AbstractTransport
{
    public function __construct(
        private readonly SesClient $sesClient,
        private readonly ?string $configurationSet = null,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void;

    public function __toString(): string
    {
        return 'ses://';
    }
}
```

### Transport\SesApiTransport

Mailer コンポーネントの `AbstractApiTransport` を継承し、SES v2 の Structured API（`SendEmail`）でメールを送信する。JSON ベースの構造化リクエストを使用し、シンプルなテキスト/HTML メールに適している。

```php
namespace WpPack\Component\Mailer\Bridge\Amazon\Transport;

use WpPack\Component\Mailer\Transport\AbstractApiTransport;
use WpPack\Component\Mailer\SentMessage;
use WpPack\Component\Mailer\Email;
use WpPack\Component\Mailer\Envelope;

final class SesApiTransport extends AbstractApiTransport
{
    public function __construct(
        private readonly SesClient $sesClient,
        private readonly ?string $configurationSet = null,
    ) {
        parent::__construct();
    }

    protected function doSendApi(
        SentMessage $sentMessage,
        Email $email,
        Envelope $envelope,
    ): ResponseInterface;

    public function __toString(): string
    {
        return 'ses+api://';
    }
}
```

## クラス一覧

| クラス | 説明 |
|-------|------|
| `Transport\SesTransportFactory` | DSN ファクトリ |
| `Transport\SesTransport` | SendRawEmail トランスポート |
| `Transport\SesApiTransport` | SendEmail API トランスポート |

## 依存関係

### 必須
- **Mailer コンポーネント** — トランスポート基盤（`TransportInterface`, `AbstractTransport`, `AbstractApiTransport`）
- **async-aws/ses** — SES API クライアント
