# AzureMailer コンポーネント

**パッケージ:** `wppack/azure-mailer`
**名前空間:** `WpPack\Component\Mailer\Bridge\Azure\`
**レイヤー:** Abstraction

Mailer コンポーネントの Azure Communication Services Email トランスポート実装。Azure の REST API を使ったメール送信を提供します。外部 SDK 不要で、`wppack/http-client` 経由で API を呼び出します。

## インストール

```bash
composer require wppack/azure-mailer
```

## DSN 設定

```php
// wp-config.php

// Azure REST API（添付・HTML・マルチパートすべて対応、推奨）
define('MAILER_DSN', 'azure://my-resource.communication.azure.com:ACCESS_KEY@default');

// Azure 構造化 API
define('MAILER_DSN', 'azure+api://my-resource.communication.azure.com:ACCESS_KEY@default');

// API バージョンを指定
define('MAILER_DSN', 'azure://ENDPOINT:KEY@default?api_version=2024-07-01-preview');
```

### DSN 一覧

| DSN | トランスポート | 送信方式 |
|-----|-------------|---------|
| `azure://` | AzureTransport | **REST API**: PHPMailer のデータから完全なリクエストを構築して送信（添付対応） |
| `azure+https://` | AzureTransport | `azure://` のエイリアス |
| `azure+api://` | AzureApiTransport | **構造化 API**: From/To/Subject/Body を個別フィールドで送信 |

### DSN 形式

```
azure://ENDPOINT:ACCESS_KEY@default
azure+https://ENDPOINT:ACCESS_KEY@default
azure+api://ENDPOINT:ACCESS_KEY@default
```

- `user` = エンドポイント（例: `my-resource.communication.azure.com`）
- `password` = アクセスキー

### DSN オプション

| オプション | デフォルト | 説明 |
|-----------|-----------|------|
| `api_version` | `2024-07-01-preview` | Azure REST API バージョン |

## 認証方法

Azure Communication Services はアクセスキーによる HMAC-SHA256 認証を使用します。

### アクセスキー

Azure ポータルの Communication Services リソースから取得:

```php
define('MAILER_DSN', 'azure://my-resource.communication.azure.com:BASE64_ACCESS_KEY@default');
```

## トランスポートクラス

### AzureTransport

`AbstractTransport` を継承。PHPMailer のデータからリクエストを構築し、Azure Communication Services Email REST API で送信。添付ファイルを含むすべてのメール機能に対応。

```php
final class AzureTransport extends AbstractTransport
{
    public function __construct(
        private readonly string $endpoint,
        private readonly string $accessKey,
        private readonly string $apiVersion = '2024-07-01-preview',
    ) {}

    protected function getMailerName(): string { return 'azure'; }

    protected function doSend(PhpMailer $phpMailer): void
    {
        // Azure REST API で送信（添付ファイル対応）
    }
}
```

### AzureApiTransport

`AbstractApiTransport` を継承。PHPMailer のプロパティから構造化リクエストを構築して Azure REST API で送信。

```php
final class AzureApiTransport extends AbstractApiTransport
{
    protected function getMailerName(): string { return 'azureapi'; }

    protected function doSendApi(PhpMailer $phpMailer): string
    {
        // Azure 構造化 API で送信
    }
}
```

### AzureTransportFactory

DSN から適切な Azure トランスポートを生成するファクトリ。

```php
// wppack/azure-mailer がインストールされていれば fromDsn() で自動検出
$transport = Transport::fromDsn('azure://ENDPOINT:KEY@default');
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
use WpPack\Component\Mailer\Mailer;
use WpPack\Component\Mailer\Transport\Transport;

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
| `Transport\AzureTransport` | REST API トランスポート（`azure://`） |
| `Transport\AzureApiTransport` | 構造化 API トランスポート（`azure+api://`） |
| `Transport\AzureTransportFactory` | DSN ファクトリ |

## 依存関係

### 必須
- **wppack/mailer** -- トランスポート基盤（`TransportInterface`, `AbstractTransport`, `AbstractApiTransport`）
- **wppack/http-client** -- HTTP クライアント
