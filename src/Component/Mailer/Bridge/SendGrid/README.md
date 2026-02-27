# SendGrid Mailer

WpPack Mailer の SendGrid トランスポート実装。

## インストール

```bash
composer require wppack/sendgrid-mailer
```

## DSN 設定

```php
// wp-config.php

// API（推奨）
define('MAILER_DSN', 'sendgrid://API_KEY@default');

// SMTP
define('MAILER_DSN', 'sendgrid+smtp://apikey:API_KEY@default');
```

| DSN | トランスポート | 送信方式 |
|-----|-------------|---------|
| `sendgrid://` | SendGridApiTransport | v3 Mail Send API（推奨） |
| `sendgrid+https://` | SendGridApiTransport | `sendgrid://` のエイリアス |
| `sendgrid+api://` | SendGridApiTransport | `sendgrid://` のエイリアス |
| `sendgrid+smtp://` | SendGridSmtpTransport | SMTP TLS（ポート 587） |
| `sendgrid+smtps://` | SendGridSmtpTransport | SMTP SSL（ポート 465） |

外部 SDK 不要。API は `wppack/http-client` 経由で呼び出します。

## 依存関係

- `wppack/mailer` ^1.0
- `wppack/http-client` ^1.0

## ドキュメント

詳細は [docs/components/mailer/sendgrid-mailer.md](../../../docs/components/mailer/sendgrid-mailer.md) を参照してください。

## リソース

- [Issues](https://github.com/wppack-io/wppack/issues)
- [Pull Requests](https://github.com/wppack-io/wppack/pulls)

メインリポジトリ [wppack-io/wppack](https://github.com/wppack-io/wppack) で開発しています。
