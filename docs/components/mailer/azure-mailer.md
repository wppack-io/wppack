# AzureMailer コンポーネント

**パッケージ:** `wppack/azure-mailer`
**名前空間:** `WPPack\Component\Mailer\Bridge\Azure\`
**Category:** Substrate (Async & Delivery)

Mailer コンポーネントの Azure Communication Services Email トランスポート実装。Azure の REST API を使ったメール送信を提供します。外部 SDK 不要で、`wppack/http-client` 経由で API を呼び出します。

## インストール

```bash
composer require wppack/azure-mailer
```

## DSN 設定

```php
// wp-config.php

// Azure REST API（推奨）
define('MAILER_DSN', 'azure://ACS_RESOURCE_NAME:ACCESS_KEY@default');

// Azure 構造化 API（明示的）
define('MAILER_DSN', 'azure+api://ACS_RESOURCE_NAME:ACCESS_KEY@default');

// API バージョンを指定
define('MAILER_DSN', 'azure://ACS_RESOURCE_NAME:ACCESS_KEY@default?api_version=2024-07-01-preview');
```

`ACS_RESOURCE_NAME` は Azure Communication Services のリソース名です。エンドポイント URL が `https://my-resource.communication.azure.com/` の場合、リソース名は `my-resource` になります。

### DSN 一覧

| DSN | トランスポート | 送信方式 |
|-----|-------------|---------|
| `azure://` | AzureApiTransport | **構造化 API**: デフォルト（`azure+api://` のエイリアス） |
| `azure+api://` | AzureApiTransport | **構造化 API**: From/To/Subject/Body を個別フィールドで送信（添付対応） |

### DSN 形式

```
azure://ACS_RESOURCE_NAME:ACCESS_KEY@default
azure+api://ACS_RESOURCE_NAME:ACCESS_KEY@default
```

- `user` = ACS リソース名（例: `my-resource`）。内部で `{resourceName}.communication.azure.com` にマッピング
- `password` = アクセスキー（Base64 エンコード済み）

### DSN オプション

| オプション | デフォルト | 説明 |
|-----------|-----------|------|
| `api_version` | `2024-07-01-preview` | Azure REST API バージョン |

## 認証方法

Azure Communication Services はアクセスキーによる HMAC-SHA256 認証を使用します。

### アクセスキー

Azure ポータルの Communication Services リソースから取得:

```php
define('MAILER_DSN', 'azure://my-resource:BASE64_ACCESS_KEY@default');
```

## トランスポートクラス

### AzureApiTransport

`AbstractApiTransport` を継承。PHPMailer のデータからリクエストを構築し、Azure Communication Services Email REST API で送信。添付ファイルを含むすべてのメール機能に対応。

```php
final class AzureApiTransport extends AbstractApiTransport
{
    private const HOST = '%s.communication.azure.com';

    public function __construct(
        private readonly string $resourceName,
        private readonly string $accessKey,
        private readonly string $apiVersion = '2024-07-01-preview',
        private readonly ?HttpClient $httpClient = null,
    ) {}

    protected function getMailerName(): string { return 'azureapi'; }

    protected function doSendApi(PhpMailer $phpMailer): string
    {
        // {resourceName}.communication.azure.com へ REST API で送信
    }
}
```

### AzureTransportFactory

DSN から Azure トランスポートを生成するファクトリ。

```php
// wppack/azure-mailer がインストールされていれば fromDsn() で自動検出
$transport = Transport::fromDsn('azure://ACS_RESOURCE_NAME:KEY@default');
```

## Azure Communication Services の設定

### 前提条件

1. Azure Communication Services リソースを作成
2. メール通信サービスを接続
3. 送信ドメインを検証（カスタムドメインまたは Azure マネージドドメイン）

### 必要な権限

Communication Services のアクセスキー（接続文字列から取得）に、メール送信権限が含まれています。

## クイックスタート

```php
use WPPack\Component\Mailer\Mailer;
use WPPack\Component\Mailer\Transport\Transport;

// Azure トランスポートの初期化（wppack/azure-mailer インストール済みで自動検出）
$transport = Transport::fromDsn(MAILER_DSN);
$mailer = new Mailer($transport);
$mailer->boot();

// wp_mail() が Azure 経由で送信される
wp_mail('user@example.com', 'Hello', 'World');
```

## クラス一覧

| クラス | 説明 |
|-------|------|
| `Transport\AzureApiTransport` | 構造化 API トランスポート（`azure://`, `azure+api://`） |
| `Transport\AzureTransportFactory` | DSN ファクトリ |

## 依存関係

### 必須
- **wppack/mailer** -- トランスポート基盤（`TransportInterface`, `AbstractApiTransport`）
- **wppack/http-client** -- HTTP クライアント
