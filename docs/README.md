# WPPack ドキュメント

WordPress をモダンに扱うためのコンポーネントライブラリ。Symfony にインスパイアされた設計で、WordPress のエコシステムに最適化されたパッケージ群を提供します。

## プロジェクト概要

WPPack は、WordPress のグローバル関数・手続き型 API を、型安全な OOP インターフェースでラップするコンポーネントライブラリです。Symfony のパターンを WordPress に持ち込み、`declare(strict_types=1)` の世界で WordPress 開発を行えるようにします。

### Before / After

#### フック登録

**WordPress 標準**

```php
add_action('init', 'my_register_post_type');
add_action('save_post', 'my_clear_cache', 10, 2);

function my_register_post_type(): void {
    register_post_type('book', ['public' => true]);
}

function my_clear_cache(int $postId, \WP_Post $post): void {
    wp_cache_delete("book_{$postId}");
}
```

**WPPack — Attribute で宣言的に定義**

```php
#[AsHookSubscriber]
final class BookSubscriber
{
    #[InitAction]
    public function registerPostType(): void {
        register_post_type('book', ['public' => true]);
    }

    #[Action('save_post', priority: 10)]
    public function clearCache(int $postId, \WP_Post $post): void {
        wp_cache_delete("book_{$postId}");
    }
}
```

#### 設定読み込み

**WordPress 標準**

```php
$api_key = get_option('my_plugin_api_key', '');
$settings = get_option('my_plugin_settings');
$endpoint = $settings['api']['endpoint'] ?? 'https://default.example.com';
```

**WPPack — `#[Option]` でコンストラクタに注入**

```php
final readonly class ApiConfig
{
    public function __construct(
        #[Option('my_plugin_api_key')]
        public string $apiKey = '',

        #[Option('my_plugin_settings.api.endpoint')]
        public string $endpoint = 'https://default.example.com',
    ) {}
}
```

#### サービス管理

**WordPress 標準**

```php
// グローバル関数を直接呼び出し、依存関係は暗黙的
function my_send_notification(int $postId): void {
    $post = get_post($postId);
    $to = get_option('admin_email');
    wp_mail($to, "Updated: {$post->post_title}", '...');
}
add_action('save_post', 'my_send_notification');
```

**WPPack — DI コンテナによるコンストラクタインジェクション**

```php
#[AsHookSubscriber]
final readonly class PostNotifier
{
    public function __construct(
        private MailerInterface $mailer,
        #[Option('admin_email')]
        private string $adminEmail,
    ) {}

    #[Action('save_post')]
    public function notify(int $postId, \WP_Post $post): void {
        $this->mailer->send($this->adminEmail, "Updated: {$post->post_title}", '...');
    }
}
```

### 主な特徴

- **PHP Attributes による宣言的 API** — フック、ルート、ショートコード等を Attribute で定義
- **DI コンテナ** — Symfony スタイルのオートワイヤリングとサービス自動検出
- **型安全なラッパー** — WordPress API を `declare(strict_types=1)` で扱える
- **コンポーネント単位の導入** — 必要なパッケージだけ `composer require`
- **マルチクラウド対応** — コアは抽象インターフェース、AWS / GCP / Azure は Bridge パッケージで分離

## ドキュメント構成

### [architecture/](./architecture/) - アーキテクチャ

プロジェクト全体の設計思想と構造。詳細は [architecture/README.md](./architecture/README.md) を参照。

### [components/](./components/) - コンポーネント

各コンポーネントパッケージの詳細ドキュメント。詳細は [components/README.md](./components/README.md) を参照。

### [plugins/](./plugins/) - プラグイン

WordPress プラグインパッケージの詳細ドキュメント。詳細は [plugins/README.md](./plugins/README.md) を参照。

### [guides/](./guides/) - ガイド

実践的な開発ガイド。詳細は [guides/README.md](./guides/README.md) を参照。

### [wordpress/](./wordpress/) - WordPress コア仕様

WordPress 内部実装のリファレンス。詳細は [wordpress/README.md](./wordpress/README.md) を参照。

## Getting Started

### 必要要件

- PHP 8.2 以上
- Composer 2.x
- WordPress 6.3 以上

### インストール

必要なコンポーネントを個別にインストールします:

```bash
# メッセージング基盤
composer require wppack/messenger

# スケジューラー
composer require wppack/scheduler

# プラグイン
composer require wppack/eventbridge-scheduler-plugin
```

### 開発環境セットアップ

```bash
git clone https://github.com/wppack-io/wppack.git
cd wppack
composer install
```

### CI コマンド

```bash
vendor/bin/phpstan analyse              # 静的解析
vendor/bin/php-cs-fixer fix --dry-run --diff  # コードスタイルチェック
vendor/bin/phpunit                      # テスト実行
```
