# Amazon Mailer

WpPack Mailer の Amazon SES トランスポート実装。

## インストール

```bash
composer require wppack/amazon-mailer
```

## DSN 設定

```php
// wp-config.php
define('MAILER_DSN', 'ses://ACCESS_KEY:SECRET_KEY@default?region=ap-northeast-1');
```

| DSN | トランスポート | 送信方式 |
|-----|-------------|---------|
| `ses://` | SesTransport | Raw MIME 送信（推奨） |
| `ses+https://` | SesTransport | `ses://` のエイリアス |
| `ses+api://` | SesApiTransport | 構造化 API 送信 |
| `ses+smtp://` | SesSmtpTransport | SMTP 接続 |
| `ses+smtps://` | SesSmtpTransport | SMTP SSL（ポート 465） |

## 依存関係

- `wppack/mailer` ^1.0
- `async-aws/ses` ^1.14

## ドキュメント

詳細は [docs/components/mailer/amazon-mailer.md](../../../docs/components/mailer/amazon-mailer.md) を参照してください。

## リソース

- [Issues](https://github.com/wppack-io/wppack/issues)
- [Pull Requests](https://github.com/wppack-io/wppack/pulls)

メインリポジトリ [wppack-io/wppack](https://github.com/wppack-io/wppack) で開発しています。
