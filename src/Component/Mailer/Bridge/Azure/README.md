# Azure Mailer

WpPack Mailer の Azure Communication Services Email トランスポート実装。

## インストール

```bash
composer require wppack/azure-mailer
```

## DSN 設定

```php
// wp-config.php
define('MAILER_DSN', 'azure://my-resource.communication.azure.com:ACCESS_KEY@default');
```

| DSN | トランスポート | 送信方式 |
|-----|-------------|---------|
| `azure://` | AzureTransport | REST API 送信（推奨） |
| `azure+https://` | AzureTransport | `azure://` のエイリアス |
| `azure+api://` | AzureApiTransport | 構造化 API 送信 |

外部 SDK 不要。`wppack/http-client` 経由で Azure REST API を呼び出します。

## 依存関係

- `wppack/mailer` ^1.0
- `wppack/http-client` ^1.0

## ドキュメント

詳細は [docs/components/mailer/azure-mailer.md](../../../docs/components/mailer/azure-mailer.md) を参照してください。

## リソース

- [Issues](https://github.com/wppack-io/wppack/issues)
- [Pull Requests](https://github.com/wppack-io/wppack/pulls)

メインリポジトリ [wppack-io/wppack](https://github.com/wppack-io/wppack) で開発しています。
