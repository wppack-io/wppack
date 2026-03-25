# Handler

モダン PHP リクエストハンドラー。WordPress のフロントコントローラーとして、リクエストルーティング・静的ファイル配信・セキュリティチェックを一元管理する。

## インストール

```bash
composer require wppack/handler
```

## 概要

Symfony の `public/index.php` のように、シンプルなフロントコントローラーパターンを実現する:

```php
// web/index.php
use WpPack\Component\Handler\Handler;
use WpPack\Component\HttpFoundation\Request;

require dirname(__DIR__) . '/vendor/autoload.php';

$request = Request::createFromGlobals();
(new Handler())->handle($request);
```

`handle()` メソッドが以下のライフサイクルを一貫して管理する:

1. 環境セットアップ（Lambda ディレクトリ作成等）
2. プロセッサチェーンによるリクエスト処理
3. 静的ファイルやリダイレクトは即座にレスポンス送信
4. PHP ファイルの場合は `$_SERVER` 変数を準備
5. Kernel が利用可能なら `Kernel::create($request)` で Request を引き渡し
6. 対象の PHP ファイルを `require`

## プロセッサチェーン

リクエストは以下の順序で処理される:

| 順序 | プロセッサ | 役割 |
|------|-----------|------|
| 1 | `SecurityProcessor` | パス検証・攻撃ブロック |
| 2 | `MultisiteProcessor` | マルチサイト URL 書き換え |
| 3 | `TrailingSlashProcessor` | ディレクトリ末尾スラッシュリダイレクト |
| 4 | `DirectoryProcessor` | ディレクトリ → インデックスファイル解決 |
| 5 | `StaticFileProcessor` | 静的ファイル配信（MIME 型自動判定） |
| 6 | `PhpFileProcessor` | PHP ファイル直接リクエスト |
| 7 | `WordPressProcessor` | WordPress index.php フォールバック |

各プロセッサは `ProcessorInterface` を実装し、以下のいずれかを返す:

- `Response` — レスポンスを即座に送信し、チェーンを停止
- `Request` — 変更されたリクエストを次のプロセッサに渡す
- `null` — 現在のリクエストのまま次のプロセッサに進む

## 設定

```php
$config = new Configuration([
    'web_root'        => __DIR__,
    'wordpress_index' => '/index.php',
    'wp_directory'    => '/wp',
    'index_files'     => ['index.php', 'index.html', 'index.htm'],
    'multisite'       => true,       // デフォルトパターンで有効化
    'lambda'          => true,       // Lambda モード強制
    'security'        => [
        'allow_directory_listing' => false,
        'check_symlinks'         => true,
        'blocked_patterns'       => ['/\.git/', '/\.env/'],
    ],
]);
```

ドット記法で値にアクセス可能: `$config->get('security.check_symlinks')`

## マルチサイト

### シンプルモード

```php
$config = new Configuration(['multisite' => true]);
// デフォルトパターン: #^/[_0-9a-zA-Z-]+(/wp-.*)#
```

### カスタムパターン

```php
$config = new Configuration([
    'multisite' => [
        'enabled' => true,
        'pattern' => '#^/sites/([^/]+)(/wp-.*)#',
        'replacement' => '/wp$2',
    ],
]);
```

## Lambda サポート

Lambda 環境は自動検出される（`AWS_LAMBDA_FUNCTION_NAME` 等の環境変数）。`setup()` 時に `/tmp` 配下のディレクトリを自動作成する。

```php
// 手動制御
$config = new Configuration([
    'lambda' => [
        'enabled' => true,
        'directories' => ['/tmp/uploads', '/tmp/cache'],
    ],
]);
```

## Kernel 統合

`wppack/kernel` がインストールされている場合、Handler は WordPress ファイルを `require` する前に `Kernel::create($request)` を呼び出す。Kernel は `boot()` 時にこの Request を再利用し、`createFromGlobals()` の再実行を避ける。

## カスタムプロセッサ

```php
use WpPack\Component\Handler\Processor\ProcessorInterface;

class MaintenanceProcessor implements ProcessorInterface
{
    public function process(Request $request, Configuration $config): Request|Response|null
    {
        if (file_exists($config->get('web_root') . '/.maintenance')) {
            return new Response('メンテナンス中です', 503);
        }
        return null;
    }
}

$handler->addProcessor(new MaintenanceProcessor(), priority: 1);
```

## セキュリティ

- ディレクトリトラバーサル防止（`../`、エンコード済みパターン）
- Null バイトインジェクション防止
- シンボリックリンク検証（Web ルート外への脱出防止）
- 設定可能なブロックパターン（`.git`、`.env`、`wp-config.php` 等）

## 依存関係

- `wppack/http-foundation` — Request/Response
- `wppack/mime` — MIME 型判定
- `psr/log` — ロギング
- `wppack/kernel`（suggest）— Kernel 初期化

## 関連ドキュメント

- [API リファレンス](../../src/Component/Handler/docs/API.md)
- [名前空間実行モデル](../../src/Component/Handler/docs/NAMESPACE_EXECUTION.md)
- [テスト](../../src/Component/Handler/docs/TESTING.md)
